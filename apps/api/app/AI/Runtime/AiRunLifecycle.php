<?php

namespace App\AI\Runtime;

use App\Events\Realtime\ChatStreamed;
use App\Models\AiRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AiRunLifecycle
{
    public const ACTIVE_STATUSES = ['queued', 'running'];

    public const PAUSED_STATUSES = ['waiting_user', 'waiting_confirmation'];

    public const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

    /** @var array<string, array<int, string>> */
    private const TRANSITIONS = [
        'pending' => ['running', 'cancelled', 'failed'],
        'queued' => ['running', 'cancelled', 'failed'],
        'running' => ['waiting_user', 'waiting_confirmation', 'completed', 'failed', 'cancelled'],
        'waiting_user' => ['running', 'cancelled', 'failed'],
        'waiting_confirmation' => ['running', 'cancelled', 'failed'],
        'failed' => ['queued'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function transition(
        AiRun|string $run,
        string $status,
        ?string $stage = null,
        ?int $current = null,
        ?int $total = null,
        array $progressMeta = [],
        array $attributes = [],
    ): AiRun {
        $runId = $run instanceof AiRun ? (string) $run->id : $run;

        $updated = DB::transaction(function () use ($attributes, $current, $progressMeta, $runId, $stage, $status, $total): AiRun {
            $locked = AiRun::query()->lockForUpdate()->findOrFail($runId);
            $from = (string) $locked->status;

            if ($from !== $status && ! in_array($status, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["AI Run cannot transition from {$from} to {$status}."],
                ]);
            }

            $now = now();
            $metadata = is_array($locked->metadata) ? $locked->metadata : [];
            $incomingMetadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
            $metrics = is_array($metadata['runtime_metrics'] ?? null) ? $metadata['runtime_metrics'] : [];
            $metrics['progress_event_count'] = ((int) ($metrics['progress_event_count'] ?? 0)) + 1;
            $metrics['realtime_delivery_count'] = ((int) ($metrics['realtime_delivery_count'] ?? 0)) + 1;
            if ($status === 'running' && ! isset($metrics['queue_wait_ms']) && $locked->queued_at) {
                $metrics['queue_wait_ms'] = max(0, $locked->queued_at->diffInMilliseconds($now));
            }
            if (in_array($status, self::TERMINAL_STATUSES, true) && $locked->started_at) {
                $metrics['ai_run_duration_ms'] = max(0, $locked->started_at->diffInMilliseconds($now));
            }
            unset($attributes['metadata']);
            $values = [
                ...$attributes,
                'status' => $status,
                'current_stage' => $stage ?? $locked->current_stage,
                'progress_current' => $current ?? $locked->progress_current,
                'progress_total' => $total ?? $locked->progress_total,
                'progress_meta' => $progressMeta !== [] ? $progressMeta : $locked->progress_meta,
                'sequence' => ((int) $locked->sequence) + 1,
                'metadata' => [
                    ...$metadata,
                    ...$incomingMetadata,
                    'runtime_metrics' => $metrics,
                ],
            ];

            if ($status === 'running' && ! $locked->started_at) {
                $values['started_at'] = $now;
            }
            if ($status === 'failed') {
                $values['failed_at'] = $now;
                $values['completed_at'] = $now;
            } elseif (in_array($status, ['completed', 'cancelled'], true)) {
                $values['completed_at'] = $now;
            }

            $locked->forceFill($values)->save();

            return $locked->fresh(['assistantMessage.blocks', 'executionPlan']);
        });

        $this->publish($updated);

        return $updated;
    }

    public function progressByAssistantMessage(
        string $assistantMessageId,
        string $stage,
        ?int $current = null,
        ?int $total = null,
        array $progressMeta = [],
    ): ?AiRun {
        $run = AiRun::query()
            ->where('message_id', $assistantMessageId)
            ->whereIn('status', [...self::ACTIVE_STATUSES, ...self::PAUSED_STATUSES, 'pending'])
            ->latest('created_at')
            ->first();

        if (! $run) {
            return null;
        }

        return $this->transition(
            $run,
            (string) $run->status,
            $stage,
            $current,
            $total,
            $progressMeta,
        );
    }

    /** @return array<string, mixed> */
    public function snapshot(AiRun $run): array
    {
        return [
            'id' => (string) $run->id,
            'workspace_id' => (string) $run->workspace_id,
            'conversation_id' => $run->conversation_id ? (string) $run->conversation_id : null,
            'user_message_id' => $run->input_message_id ? (string) $run->input_message_id : null,
            'assistant_message_id' => $run->message_id ? (string) $run->message_id : null,
            'execution_plan_id' => $run->execution_plan_id ? (string) $run->execution_plan_id : null,
            'provider' => $run->provider,
            'model' => $run->model_key,
            'status' => (string) $run->status,
            'stage' => $run->current_stage,
            'progress' => [
                'current' => $run->progress_current,
                'total' => $run->progress_total,
                'meta' => is_array($run->progress_meta) ? $run->progress_meta : [],
            ],
            'sequence' => (int) $run->sequence,
            'retry_count' => (int) $run->infrastructure_retry_count,
            'queued_at' => $run->queued_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'error_code' => $run->error_code,
            'error_message_safe' => $run->error_code
                ? 'Humoo could not complete this request. You can safely retry it.'
                : null,
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }

    private function publish(AiRun $run): void
    {
        if (! $run->conversation_id || ! $run->message_id) {
            return;
        }

        ChatStreamed::dispatch(
            (string) $run->conversation_id,
            (string) $run->message_id,
            'ai_run.updated',
            ['aiRun' => $this->snapshot($run)],
        );

        Log::info('ai.run.state_changed', [
            'ai_run_id' => $run->id,
            'conversation_id' => $run->conversation_id,
            'sequence' => $run->sequence,
            'stage' => $run->current_stage,
            'status' => $run->status,
            'workspace_id' => $run->workspace_id,
        ]);
    }
}
