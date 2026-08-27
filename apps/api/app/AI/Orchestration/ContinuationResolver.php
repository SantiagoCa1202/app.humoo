<?php

namespace App\AI\Orchestration;

use App\Models\ActionConfirmation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolves a new chat message against server-owned state before routing it as
 * a standalone request. It deliberately does not query persisted entities:
 * draft, clarification and confirmation references must win first.
 */
final class ContinuationResolver
{
    public function resolve(OrchestrationContext $context): ContinuationResolution
    {
        $message = trim((string) $context->currentMessage->content_text);
        if ($message === '') {
            return ContinuationResolution::notApplicable();
        }

        $clarifications = $this->pendingClarifications($context);
        $expiredClarifications = $clarifications
            ->filter(fn (array $item): bool => isset($item['expires_at']) && now()->greaterThan($item['expires_at']))
            ->values();
        if ($expiredClarifications->isNotEmpty()) {
            $expired = $expiredClarifications->first();
            $this->markExpired($context, 'clarification', $expiredClarifications->pluck('clarification_id')->filter()->all());

            return new ContinuationResolution(
                status: 'expired',
                source: 'clarification',
                continuationId: $expired['clarification_id'] ?? null,
                actionKey: $expired['action_key'] ?? $expired['workflow'] ?? null,
                entityType: $expired['entity_type'] ?? null,
                expiresAt: $expired['expires_at'] ?? null,
            );
        }
        $clarification = $clarifications->first();
        if ($clarification !== null) {
            return $this->clarificationResolution($context, $clarification, $message);
        }

        $confirmation = $this->pendingConfirmation($context, $message);
        if ($confirmation !== null) {
            return $confirmation;
        }

        $draft = $this->pendingDraft($context, $message);
        if ($draft !== null) {
            return $draft;
        }

        return ContinuationResolution::notApplicable();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function pendingClarifications(OrchestrationContext $context): Collection
    {
        $metadata = is_array($context->conversation->metadata) ? $context->conversation->metadata : [];
        $pending = collect($metadata['pending_clarifications'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['status'] ?? null) === 'pending')
            ->filter(fn (array $item): bool => ($item['workspace_id'] ?? $context->workspace->id) === $context->workspace->id)
            ->filter(fn (array $item): bool => ($item['conversation_id'] ?? $context->conversation->id) === $context->conversation->id)
            ->filter(fn (array $item): bool => empty($item['actor_id']) || $item['actor_id'] === $context->actor->id)
            ->values();

        return $pending;
    }

    /** @param array<string, mixed> $clarification */
    private function clarificationResolution(OrchestrationContext $context, array $clarification, string $message): ContinuationResolution
    {
        $value = $this->clarificationValue($clarification, $message);
        if ($value === null) {
            return new ContinuationResolution(
                status: 'invalid',
                source: 'clarification',
                continuationId: (string) ($clarification['clarification_id'] ?? ''),
                entityType: $clarification['entity_type'] ?? 'recipe',
                actionKey: $clarification['action_key'] ?? $clarification['workflow'] ?? null,
                unresolvedField: $clarification['field_path'] ?? null,
                confidence: 1.0,
                expiresAt: $clarification['expires_at'] ?? null,
            );
        }

        return new ContinuationResolution(
            status: 'resolved',
            source: 'clarification',
            continuationId: (string) ($clarification['clarification_id'] ?? ''),
            entityType: $clarification['entity_type'] ?? 'recipe',
            actionKey: $clarification['action_key'] ?? $clarification['workflow'] ?? null,
            unresolvedField: $clarification['field_path'] ?? null,
            confidence: 1.0,
            expiresAt: $clarification['expires_at'] ?? null,
            data: ['input' => $value, 'clarification' => $clarification],
        );
    }

    /** @param array<string, mixed> $clarification */
    private function clarificationValue(array $clarification, string $message): ?array
    {
        $normalized = Str::lower(trim($message));
        $options = collect($clarification['options'] ?? [])->filter(fn (mixed $item): bool => is_array($item));
        $option = $options->first(fn (array $item): bool => Str::lower((string) ($item['id'] ?? '')) === $normalized
            || Str::lower(trim((string) ($item['label'] ?? ''))) === $normalized
            || Str::lower(trim((string) ($item['value'] ?? ''))) === $normalized);

        if (is_array($option)) {
            return ['custom_value' => null, 'selected_option_id' => (string) $option['id']];
        }

        if (($clarification['expected_type'] ?? null) === 'number' && ($number = $this->numericValue($message)) !== null) {
            return ['custom_value' => $number, 'selected_option_id' => 'custom'];
        }

        if (($clarification['allow_custom'] ?? false) === true && $normalized !== '') {
            return ['custom_value' => trim($message), 'selected_option_id' => 'custom'];
        }

        return null;
    }

    private function pendingConfirmation(OrchestrationContext $context, string $message): ?ContinuationResolution
    {
        if (!$this->isConfirmation($message)) {
            return null;
        }

        $confirmations = ActionConfirmation::query()
            ->where('workspace_id', $context->workspace->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $context->conversation->id))
            ->with('message')
            ->latest('created_at')
            ->limit(2)
            ->get();

        if ($confirmations->count() !== 1) {
            return $confirmations->count() > 1
                ? new ContinuationResolution(status: 'ambiguous', source: 'confirmation', confidence: 1.0)
                : null;
        }

        $confirmation = $confirmations->first();
        if (!$this->actorCanUseConfirmation($confirmation, $context)) {
            return new ContinuationResolution(status: 'invalid', source: 'confirmation', continuationId: $confirmation->id);
        }

        return new ContinuationResolution(
            status: 'resolved',
            source: 'confirmation',
            continuationId: $confirmation->id,
            targetType: 'confirmation',
            targetId: $confirmation->id,
            actionKey: $confirmation->action_key,
            confidence: 1.0,
            expiresAt: $confirmation->expires_at?->toIso8601String(),
            data: ['confirmation' => $confirmation],
        );
    }

