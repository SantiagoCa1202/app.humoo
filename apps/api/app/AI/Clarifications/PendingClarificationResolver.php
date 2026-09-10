<?php

namespace App\AI\Clarifications;

use App\AI\EntityResolution\EntityReferenceResolver;
use App\AI\EntityResolution\EntityResolutionRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PendingClarificationResolver
{
    public function __construct(private EntityReferenceResolver $entityReferenceResolver) {}

    public function resolveEntity(object $conversation, string $workspaceId, string $actorId, string $clarificationId, string $candidateId): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : [];
        $index = collect($pending)->search(fn (mixed $item): bool => is_array($item) && ($item['clarification_id'] ?? null) === $clarificationId);
        $clarification = $index === false ? null : $pending[$index];
        if (!is_array($clarification) || ($clarification['type'] ?? null) !== 'entity.disambiguation' || ($clarification['status'] ?? null) !== 'pending'
            || ($clarification['workspace_id'] ?? null) !== $workspaceId || ($clarification['conversation_id'] ?? null) !== $conversation->id
            || ($clarification['actor_id'] ?? null) !== $actorId || now()->greaterThan($clarification['expires_at'] ?? now()->subSecond())) {
            throw ValidationException::withMessages(['clarification_id' => ['This entity selection is unavailable or expired.']]);
        }

        $entityType = (string) ($clarification['entity_type'] ?? '');
        $field = (string) ($clarification['unresolved_field'] ?? '');
        $selectedCandidate = collect($clarification['candidate_snapshot'] ?? [])
            ->first(fn (mixed $candidate): bool => is_array($candidate) && ($candidate['entity_id'] ?? null) === $candidateId);
        $allowed = is_array($selectedCandidate);
        if (!$allowed || $entityType === '' || $field === '') {
            throw ValidationException::withMessages(['candidate_id' => ['The selected entity is unavailable.']]);
        }

        $resolution = $this->entityReferenceResolver->resolve(new EntityResolutionRequest(
            workspaceId: $workspaceId, actorId: $actorId, conversationId: $conversation->id,
            actionKey: $clarification['action_key'] ?? null, entityType: $entityType,
            unresolvedField: $field, knownPayload: [$field => $candidateId],
            riskLevel: $clarification['risk_level'] ?? 'write',
        ));
        if ($resolution->status !== 'resolved') {
            throw ValidationException::withMessages(['candidate_id' => ['The selected entity is no longer accessible.']]);
        }

        $continuation = is_array($clarification['original_payload'] ?? null) ? $clarification['original_payload'] : [];
        // The component posts the opaque option ID. Persist and replay that
        // exact snapshot ID; never re-resolve by the displayed label or its
        // ordinal, which can point at another candidate after a refresh.
        data_set($continuation, 'input.'.$field, $candidateId);
        data_set($continuation, 'resolved_entity_ids.'.$field, $candidateId);
        $originalReference = trim((string) ($clarification['original_reference'] ?? ''));
        if ($originalReference !== '') {
            $continuation['_entity_reference_alias'] = [
                'alias' => $originalReference,
                'entity_id' => $candidateId,
                'entity_type' => $entityType,
                'locale' => (string) ($clarification['locale'] ?? 'en'),
            ];
        }
        $pending[$index]['status'] = 'resolved';
        $pending[$index]['resolved_entity_id'] = $candidateId;
        $pending[$index]['resolved_candidate'] = [
            'entity_id' => $candidateId,
            'display_name' => $selectedCandidate['display_name'] ?? null,
        ];
        $metadata['confirmed_entity_targets'] = [
            ...(is_array($metadata['confirmed_entity_targets'] ?? null) ? $metadata['confirmed_entity_targets'] : []),
            [
                'action_key' => $clarification['action_key'] ?? null,
                'entity_id' => $candidateId,
                'entity_type' => $entityType,
                'field' => $field,
                'resolved_at' => now()->toIso8601String(),
            ],
        ];
        $metadata['pending_clarifications'] = $pending;
        $conversation->forceFill(['metadata' => $metadata])->save();

        return $continuation;
    }

    public function rejectEntity(object $conversation, string $workspaceId, string $actorId, string $clarificationId): ?array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : [];
        $index = collect($pending)->search(fn (mixed $item): bool => is_array($item) && ($item['clarification_id'] ?? null) === $clarificationId);
        $clarification = $index === false ? null : $pending[$index];
        if (!is_array($clarification) || ($clarification['type'] ?? null) !== 'entity.disambiguation' || ($clarification['mode'] ?? null) !== 'confirm_suggestion'
            || ($clarification['status'] ?? null) !== 'pending' || ($clarification['workspace_id'] ?? null) !== $workspaceId
            || ($clarification['conversation_id'] ?? null) !== $conversation->id || ($clarification['actor_id'] ?? null) !== $actorId
            || now()->greaterThan($clarification['expires_at'] ?? now()->subSecond())) {
            throw ValidationException::withMessages(['clarification_id' => ['This entity suggestion is unavailable or expired.']]);
        }

        $candidates = collect($clarification['candidate_snapshot'] ?? [])
            ->filter(fn (mixed $candidate): bool => is_array($candidate) && filled($candidate['entity_id'] ?? null))
            ->values();
        $rejectedCandidateId = (string) ($candidates->first()['entity_id'] ?? '');
        $remaining = $candidates
            ->reject(fn (array $candidate): bool => ($candidate['entity_id'] ?? null) === $rejectedCandidateId)
            ->values()
            ->all();

        $pending[$index]['rejected_candidate_ids'] = $rejectedCandidateId === '' ? [] : [$rejectedCandidateId];
        if ($remaining !== []) {
            $pending[$index]['candidate_snapshot'] = $remaining;
            $pending[$index]['mode'] = 'choose_candidate';
        } else {
            $pending[$index]['status'] = 'rejected';
        }
        $metadata['pending_clarifications'] = $pending;
        $conversation->forceFill(['metadata' => $metadata])->save();
        Log::info('entity_reference.suggestion_rejected', [
            'clarification_id' => $clarificationId,
            'entity_type' => $clarification['entity_type'] ?? null,
            'remaining_candidate_count' => count($remaining),
            'workspace_id' => $workspaceId,
        ]);

        return $remaining === [] ? null : [
            'clarification_id' => $clarificationId,
            'entity_type' => $clarification['entity_type'] ?? 'record',
            'expires_at' => $clarification['expires_at'] ?? null,
            'options' => array_map(static fn (array $candidate): array => [
                'id' => $candidate['entity_id'],
                'label' => $candidate['display_name'] ?? $candidate['entity_id'],
                'metadata' => $candidate['safe_metadata'] ?? [],
                'value' => $candidate['entity_id'],
            ], $remaining),
        ];
    }

    public function resolve(object $conversation, string $workspaceId, string $clarificationId, array $input, ?string $actorId = null): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : [];
        $index = collect($pending)->search(fn (mixed $item): bool => is_array($item) && ($item['clarification_id'] ?? null) === $clarificationId);
        $clarification = $index === false ? null : $pending[$index];
        if (!is_array($clarification)
            || ($clarification['conversation_id'] ?? $conversation->id) !== $conversation->id
            || ($clarification['workspace_id'] ?? $workspaceId) !== $workspaceId
            || ($actorId !== null && !empty($clarification['actor_id']) && $clarification['actor_id'] !== $actorId)
            || ($clarification['status'] ?? null) !== 'pending') {
            throw ValidationException::withMessages(['clarification_id' => ['This clarification is unavailable.']]);
        }

        $selected = (string) ($input['selected_option_id'] ?? '');
        $option = collect($clarification['options'] ?? [])->firstWhere('id', $selected);
        if (!is_array($option) && ($clarification['type'] ?? null) === 'entity.disambiguation') {
            $candidate = collect($clarification['candidate_snapshot'] ?? [])
                ->first(fn (mixed $item): bool => is_array($item) && ($item['entity_id'] ?? null) === $selected);
            if (is_array($candidate)) {
                $option = [
                    'id' => $selected,
                    'value' => $selected,
                ];
            }
        }
        $usedCustom = $selected === 'custom';
        $value = $usedCustom ? ($input['custom_value'] ?? null) : ($option['value'] ?? null);
        $expectedType = (string) ($clarification['expected_type'] ?? 'number');
        if (($clarification['type'] ?? null) === 'entity.disambiguation' && is_array($option)) {
            return $this->resolveEntity(
                $conversation,
                $workspaceId,
                $actorId ?? '',
                $clarificationId,
                (string) $option['value']
            );
        }
        $validValue = $expectedType === 'number'
            ? is_numeric($value) && (float) $value > 0
            : is_string($value) && trim($value) !== '';
        if (!$validValue || (!$usedCustom && !is_array($option)) || ($usedCustom && !($clarification['allow_custom'] ?? false))) {
            throw ValidationException::withMessages(['value' => ['The selected clarification value is invalid.']]);
        }

        $state = is_array($metadata['active_recipe_draft_state'] ?? null)
            ? $metadata['active_recipe_draft_state']
            : [];
        $draftId = (string) ($clarification['draft_id'] ?? '');
        $draft = $draftId !== ''
            && ($state['draft_id'] ?? null) === $draftId
            && is_array($state['payload'] ?? null)
                ? $state['payload']
                : null;
        $fieldPath = (string) ($clarification['field_path'] ?? '');
        $isNumber = $expectedType === 'number';
        if (!is_array($draft) || $fieldPath === '') {
            throw ValidationException::withMessages(['clarification_id' => ['The associated draft is no longer available.']]);
        }
        if ($isNumber && (!is_numeric($value) || (float) $value <= 0)) {
            throw ValidationException::withMessages(['value' => ['The selected clarification value is invalid.']]);
        }
        if (!$isNumber && (!is_string($value) || trim($value) === '')) {
            throw ValidationException::withMessages(['value' => ['The selected clarification value is invalid.']]);
        }
        $resolvedValue = $isNumber ? (float) $value : trim((string) $value);
        data_set($draft, $fieldPath, $resolvedValue);
        if ($isNumber && str_ends_with($fieldPath, '.quantity')) {
            $rangeBase = substr($fieldPath, 0, -strlen('.quantity'));
            data_forget($draft, $rangeBase.'.quantity_min');
            data_forget($draft, $rangeBase.'.quantity_max');
        }
        $metadata['active_recipe_draft_state']['payload'] = $draft;
        $metadata['active_recipe_draft_state']['revision'] = (int) ($state['revision'] ?? 0) + 1;
        $metadata['active_recipe_draft_state']['status'] = 'needs_clarification';
        $pending[$index]['status'] = 'resolved';
        $pending[$index]['resolved_value'] = $resolvedValue;
        $metadata['pending_clarifications'] = $pending;
        $conversation->forceFill(['metadata' => $metadata])->save();
        Log::info('ai.clarification.resolved', ['workflow' => $clarification['action_key'] ?? $clarification['workflow'] ?? null, 'clarification_type' => $clarification['type'] ?? null, 'draft_id' => $clarification['draft_id'] ?? $clarification['continuation_id'] ?? null, 'field_path' => $fieldPath, 'expected_type' => $expectedType, 'selection_mode' => 'single', 'used_custom' => $usedCustom, 'router_bypassed' => true, 'ai_bypassed' => true, 'workspace_id' => $workspaceId]);

        return [
            'draft' => $draft,
            'input' => is_array($draft['input'] ?? null) ? $draft['input'] : $draft,
            'clarification' => $pending[$index],
        ];
    }

    public function cancel(object $conversation, string $workspaceId, string $clarificationId): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : [];
        foreach ($pending as $index => $clarification) {
            if (is_array($clarification) && ($clarification['clarification_id'] ?? null) === $clarificationId && ($clarification['status'] ?? null) === 'pending') {
                $pending[$index]['status'] = 'cancelled';
                $metadata['pending_clarifications'] = $pending;
                $conversation->forceFill(['metadata' => $metadata])->save();
                Log::info('ai.clarification.cancelled', ['workflow' => $clarification['workflow'] ?? null, 'workspace_id' => $workspaceId]);
                return;
            }
        }
        throw ValidationException::withMessages(['clarification_id' => ['This clarification is unavailable.']]);
    }
}
