<?php

namespace App\AI\Orchestration;

use App\Models\ActionConfirmation;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

final class ConversationContinuationLifecycle
{
    /** @return array<int, array{call_id: string, output: array<string, mixed>}> */
    public function pendingProviderToolOutputs(Conversation $conversation): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];

        return collect($metadata['pending_provider_tool_outputs'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item)
                && trim((string) ($item['call_id'] ?? '')) !== ''
                && is_array($item['output'] ?? null))
            ->map(fn (array $item): array => [
                'call_id' => (string) $item['call_id'],
                'output' => $item['output'],
            ])
            ->values()
            ->all();
    }

    public function registerPendingProviderToolCall(
        Conversation $conversation,
        string $callId,
        ?string $continuationId,
        string $actionKey
    ): void {
        $callId = trim($callId);
        if ($callId === '') {
            return;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = collect($metadata['pending_provider_tool_outputs'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && trim((string) ($item['call_id'] ?? '')) !== '')
            ->reject(fn (array $item): bool => (string) ($item['call_id'] ?? '') === $callId)
            ->values()
            ->all();
        $pending[] = [
            'action_key' => $actionKey,
            'call_id' => $callId,
            'continuation_id' => $continuationId,
            'output' => null,
            'created_at' => now()->toIso8601String(),
        ];

        $conversation->forceFill(['metadata' => [
            ...$metadata,
            'pending_provider_tool_outputs' => $pending,
        ]])->save();
    }

    /** @param array<string, mixed> $result */
    public function resolvePendingProviderToolCall(
        Conversation $conversation,
        ?string $continuationId,
        string $actionKey,
        array $result
    ): void {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = collect($metadata['pending_provider_tool_outputs'] ?? []);
        $index = $pending->search(function (mixed $item) use ($continuationId, $actionKey): bool {
            if (!is_array($item) || trim((string) ($item['call_id'] ?? '')) === '') {
                return false;
            }

            if ($continuationId !== null) {
                return (string) ($item['continuation_id'] ?? '') === $continuationId;
            }

            return (string) ($item['action_key'] ?? '') === $actionKey;
        });

        if ($index === false) {
            Log::warning('ai.provider_tool_output.pending_call_missing', [
                'action_key' => $actionKey,
                'continuation_id' => $continuationId,
                'conversation_id' => $conversation->id,
                'workspace_id' => $conversation->workspace_id,
            ]);
            return;
        }

        $pending = $pending->values();
        $item = $pending->get($index);
        $item['output'] = $this->modelToolOutput($actionKey, $result);
        $item['resolved_at'] = now()->toIso8601String();
        $pending->put($index, $item);

        $conversation->forceFill(['metadata' => [
            ...$metadata,
            'pending_provider_tool_outputs' => $pending->all(),
        ]])->save();
    }

    /** @param array<string, mixed> $result */
    public function resolvePendingProviderToolCallForConfirmation(
        ActionConfirmation $confirmation,
        array $result
    ): void {
        $conversation = $confirmation->message?->conversation;
        if (!$conversation) {
            return;
        }

        $this->resolvePendingProviderToolCall(
            $conversation,
            (string) $confirmation->id,
            (string) $confirmation->action_key,
            $result
        );
    }

    /** @param array<int, string> $callIds */
    public function consumeProviderToolOutputs(Conversation $conversation, array $callIds): void
    {
        $ids = collect($callIds)->map(fn (mixed $id): string => trim((string) $id))->filter()->all();
        if ($ids === []) {
            return;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $remaining = collect($metadata['pending_provider_tool_outputs'] ?? [])
            ->reject(fn (mixed $item): bool => is_array($item) && in_array((string) ($item['call_id'] ?? ''), $ids, true))
            ->values()
            ->all();

        $conversation->forceFill(['metadata' => [
            ...$metadata,
            'pending_provider_tool_outputs' => $remaining,
        ]])->save();
    }

    public function clearProviderToolOutputs(Conversation $conversation): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        if (!array_key_exists('pending_provider_tool_outputs', $metadata)) {
            return;
        }

        $metadata['pending_provider_tool_outputs'] = [];
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    /** @param array<string, mixed> $result @return array<string, mixed> */
    private function modelToolOutput(string $actionKey, array $result): array
    {
        $status = (string) ($result['status'] ?? 'completed');
        $ok = !in_array($status, ['failed', 'final_not_found', 'cancelled'], true);

        return [
            'ok' => $ok,
            'code' => $ok ? null : ($status === 'cancelled' ? 'TOOL_CANCELLED' : 'TOOL_FAILED'),
            'message_for_model' => $ok ? 'Tool completed.' : 'The tool was not executed.',
            'retryable' => false,
            'allowed_next_actions' => [],
            'safe_details' => [
                'action_key' => $actionKey,
                'status' => $status,
                'result' => $result['result_ref_json'] ?? [],
                'entity_refs' => $result['entity_refs'] ?? [],
            ],
        ];
    }

    public function completeAfterConfirmation(ActionConfirmation $confirmation): void
    {
        $conversation = $confirmation->message?->conversation;
        if (!$conversation) {
            return;
        }

        $actionKey = (string) $confirmation->action_key;
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $draftState = is_array($metadata['active_recipe_draft_state'] ?? null)
            ? $metadata['active_recipe_draft_state']
            : [];
        $confirmedDraftId = data_get($confirmation->draft_json, 'draft_state.draft_id');
        $activeDraftId = $draftState['draft_id'] ?? null;
        $continuationId = $confirmedDraftId ?? $activeDraftId;
        $activeDraftIsCurrent = $confirmedDraftId === null
            || $activeDraftId === null
            || (string) $confirmedDraftId === (string) $activeDraftId;

        if ($confirmedDraftId !== null && $activeDraftId !== null && !$activeDraftIsCurrent) {
            Log::warning('ai.conversation.context_cleanup_skipped', [
                'action_key' => $actionKey,
                'confirmation_id' => $confirmation->id,
                'confirmed_draft_id' => $confirmedDraftId,
                'active_draft_id' => $activeDraftId,
                'conversation_id' => $conversation->id,
                'workspace_id' => $conversation->workspace_id,
            ]);
        }

        $metadata['pending_clarifications'] = collect($metadata['pending_clarifications'] ?? [])
            ->map(function (mixed $item) use ($actionKey, $continuationId): mixed {
                if (!is_array($item)
                    || ($item['workflow'] ?? $item['action_key'] ?? null) !== $actionKey
                    || ($item['status'] ?? null) !== 'pending') {
                    return $item;
                }

                $itemId = $item['draft_id'] ?? $item['continuation_id'] ?? null;
                if ($continuationId !== null && (string) $itemId === (string) $continuationId) {
                    $item['status'] = 'completed';
                }

                return $item;
            })
            ->values()
            ->all();

        $metadata['pending_continuations'] = collect($metadata['pending_continuations'] ?? [])
            ->map(function (mixed $item) use ($actionKey, $continuationId): mixed {
                if (!is_array($item)
                    || ($item['action_key'] ?? null) !== $actionKey
                    || ($item['status'] ?? null) !== 'pending') {
                    return $item;
                }

                $itemId = $item['draft_id'] ?? $item['continuation_id'] ?? null;
                if ($continuationId !== null && (string) $itemId === (string) $continuationId) {
                    $item['status'] = 'completed';
                }

                return $item;
            })
            ->values()
            ->all();

        if ($actionKey === 'recipes.create' && $activeDraftIsCurrent) {
            unset(
                $metadata['active_recipe_draft'],
                $metadata['active_recipe_draft_continuation_id'],
                $metadata['active_recipe_ingestion_issues'],
                $metadata['active_recipe_draft_state'],
                $metadata['active_recommendation_draft']
            );
        }

        $conversation->forceFill(['metadata' => $metadata])->save();

        Log::info('ai.conversation.context_completed', [
            'action_key' => $actionKey,
            'confirmation_id' => $confirmation->id,
            'continuation_id' => $continuationId,
            'conversation_id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
        ]);
    }
}
