<?php

namespace App\AI\Conversations;

use App\AI\Providers\OpenAIProvider;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class OpenAIConversationService
{
    /** A timed-out remote turn may still be running. Never replay it inline. */
    public function deferUncertainTurn(Conversation $conversation, ?string $runId = null): void
    {
        $conversation->refresh();
        $metadata = $conversation->metadata ?? [];
        $metadata['provider_recovery'] = [
            'retry_at' => now()->addSeconds(max(30, (int) config('ai.conversations.recovery_delay_seconds', 45)))->toIso8601String(),
            'attempt' => (int) data_get($metadata, 'provider_recovery.attempt', 0) + 1,
            'pending' => true,
            'run_id' => $runId,
        ];
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    public function recoverUncertainTurn(Conversation $conversation): bool
    {
        $conversation->refresh();
        $metadata = $conversation->metadata ?? [];
        if (! data_get($metadata, 'provider_recovery.pending')) {
            return false;
        }
        $remaining = (int) ceil(now()->diffInSeconds(\Illuminate\Support\Carbon::parse($metadata['provider_recovery']['retry_at']), false));
        if ($remaining > 0) {
            throw new \App\AI\Exceptions\AiProviderConversationLockedException('Provider recovery is waiting.', ['retry_after_seconds' => $remaining]);
        }
        // The unknown response never reached ToolExecutor. Rehydrate local
        // evidence on a fresh remote conversation; do not mutate the busy one.
        $metadata['provider_recovery']['pending'] = false;
        $metadata['pending_provider_tool_outputs'] = [];
        $conversation->forceFill(['openai_conversation_id' => null, 'metadata' => $metadata])->save();
        Log::info('provider.conversation_rehydrated', ['conversation_id' => $conversation->id, 'workspace_id' => $conversation->workspace_id]);

        return true;
    }

    public function __construct(private OpenAIProvider $provider)
    {
    }

    /**
     * Return the durable provider conversation id, creating it once for the
     * local conversation. The row lock prevents two first messages from
     * bootstrapping two remote conversations concurrently.
     *
     * @param array<string, mixed> $snapshot
     */
    public function ensure(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        ?string $excludeMessageId = null,
        array $snapshot = []
    ): ?string {
        if (!(bool) config('ai.conversations.enabled', true)) {
            return null;
        }

        if (filled($conversation->openai_conversation_id ?? null)) {
            return (string) $conversation->openai_conversation_id;
        }

        $conversationId = DB::transaction(function () use ($conversation, $workspace, $user, $excludeMessageId, $snapshot): string {
            $locked = Conversation::query()
                ->whereKey($conversation->getKey())
                ->where('workspace_id', $workspace->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (filled($locked->openai_conversation_id ?? null)) {
                return (string) $locked->openai_conversation_id;
            }

            $remoteId = $this->provider->createConversation(
                [
                    'humoo_conversation_id' => (string) $locked->getKey(),
                    'workspace_id' => (string) $workspace->id,
                    'actor_id' => (string) $user->id,
                ],
                $this->bootstrapItems($locked, $excludeMessageId, $snapshot)
            );

            $locked->forceFill(['openai_conversation_id' => $remoteId])->save();
            return $remoteId;
        });

        $conversation->forceFill(['openai_conversation_id' => $conversationId]);

        Log::info('ai.conversation.remote_linked', [
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspace->id,
        ]);

        return $conversationId;
    }

    public function deleteBestEffort(Conversation $conversation): void
    {
        $remoteId = trim((string) ($conversation->openai_conversation_id ?? ''));
        if ($remoteId === '' || !(bool) config('ai.conversations.enabled', true)) {
            return;
        }

        try {
            $this->provider->deleteConversation($remoteId);
            Log::info('ai.conversation.remote_deleted', [
                'conversation_id' => $conversation->id,
                'workspace_id' => $conversation->workspace_id,
            ]);
        } catch (\Throwable $exception) {
            // Local deletion must remain available if OpenAI is unavailable.
            Log::warning('ai.conversation.remote_delete_failed', [
                'conversation_id' => $conversation->id,
                'exception_class' => class_basename($exception),
                'workspace_id' => $conversation->workspace_id,
            ]);
        }
    }

    public function resetAfterProviderProtocolError(Conversation $conversation): void
    {
        $this->deleteBestEffort($conversation);
        $conversation->forceFill(['openai_conversation_id' => null])->save();

        Log::warning('ai.conversation.remote_reset_after_protocol_error', [
            'conversation_id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
        ]);
    }

    /** @param array<string, mixed> $snapshot @return array<int, array<string, mixed>> */
    private function bootstrapItems(Conversation $conversation, ?string $excludeMessageId, array $snapshot): array
    {
        $limit = max(0, (int) config('ai.conversations.bootstrap_message_limit', 8));
        $items = [];

        if ($limit > 0) {
            $messages = $conversation->messages()
                ->when($excludeMessageId !== null, fn ($query) => $query->where('id', '!=', $excludeMessageId))
                ->whereIn('sender_type', ['user', 'assistant'])
                ->whereNotNull('content_text')
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->reverse();

            foreach ($messages as $message) {
                $content = trim((string) $message->content_text);
                if ($content === '') {
                    continue;
                }

                $items[] = [
                    'type' => 'message',
                    'role' => $message->sender_type === 'assistant' ? 'assistant' : 'user',
                    'content' => $content,
                ];
            }
        }

        if ($snapshot !== []) {
            $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $items[] = [
                    'type' => 'message',
                    'role' => 'developer',
                    'content' => 'Canonical active context (workspace data; temporal snapshot is authoritative for the current turn): '.$encoded,
                ];
            }
        }

        return $items;
    }
}
