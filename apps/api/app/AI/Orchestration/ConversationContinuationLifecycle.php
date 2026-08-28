<?php

namespace App\AI\Orchestration;

use App\Models\ActionConfirmation;
use Illuminate\Support\Facades\Log;

final class ConversationContinuationLifecycle
{
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
