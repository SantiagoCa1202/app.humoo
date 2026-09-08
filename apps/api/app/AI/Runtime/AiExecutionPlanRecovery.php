<?php

namespace App\AI\Runtime;

use App\AI\Objectives\AiObjectiveLifecycle;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\AiExecutionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class AiExecutionPlanRecovery
{
    public function __construct(
        private AiObjectiveLifecycle $objectives,
        private AiRunLifecycle $runs,
    ) {}

    /**
     * Resume a plan whose queue job stopped without leaving any operation in
     * an uncertain state. Completed items remain immutable and are not retried.
     */
    public function resumeUncertainFree(string $planId): AiExecutionPlan
    {
        $plan = DB::transaction(function () use ($planId): AiExecutionPlan {
            $plan = AiExecutionPlan::query()
                ->with('aiRun')
                ->lockForUpdate()
                ->findOrFail($planId);

            if (! in_array((string) $plan->status, ['queued', 'partial', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'execution_plan' => ['Only a stopped execution plan can be resumed.'],
                ]);
            }

            $unsafeItems = $plan->items()
                ->whereIn('status', ['running', 'failed', 'needs_review'])
                ->count();
            $queuedItems = $plan->items()->where('status', 'queued')->count();
            if ($unsafeItems > 0 || $queuedItems === 0) {
                throw ValidationException::withMessages([
                    'execution_plan' => ['This plan has no safely resumable queued work. Review its unresolved operations first.'],
                ]);
            }

            $plan->forceFill([
                'completed_count' => $plan->items()->where('status', 'completed')->count(),
                'failed_count' => 0,
                'finished_at' => null,
                'needs_review_count' => 0,
                'status' => 'queued',
            ])->save();

            return $plan->fresh(['aiRun', 'items', 'objectiveRecord']);
        });

        $this->objectives->syncPlan($plan);
        $run = $plan->aiRun?->fresh();
        if ($run && ! in_array((string) $run->status, ['completed', 'cancelled'], true)) {
            $targetStatus = (string) $run->status === 'running' ? 'running' : 'queued';
            $this->runs->transition(
                $run,
                $targetStatus,
                'preparing_execution',
                (int) $plan->completed_count,
                (int) $plan->item_count,
                ['execution_plan_status' => 'queued'],
                [
                    'error_code' => null,
                    'error_message' => null,
                    'next_retry_at' => null,
                ],
            );
        }

        ExecuteAiExecutionPlan::dispatch(
            (string) $plan->id,
            (string) $plan->workspace_id,
            (string) $plan->created_by,
        );

        Log::info('ai.execution_plan.resumed', [
            'completed_count' => $plan->completed_count,
            'execution_plan_id' => $plan->id,
            'item_count' => $plan->item_count,
            'workspace_id' => $plan->workspace_id,
        ]);

        return $plan->fresh(['aiRun', 'items', 'objectiveRecord']);
    }
}
