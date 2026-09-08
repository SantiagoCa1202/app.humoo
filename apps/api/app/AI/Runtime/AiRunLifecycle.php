<?php

namespace App\AI\Runtime;

use App\AI\Objectives\AiObjectiveLifecycle;
use App\AI\Objectives\ObjectiveValidator;
use App\AI\Streaming\BestEffortChatBroadcaster;
use App\Models\ActionConfirmation;
use App\Models\AiObjective;
use App\Models\AiRun;
use App\Models\Conversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AiRunLifecycle
{
    public function __construct(private BestEffortChatBroadcaster $realtime) {}

    public const ACTIVE_STATUSES = ['queued', 'running', 'retrying'];

    public const PAUSED_STATUSES = ['waiting_user', 'waiting_confirmation', 'paused', 'needs_review'];

    public const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

    /** @var array<string, array<int, string>> */
    private const TRANSITIONS = [
        'pending' => ['running', 'cancelled', 'failed'],
        'queued' => ['running', 'retrying', 'paused', 'cancelled', 'failed'],
        'running' => ['waiting_user', 'waiting_confirmation', 'retrying', 'paused', 'needs_review', 'completed', 'failed', 'cancelled'],
        'retrying' => ['running', 'paused', 'needs_review', 'failed', 'cancelled'],
        'paused' => ['queued', 'running', 'cancelled', 'failed'],
        'needs_review' => ['queued', 'running', 'cancelled', 'failed'],
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

    /**
     * Resume the canonical paused state before terminalizing a confirmed
     * operation. Duplicate deliveries are a no-op once the run is terminal.
     */
    public function resumeAndFinishConfirmation(
        AiRun|string $run,
        string $terminalStatus = 'completed',
        string $runningStage = 'finalizing_confirmation',
    ): AiRun {
        if (! in_array($terminalStatus, self::TERMINAL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['A confirmed run can only finish in a terminal state.'],
            ]);
        }

        $current = $run instanceof AiRun ? $run->fresh() : AiRun::query()->findOrFail($run);
        if (in_array((string) $current->status, self::TERMINAL_STATUSES, true)) {
            return $current;
        }

        if ((string) $current->status !== 'running') {
            $current = $this->transition($current, 'running', $runningStage);
        }

        return $this->transition($current, $terminalStatus, $terminalStatus);
    }

    /**
     * Repair persisted runtime state only. This never executes a capability,
     * creates a message, or changes domain data.
     */
    public function reconcileConversation(Conversation $conversation, string $workspaceId): int
    {
        $reconciled = 0;
        $runs = AiRun::query()
            ->where('workspace_id', $workspaceId)
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [...self::PAUSED_STATUSES, 'running'])
            ->with('executionPlan', 'objective')
            ->get();

        foreach ($runs as $run) {
            $plan = $run->executionPlan;
            $terminal = $plan && in_array((string) $plan->status, ['completed', 'partial', 'failed', 'cancelled'], true)
                ? match ((string) $plan->status) {
                    'failed' => 'failed',
                    'cancelled' => 'cancelled',
                    'partial' => 'needs_review',
                    default => 'completed',
                }
            : null;
            $confirmation = ActionConfirmation::query()
                ->where('workspace_id', $workspaceId)
                ->where('message_id', $run->message_id)
                ->latest('created_at')
                ->first();

            if ($terminal === null && $confirmation?->status === 'executed') {
                $terminal = 'completed';
            } elseif ($terminal === null && $confirmation?->status === 'cancelled') {
                $terminal = 'cancelled';
            } elseif ($terminal === null && $confirmation?->status === 'failed') {
                $terminal = 'failed';
            }

            if ($terminal === null) {
                continue;
            }
            if ($terminal === 'completed') {
                $terminal = $this->verifiedCompletionStatus($run->objective);
            }
            if ($run->status === $terminal) {
                continue;
            }

            $this->resumeAndFinishConfirmation(
                $run,
                $terminal,
                $plan ? 'preparing_execution' : 'finalizing_confirmation',
            );
            $reconciled++;
            Log::info('ai.run.reconciled_state', [
                'ai_run_id' => $run->id,
                'conversation_id' => $conversation->id,
                'execution_plan_id' => $plan?->id,
                'status' => $terminal,
                'workspace_id' => $workspaceId,
            ]);
        }

        return $reconciled;
    }

    public function verifiedCompletionStatus(?AiObjective $objective): string
    {
        if (! $objective || ($objective->operation_count === 0 && ! data_get($objective->metadata_json, 'scope_defined'))) {
            return 'completed';
        }
        $verification = app(ObjectiveValidator::class)->validate($objective);

        return $verification['valid'] ? 'completed'
            : ($verification['canonical_status'] === 'blocked' ? 'waiting_user' : 'needs_review');
    }

    /** @return array<string, mixed> */
    public function snapshot(AiRun $run): array
    {
        $run->loadMissing('objective');

        return [
            'id' => (string) $run->id,
            'workspace_id' => (string) $run->workspace_id,
            'conversation_id' => $run->conversation_id ? (string) $run->conversation_id : null,
            'user_message_id' => $run->input_message_id ? (string) $run->input_message_id : null,
            'assistant_message_id' => $run->message_id ? (string) $run->message_id : null,
            'execution_plan_id' => $run->execution_plan_id ? (string) $run->execution_plan_id : null,
            'objective_id' => $run->objective_id ? (string) $run->objective_id : null,
            'objective' => $run->objective
                ? app(AiObjectiveLifecycle::class)->snapshot($run->objective)
                : null,
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
            'deadline_at' => $run->deadline_at?->toIso8601String(),
            'last_heartbeat_at' => $run->last_heartbeat_at?->toIso8601String(),
            'next_retry_at' => $run->next_retry_at?->toIso8601String(),
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

        $this->realtime->publish(
            (string) $run->conversation_id,
            (string) $run->message_id,
            'ai_run.updated',
            ['aiRun' => $this->realtimeSnapshot($run)],
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

    /**
     * Realtime consumers only need progress and stable references. The full
     * objective remains available from the canonical AI-runs endpoint.
     *
     * @return array<string, mixed>
     */
    private function realtimeSnapshot(AiRun $run): array
    {
        $snapshot = $this->snapshot($run);
        $objective = is_array($snapshot['objective'] ?? null)
            ? $snapshot['objective']
            : null;

        if ($objective !== null) {
            $snapshot['objective'] = array_intersect_key($objective, array_flip([
                'blocked_count',
                'completed_count',
                'error_code',
                'failed_count',
                'id',
                'needs_review_count',
                'operation_count',
                'pending_count',
                'preserved_progress',
                'revision',
                'status',
                'updated_at',
            ]));
        }

        return $snapshot;
    }
}
