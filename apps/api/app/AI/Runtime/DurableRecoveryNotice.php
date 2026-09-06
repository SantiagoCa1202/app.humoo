<?php

namespace App\AI\Runtime;

use App\AI\Errors\ErrorResponseMapper;
use App\AI\Exceptions\AiProviderTimeoutException;
use App\AI\Exceptions\AiRuntimeException;
use App\AI\Objectives\AiObjectiveLifecycle;
use App\AI\Streaming\ChatStreamPublisher;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Models\AiRun;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DurableRecoveryNotice
{
    public function __construct(
        private AssistantMessageWriter $messageWriter,
        private ChatStreamPublisher $streamPublisher,
        private AiRunLifecycle $runLifecycle,
        private AiObjectiveLifecycle $objectiveLifecycle,
    ) {}

    public function record(
        AiRun $run,
        Throwable $exception,
        ?string $locale = null,
        ?string $runtimeCode = null,
    ): ?Message
    {
        $run->loadMissing(['assistantMessage', 'conversation', 'inputMessage', 'objective']);
        $conversation = $run->conversation;
        $workspace = Workspace::query()->find($run->workspace_id);
        if (! $conversation || ! $workspace) {
            return null;
        }

        $mappedException = $runtimeCode !== null
            ? $this->runtimeException($runtimeCode, $exception)
            : (str_contains(class_basename($exception), 'Timeout')
                ? new AiProviderTimeoutException('The durable AI job exceeded its execution deadline.', [], $exception)
                : $exception);
        $objective = $run->objective;
        $publicError = (new ErrorResponseMapper)->map(
            $mappedException,
            $locale ?? $run->inputMessage?->locale ?? 'en',
            (string) data_get($run->metadata, 'correlation_id', $run->id),
            [
                'ai_run_id' => (string) $run->id,
                'objective_id' => $objective?->id,
                'preserved_progress' => $objective !== null,
                'completed_count' => (int) ($objective?->completed_count ?? $run->progress_current ?? 0),
                'pending_count' => (int) ($objective?->pending_count ?? 0),
            ],
        );

        if ($objective) {
            $objective = $this->objectiveLifecycle->pause($objective, $publicError);
        }

        $freshRun = $run->fresh();
        if (! in_array((string) $freshRun->status, AiRunLifecycle::TERMINAL_STATUSES, true)) {
            if ((string) $freshRun->status !== 'running') {
                $freshRun = $this->runLifecycle->transition($freshRun, 'running', 'recovering');
            }
            $this->runLifecycle->transition(
                $freshRun,
                $publicError['category'] === 'transient' ? 'paused' : 'needs_review',
                $publicError['category'] === 'transient' ? 'paused' : 'needs_review',
                attributes: [
                    'error_code' => $publicError['error_code'],
                    'error_message' => $publicError['message'],
                    'next_retry_at' => filled($publicError['retry_after_seconds'] ?? null)
                        ? now()->addSeconds((int) $publicError['retry_after_seconds'])
                        : null,
                ],
            );
        }

        $payload = [
            'blocks' => [[
                'component' => 'error.recovery',
                'data' => [
                    ...$publicError,
                    'objective' => $objective ? $this->objectiveLifecycle->snapshot($objective) : null,
                ],
                'schema_version' => 1,
                'type' => 'component',
            ]],
            'suggestions' => [],
        ];
        $assistantMessage = $run->assistantMessage;
        if (! $assistantMessage || ! in_array((string) $assistantMessage->status, ['pending', 'streaming'], true)) {
            $assistantMessage = $this->messageWriter->createPending(
                $conversation,
                $workspace,
                $locale ?? $run->inputMessage?->locale,
                $run->inputMessage,
                ['source' => 'durable-recovery'],
            );
        }
        $assistantMessage = $this->messageWriter->fail(
            $assistantMessage,
            $workspace,
            (string) $publicError['error_code'],
            (string) $publicError['message'],
            $payload,
            $locale ?? $run->inputMessage?->locale,
            [
                'source' => 'durable-recovery',
                'ai_run_id' => (string) $run->id,
                'objective_id' => $objective?->id,
            ],
        );
        $this->streamPublisher->failed($conversation, $assistantMessage);

        Log::warning('user_recovery_notice.created', [
            'ai_run_id' => $run->id,
            'conversation_id' => $conversation->id,
            'error_code' => $publicError['error_code'],
            'message_id' => $assistantMessage->id,
            'objective_id' => $objective?->id,
            'workspace_id' => $workspace->id,
        ]);

        return $assistantMessage;
    }

    private function runtimeException(string $code, Throwable $previous): AiRuntimeException
    {
        return match ($code) {
            'RUN_DEADLINE_EXCEEDED' => new AiRuntimeException($code, 'run_deadline_exceeded', true, 'The AI run deadline was exceeded.', $previous),
            'TOOL_TIMEOUT' => new AiRuntimeException($code, 'tool_timeout', true, 'A tool timed out.', $previous),
            'WORKFLOW_RETRY_EXHAUSTED' => new AiRuntimeException($code, 'workflow_retry_exhausted', true, 'The workflow retry budget was exhausted.', $previous),
            'RECOVERY_STATE_UNCERTAIN' => new AiRuntimeException($code, 'recovery_state_uncertain', false, 'The interrupted state requires review.', $previous),
            default => new AiRuntimeException($code, 'internal_error', false, 'The AI runtime requires review.', $previous),
        };
    }
}