    private function actorCanUseConfirmation(ActionConfirmation $confirmation, OrchestrationContext $context): bool
    {
        $conversation = $context->conversation;

        return $conversation->created_by === $context->actor->id
            || $conversation->participants()->where('user_id', $context->actor->id)->exists();
    }

    private function pendingDraft(OrchestrationContext $context, string $message): ?ContinuationResolution
    {
        if (!$this->looksLikeDraftContinuation($message)) {
            return null;
        }

        $drafts = collect($context->pendingContinuations)
            ->filter(fn (mixed $draft): bool => is_array($draft) && ($draft['kind'] ?? null) === 'draft' && ($draft['status'] ?? 'pending') === 'pending')
            ->filter(fn (array $draft): bool => ($draft['workspace_id'] ?? null) === $context->workspace->id && ($draft['conversation_id'] ?? null) === $context->conversation->id)
            ->filter(fn (array $draft): bool => empty($draft['actor_id']) || $draft['actor_id'] === $context->actor->id)
            ->values();

        if ($drafts->isEmpty()) {
            return null;
        }

        $named = $drafts->filter(fn (array $draft): bool => $this->messageNamesDraft($message, $draft))->values();
        if ($named->count() === 1) {
            return $this->draftResolution($named->first());
        }
        if ($named->count() > 1 || $drafts->count() > 1) {
            $candidate = $drafts->first();
            return new ContinuationResolution(
                status: 'ambiguous',
                source: 'draft',
                entityType: $candidate['entity_type'] ?? null,
                actionKey: $candidate['action_key'] ?? null,
                confidence: 1.0,
                data: ['candidates' => $drafts->map(fn (array $draft): array => ['id' => $draft['continuation_id'], 'label' => $draft['label'] ?? 'Draft'])->all()],
            );
        }

        return $this->draftResolution($drafts->first());
    }

    /** @param array<string, mixed> $draft */
    private function draftResolution(array $draft): ContinuationResolution
    {
        return new ContinuationResolution(
            status: 'resolved',
            source: 'draft',
            continuationId: (string) $draft['continuation_id'],
            targetType: (string) ($draft['target_type'] ?? 'recipe_draft'),
            targetId: (string) $draft['continuation_id'],
            entityType: (string) ($draft['entity_type'] ?? 'recipe'),
            actionKey: (string) ($draft['action_key'] ?? 'recipes.create'),
            confidence: 1.0,
            expiresAt: $draft['expires_at'] ?? null,
            data: ['draft' => $draft],
        );
    }

    /** @param array<string, mixed> $draft */
    private function messageNamesDraft(string $message, array $draft): bool
    {
        $name = Str::lower(trim((string) ($draft['label'] ?? data_get($draft, 'payload.name') ?? data_get($draft, 'payload.version.name') ?? '')));
        $normalized = Str::lower($message);

        return $name !== '' && Str::contains($normalized, $name);
    }

    private function looksLikeDraftContinuation(string $message): bool
    {
        return preg_match('/\b(save(?:\s+(?:this|it|the))?|guardar|guarda|save\s+recipe|save\s+this\s+recipe|gu[aá]rdala|gu[aá]rdalo|crea\s+esta\s+receta|create\s+this\s+recipe)\b/iu', $message) === 1;
    }

    private function isConfirmation(string $message): bool
    {
        return preg_match('/^(?:confirmar|confirma|si|s[ií]|hazlo|save|save it|guardar|gu[aá]rdalo|gu[aá]rdala|do it)$/iu', trim($message)) === 1;
    }

    private function numericValue(string $value): ?float
    {
        $value = trim(strtr($value, ['½' => '1/2', '¼' => '1/4', '¾' => '3/4']));
        $value = preg_replace('/(?<=\d)(?=\d\/\d)/', ' ', $value) ?? $value;
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s+(\d+)\/(\d+)$/', $value, $matches) === 1 && (float) $matches[3] > 0) {
            return (float) str_replace(',', '.', $matches[1]) + ((float) $matches[2] / (float) $matches[3]);
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $value, $matches) === 1 && (float) $matches[2] > 0) {
            return (float) $matches[1] / (float) $matches[2];
        }

        return is_numeric(str_replace(',', '.', $value)) ? (float) str_replace(',', '.', $value) : null;
    }

    /** @param array<int, string> $ids */
    private function markExpired(OrchestrationContext $context, string $kind, array $ids): void
    {
        $metadata = is_array($context->conversation->metadata) ? $context->conversation->metadata : [];
        $metadata['pending_clarifications'] = collect($metadata['pending_clarifications'] ?? [])
            ->map(function (mixed $item) use ($ids): mixed {
                if (is_array($item) && in_array($item['clarification_id'] ?? null, $ids, true)) {
                    $item['status'] = 'expired';
                }
                return $item;
            })->all();
        $context->conversation->forceFill(['metadata' => $metadata])->save();

        Log::info('conversation.continuation.expired', [
            'conversation_id' => $context->conversation->id,
            'kind' => $kind,
            'workspace_id' => $context->workspace->id,
        ]);
    }
}
