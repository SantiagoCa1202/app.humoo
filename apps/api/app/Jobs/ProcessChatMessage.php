<?php

namespace App\Jobs;

use App\AI\Orchestration\AIOrchestrator;
use App\AI\Streaming\ChatStreamPublisher;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\WorkspaceContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessChatMessage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $conversationId,
        public string $workspaceId,
        public string $userId,
        public string $messageId,
    ) {
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->conversationLockKey()))
                ->releaseAfter(3)
                ->expireAfter($this->timeout + 60),
        ];
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [5, 20];
    }

    public function uniqueId(): string
    {
        return $this->messageId;
    }

    public function handle(
        AIOrchestrator $aiOrchestrator,
        ChatStreamPublisher $chatStreamPublisher,
        WorkspaceContextService $workspaceContext,
    ): void {
        $message = Message::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('conversation_id', $this->conversationId)
            ->whereKey($this->messageId)
            ->first();
        $conversation = Conversation::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereKey($this->conversationId)
            ->first();
        $workspace = Workspace::query()->find($this->workspaceId);
        $user = User::query()->find($this->userId);
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('user_id', $this->userId)
            ->where('status', 'active')
            ->first();

        if (! $message || ! $conversation || ! $workspace || ! $user || ! $membership) {
            Log::warning('ai.chat.message_processing_skipped', [
                'conversation_id' => $this->conversationId,
                'message_id' => $this->messageId,
                'reason' => 'context_not_available',
                'workspace_id' => $this->workspaceId,
            ]);

            $message?->forceFill([
                'error_code' => 'AI_PROCESSING_UNAVAILABLE',
                'status' => 'failed',
            ])->save();

            return;
        }

        if (! in_array($message->status, ['pending', 'streaming'], true)) {
            return;
        }

        $nextMessage = Message::query()
            ->where('conversation_id', $this->conversationId)
            ->where('sender_type', 'user')
            ->whereIn('status', ['pending', 'streaming'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($nextMessage && (string) $nextMessage->id !== $this->messageId) {
            $this->release(3);

            return;
        }

        $message->forceFill(['status' => 'streaming'])->save();

        $assistantMessage = $workspaceContext->within($workspace, $membership, function () use ($aiOrchestrator, $conversation, $membership, $message, $user, $workspace): Message {
            return $aiOrchestrator->respond(
                $conversation,
                $workspace,
                $membership,
                $user,
                $message,
                [
                    'content' => $message->content_text,
                    'locale' => $message->locale,
                ],
            );
        });

        $message->fresh()->forceFill([
            'error_code' => null,
            'status' => 'completed',
        ])->save();

        // AIOrchestrator publishes its terminal event as soon as the assistant
        // message is persisted. The user message is finalized immediately after
        // that call, so publish once more from the queue boundary after both
        // records are consistent. Consumers can now refetch a terminal snapshot
        // without observing the user message stuck in "streaming".
        $assistantMessage = $assistantMessage->fresh();

        if ($assistantMessage?->status === 'failed') {
            $chatStreamPublisher->failed($conversation, $assistantMessage);

            return;
        }

        if ($assistantMessage) {
            $chatStreamPublisher->completed($conversation, $assistantMessage);
        }
    }

    public function failed(Throwable $exception): void
    {
        Message::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('conversation_id', $this->conversationId)
            ->whereKey($this->messageId)
            ->whereIn('status', ['pending', 'streaming'])
            ->update([
                'error_code' => 'AI_PROCESSING_FAILED',
                'status' => 'failed',
                'updated_at' => now(),
            ]);

        Log::warning('ai.chat.message_processing_failed', [
            'conversation_id' => $this->conversationId,
            'exception_class' => class_basename($exception),
            'message_id' => $this->messageId,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    private function conversationLockKey(): string
    {
        return "ai-conversation:{$this->conversationId}";
    }
}
