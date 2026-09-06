<?php

namespace App\Jobs;

use App\AI\Orchestration\AIOrchestrator;
use App\AI\Runtime\DurableRecoveryNotice;
use App\AI\Runtime\AiRunLifecycle;
use App\AI\Streaming\ChatStreamPublisher;
use App\Models\Conversation;
use App\Models\AiRun;
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

    public int $timeout = 540;

    public int $uniqueFor = 900;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $conversationId,
        public string $workspaceId,
        public string $userId,
        public string $messageId,
        public ?string $aiRunId = null,
    ) {
        $this->timeout = max(60, (int) config('ai.deadlines.run_seconds', 540));
        $this->uniqueFor = $this->timeout + 300;
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
        AiRunLifecycle $aiRunLifecycle,
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
        $aiRun = $this->aiRunId
            ? AiRun::query()->where('workspace_id', $this->workspaceId)->find($this->aiRunId)
            : AiRun::query()
                ->where('workspace_id', $this->workspaceId)
                ->where('input_message_id', $this->messageId)
                ->latest('created_at')
                ->first();

        if (! $message || ! $conversation || ! $workspace || ! $user || ! $membership || ($this->aiRunId && ! $aiRun)) {
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
            if ($aiRun && ! in_array($aiRun->status, AiRunLifecycle::TERMINAL_STATUSES, true)) {
                $aiRunLifecycle->transition($aiRun, 'failed', 'failed', attributes: [
                    'error_code' => 'AI_PROCESSING_UNAVAILABLE',
                    'error_message' => 'AI processing is temporarily unavailable.',
                ]);
            }

            return;
        }

        if ($aiRun && in_array($aiRun->status, [...AiRunLifecycle::TERMINAL_STATUSES, ...AiRunLifecycle::PAUSED_STATUSES], true)) {
            Log::info('duplicate_ai_run_execution_prevented', [
                'ai_run_id' => $aiRun->id,
                'message_id' => $this->messageId,
                'status' => $aiRun->status,
                'workspace_id' => $this->workspaceId,
            ]);

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

        if ($aiRun) {
            $retryCount = max((int) $aiRun->infrastructure_retry_count, max(0, $this->attempts() - 1));
            $aiRun = $aiRunLifecycle->transition($aiRun, 'running', 'analyzing', attributes: [
                'attempt' => $this->attempts(),
                'infrastructure_retry_count' => $retryCount,
            ]);
        }

        $assistantMessage = $workspaceContext->within($workspace, $membership, function () use ($aiOrchestrator, $aiRun, $conversation, $membership, $message, $user, $workspace): Message {
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
                $aiRun?->assistantMessage,
                $aiRun,
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
        $inputMessage = Message::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('conversation_id', $this->conversationId)
            ->whereKey($this->messageId)
            ->first();

        $run = $this->aiRunId
            ? AiRun::query()->where('workspace_id', $this->workspaceId)->find($this->aiRunId)
            : AiRun::query()->where('workspace_id', $this->workspaceId)
                ->where('input_message_id', $this->messageId)->latest('created_at')->first();
        if ($run && ! in_array($run->status, AiRunLifecycle::TERMINAL_STATUSES, true)) {
            app(DurableRecoveryNotice::class)->record(
                $run,
                $exception,
                $inputMessage?->locale,
                str_contains(class_basename($exception), 'Timeout')
                    ? 'RUN_DEADLINE_EXCEEDED'
                    : 'WORKFLOW_RETRY_EXHAUSTED',
            );
            $inputMessage?->forceFill(['error_code' => null, 'status' => 'completed'])->save();
        } else {
            $inputMessage?->forceFill([
                'error_code' => 'AI_PROCESSING_FAILED',
                'status' => 'failed',
            ])->save();
        }

        Log::warning('ai.chat.message_processing_failed', [
            'conversation_id' => $this->conversationId,
            'exception_class' => class_basename($exception),
            'message_id' => $this->messageId,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    private function conversationLockKey(): string
    {
        return $this->aiRunId
            ? "ai-run:{$this->aiRunId}"
            : "ai-conversation:{$this->conversationId}";
    }
}
