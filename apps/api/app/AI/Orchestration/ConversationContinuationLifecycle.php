<?php

namespace App\AI\Orchestration;

use App\Models\ActionConfirmation;
use App\Models\Conversation;
use App\Models\Message;
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
        array $result,
        ?string $providerCallId = null
    ): bool {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = collect($metadata['pending_provider_tool_outputs'] ?? []);
        $index = $pending->search(function (mixed $item) use ($continuationId, $actionKey, $providerCallId): bool {
            if (!is_array($item) || trim((string) ($item['call_id'] ?? '')) === '') {
                return false;
            }

            if ($providerCallId !== null) {
                return (string) ($item['call_id'] ?? '') === $providerCallId;
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
            return false;
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

        return true;
    }

    public function pendingProviderToolCallId(Conversation $conversation, ?string $continuationId): ?string
    {
        if ($continuationId === null || trim($continuationId) === '') {
            return null;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];

        $pending = collect($metadata['pending_provider_tool_outputs'] ?? [])
            ->first(fn (mixed $item): bool => is_array($item)
                && (string) ($item['continuation_id'] ?? '') === $continuationId
                && trim((string) ($item['call_id'] ?? '')) !== '');
        $callId = is_array($pending) ? ($pending['call_id'] ?? null) : null;

        return filled($callId) ? (string) $callId : null;
    }

    /** @param array<string, mixed> $result */
    public function resolvePendingProviderToolCallForConfirmation(
        ActionConfirmation $confirmation,
        array $result
    ): bool {
        $conversation = $confirmation->message?->conversation;
        if (!$conversation) {
            return false;
        }

        return $this->resolvePendingProviderToolCall(
            $conversation,
            (string) $confirmation->id,
            (string) $confirmation->action_key,
            $result,
            filled(data_get($confirmation->draft_json, 'provider_call_id'))
                ? (string) data_get($confirmation->draft_json, 'provider_call_id')
                : null
        );
    }

    /**
     * A normal chat message can arrive while the provider is waiting for the
     * result of a confirmation-gated tool call. Resolve only that provider
     * handoff so the model can assess the new message. The confirmation stays
     * pending until a revised preview replaces it or the user uses its
     * explicit confirmation control.
     */
    public function acknowledgeUserMessageBeforeConfirmation(
        ActionConfirmation $confirmation,
        Message $message
    ): bool {
        $conversation = $confirmation->message?->conversation;
        if (!$conversation || $confirmation->status !== 'pending') {
            return false;
        }

        $providerCallId = filled(data_get($confirmation->draft_json, 'provider_call_id'))
            ? (string) data_get($confirmation->draft_json, 'provider_call_id')
            : null;
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $hasWaitingProviderCall = collect($metadata['pending_provider_tool_outputs'] ?? [])
            ->contains(function (mixed $item) use ($confirmation, $providerCallId): bool {
                if (!is_array($item) || trim((string) ($item['call_id'] ?? '')) === '') {
                    return false;
                }

                if (is_array($item['output'] ?? null)) {
                    return false;
                }

                return $providerCallId !== null
                    ? (string) ($item['call_id'] ?? '') === $providerCallId
                    : (string) ($item['continuation_id'] ?? '') === (string) $confirmation->id;
            });
        if (!$hasWaitingProviderCall) {
            return false;
        }

        $resolved = $this->resolvePendingProviderToolCall(
            $conversation,
            (string) $confirmation->id,
            (string) $confirmation->action_key,
            [
                'workflow_status' => 'revision_requested',
                'result_ref_json' => [
                    'confirmation_id' => $confirmation->id,
                    'draft_id' => data_get($confirmation->draft_json, 'draft_state.draft_id'),
                    'message_id' => $message->id,
                    'revision' => data_get($confirmation->draft_json, 'draft_state.revision'),
                ],
            ],
            $providerCallId,
        );

        if ($resolved) {
            Log::info('ai.confirmation.user_message_received', [
                'action_key' => $confirmation->action_key,
                'confirmation_id' => $confirmation->id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'workspace_id' => $conversation->workspace_id,
            ]);
        }

        return $resolved;
    }

    /**
     * The model, not a local text classifier, interprets a response to a
     * pending clarification. This only closes the provider handoff so the
     * latest user message can be evaluated in the canonical tool loop.
     */
    public function acknowledgeUserMessageBeforeClarification(
        Conversation $conversation,
        string $continuationId,
        string $actionKey,
        Message $message
    ): bool {
        $continuationId = trim($continuationId);
        $actionKey = trim($actionKey);
        if ($continuationId === '' || $actionKey === '') {
            return false;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $hasWaitingProviderCall = collect($metadata['pending_provider_tool_outputs'] ?? [])
            ->contains(fn (mixed $item): bool => is_array($item)
                && (string) ($item['continuation_id'] ?? '') === $continuationId
                && trim((string) ($item['call_id'] ?? '')) !== ''
                && !is_array($item['output'] ?? null));
        if (!$hasWaitingProviderCall) {
            return false;
        }

        $resolved = $this->resolvePendingProviderToolCall(
            $conversation,
            $continuationId,
            $actionKey,
            [
                'workflow_status' => 'clarification_response_received',
                'result_ref_json' => [
                    'clarification_id' => $continuationId,
                    'message_id' => $message->id,
                ],
            ],
        );

        if ($resolved) {
            Log::info('ai.clarification.user_message_received', [
                'action_key' => $actionKey,
                'clarification_id' => $continuationId,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'workspace_id' => $conversation->workspace_id,
            ]);
        }

        return $resolved;
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
        $status = (string) ($result['status']
            ?? $result['workflow_status']
            ?? (is_array($result['confirmation'] ?? null) ? 'confirmation_required' : 'completed'));
        $ok = !in_array($status, ['failed', 'final_not_found', 'cancelled'], true);

        return [
            'ok' => $ok,
            'code' => match ($status) {
                'revision_requested' => 'CONFIRMATION_REVISION_REQUESTED',
                'clarification_response_received' => 'CLARIFICATION_RESPONSE_RECEIVED',
                'cancelled' => 'TOOL_CANCELLED',
                default => $ok ? null : 'TOOL_FAILED',
            },
            'message_for_model' => match ($status) {
                'confirmation_required' => 'The tool produced a confirmation request. Wait for the user confirmation before continuing.',
                'revision_requested' => 'The user sent a message before confirming. Assess whether it revises the pending plan. Do not execute the pending write. If the user changes it, prepare a new preview with the canonical write tool; otherwise answer without changing the pending confirmation.',
                'clarification_response_received' => 'The user responded to a pending clarification. Use the latest user message and the authoritative clarification context to continue with the canonical tool; do not apply a local parser or classifier.',
                default => $ok ? 'Tool completed.' : 'The tool was not executed.',
            },
            'retryable' => false,
            'allowed_next_actions' => in_array($status, ['revision_requested', 'clarification_response_received'], true)
                ? ['review_latest_user_message']
                : [],
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
