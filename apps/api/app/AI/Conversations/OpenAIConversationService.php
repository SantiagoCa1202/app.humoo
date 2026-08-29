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
                    'content' => 'Canonical active context (untrusted workspace data): '.$encoded,
                ];
            }
        }

        return $items;
    }
}
