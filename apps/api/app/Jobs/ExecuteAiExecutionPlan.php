<?php

namespace App\Jobs;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Events\Realtime\ChatStreamed;
use App\Models\AiExecutionPlan;
use App\Models\AiExecutionPlanItem;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\User;
use App\Services\WorkspaceContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Executes an already-confirmed plan in bounded sequential blocks. It never
 * interprets user text; each item delegates to the canonical ToolExecutor.
 */
final class ExecuteAiExecutionPlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $executionPlanId,
        public string $workspaceId,
        public string $userId,
    ) {
    }

    public function handle(
        ToolExecutor $toolExecutor,
        AssistantMessageWriter $assistantMessageWriter,
        WorkspaceContextService $workspaceContext,
    ): void
    {
        $workspace = Workspace::query()->find($this->workspaceId);
        $user = User::query()->find($this->userId);
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('user_id', $this->userId)
            ->where('status', 'active')
            ->first();
        if (!$workspace || !$user || !$membership) {
            $this->failPlan('context_not_available');

            return;
        }

        $workspaceContext->within($workspace, $membership, function () use ($assistantMessageWriter, $membership, $toolExecutor, $user, $workspace): void {
            $planContext = AiExecutionPlan::query()
                ->with('confirmation.message.conversation')
                ->find($this->executionPlanId);
            $sourceMessage = $planContext?->confirmation?->message;
            $toolExecutor->activateExecutionPlanDependencies($this->executionPlanId, $this->workspaceId, [
                'conversation' => $sourceMessage?->conversation,
                'entity_refs' => [],
                'locale' => $sourceMessage?->locale ?? 'en',
                'membership' => $membership,
                'source_message' => $sourceMessage,
                'tool_loop' => true,
                'user' => $user,
                'user_message' => $sourceMessage,
                'workspace' => $workspace,
            ]);
            $itemIds = $this->claimNextBlock();
            if ($itemIds === []) {
                $state = $this->finalizeBlock();
                $plan = AiExecutionPlan::query()
                    ->with(['confirmation.message.conversation', 'progressMessage', 'workspace'])
                    ->find($this->executionPlanId);
                if ($plan) {
                    $this->writeProgressMessage($assistantMessageWriter, $toolExecutor, $plan);
                }
                if (!$state['terminal']) {
                    self::dispatch($this->executionPlanId, $this->workspaceId, $this->userId)->delay(now()->addSecond());
                }
                return;
            }

            $plan = AiExecutionPlan::query()
                ->with(['confirmation.message.conversation', 'progressMessage', 'workspace'])
                ->find($this->executionPlanId);
            if ($plan) {
                $this->writeProgressMessage($assistantMessageWriter, $toolExecutor, $plan);
            }

            foreach ($itemIds as $itemId) {
                $item = AiExecutionPlanItem::query()
                    ->with(['confirmation.message.conversation', 'plan'])
                    ->find($itemId);
                if (!$item || !$item->plan || !in_array($item->plan->status, ['queued', 'running'], true)) {
                    continue;
                }

                try {
                    $confirmation = $item->confirmation;
                    if (!$confirmation || !$confirmation->message?->conversation) {
                        throw new \RuntimeException('The plan item confirmation context is unavailable.');
                    }
                    $result = $toolExecutor->executeExecutionPlanItem($item, [
                        'conversation' => $confirmation->message->conversation,
                        'entity_refs' => [],
                        'locale' => $confirmation->message->locale ?? 'en',
                        'membership' => $membership,
                        'tool_loop' => true,
                        'user' => $user,
                        'user_message' => $confirmation->message,
                        'workspace' => $workspace,
                    ]);
                    $item->forceFill([
                        'completed_at' => now(),
                        'result_ref_json' => $result['result_ref_json'] ?? null,
                        'status' => 'completed',
                    ])->save();
                } catch (\Throwable $exception) {
                    Log::warning('ai.execution_plan.item_failed', [
                        'action_key' => $item?->action_key,
                        'exception_class' => class_basename($exception),
                        'execution_plan_id' => $this->executionPlanId,
                        'item_id' => $itemId,
                        'workspace_id' => $this->workspaceId,
                    ]);
                    AiExecutionPlanItem::query()
                        ->whereKey($itemId)
                        ->update([
                            'completed_at' => now(),
                            'error_code' => $exception instanceof \Illuminate\Validation\ValidationException ? 'VALIDATION_FAILED' : 'EXECUTION_FAILED',
                            'error_message' => $exception->getMessage(),
                            'status' => 'failed',
                            'updated_at' => now(),
                        ]);
                }
            }

            $state = $this->finalizeBlock();
            $plan = AiExecutionPlan::query()
                ->with(['confirmation.message.conversation', 'progressMessage', 'workspace'])
                ->find($this->executionPlanId);
            if ($plan) {
                $this->writeProgressMessage($assistantMessageWriter, $toolExecutor, $plan);
            }

            if (!$state['terminal']) {
                self::dispatch($this->executionPlanId, $this->workspaceId, $this->userId);
            }
        });
    }

    /** @return array<int, string> */
    private function claimNextBlock(): array
    {
        return DB::transaction(function (): array {
            $plan = AiExecutionPlan::query()
                ->whereKey($this->executionPlanId)
                ->where('workspace_id', $this->workspaceId)
                ->lockForUpdate()
                ->first();
            if (!$plan || !in_array($plan->status, ['queued', 'running'], true)) {
                return [];
            }

            $items = $plan->items()
                ->where('status', 'queued')
                ->orderBy('position')
                ->lockForUpdate()
                ->limit($plan->block_size)
                ->get();
            if ($items->isEmpty()) {
                return [];
            }

            $now = now();
            foreach ($items as $item) {
                $item->forceFill([
                    'attempts' => $item->attempts + 1,
                    'started_at' => $item->started_at ?? $now,
                    'status' => 'running',
                ])->save();
            }
            $plan->forceFill([
                'started_at' => $plan->started_at ?? $now,
                'status' => 'running',
            ])->save();

            return $items->pluck('id')->all();
        });
    }

    /** @return array{terminal: bool} */
    private function finalizeBlock(): array
    {
        return DB::transaction(function (): array {
            $plan = AiExecutionPlan::query()
                ->whereKey($this->executionPlanId)
                ->where('workspace_id', $this->workspaceId)
                ->lockForUpdate()
                ->first();
            if (!$plan || $plan->status === 'cancelled') {
                return ['terminal' => true];
            }

            $completed = $plan->items()->where('status', 'completed')->count();
            $failed = $plan->items()->where('status', 'failed')->count();
            $needsReview = $plan->items()->where('status', 'needs_review')->count();
            $remaining = $plan->items()->whereIn('status', ['previewed', 'ready', 'waiting', 'preparing', 'queued', 'running'])->count();
            $status = 'running';
            if ($remaining === 0) {
                $status = $failed > 0 || $needsReview > 0 ? 'partial' : 'completed';
            }

            $plan->forceFill([
                'completed_count' => $completed,
                'failed_count' => $failed,
                'needs_review_count' => $needsReview,
                'finished_at' => $remaining === 0 ? now() : null,
                'status' => $status,
            ])->save();

            Log::info('ai.execution_plan.progressed', [
                'completed_count' => $completed,
                'execution_plan_id' => $plan->id,
                'failed_count' => $failed,
                'item_count' => $plan->item_count,
                'needs_review_count' => $needsReview,
                'status' => $status,
                'workspace_id' => $plan->workspace_id,
            ]);

            return ['terminal' => $remaining === 0];
        });
    }

    private function failPlan(string $reason): void
    {
        AiExecutionPlan::query()
            ->whereKey($this->executionPlanId)
            ->where('workspace_id', $this->workspaceId)
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'finished_at' => now(),
                'status' => 'failed',
                'updated_at' => now(),
            ]);
        Log::warning('ai.execution_plan.failed', [
            'execution_plan_id' => $this->executionPlanId,
            'reason' => $reason,
            'workspace_id' => $this->workspaceId,
        ]);
    }

    private function writeProgressMessage(AssistantMessageWriter $assistantMessageWriter, ToolExecutor $toolExecutor, AiExecutionPlan $plan): void
    {
        $conversation = $plan->confirmation?->message?->conversation;
        $workspace = $plan->workspace;
        if (!$conversation || !$workspace) {
            return;
        }

        $snapshot = $toolExecutor->executionPlanSnapshot($plan->fresh());
        $status = $plan->status === 'completed' ? 'success' : ($plan->status === 'partial' ? 'partial' : 'pending');
        $payload = [
            'blocks' => [[
                'component' => 'execution.plan',
                'data' => [
                    'description' => 'The queue continues automatically. Completed steps are never repeated and blocked dependencies remain visible for review.',
                    'execution_plan' => $snapshot,
                    'status' => $status,
                    'title' => $plan->status === 'completed' || $plan->status === 'partial'
                        ? 'Execution workflow finished'
                        : 'Execution workflow in progress',
                ],
                'schema_version' => 1,
                'type' => 'component',
            ]],
            'suggestions' => [],
        ];
        $message = $plan->progressMessage;
        if ($message) {
            $assistantMessageWriter->complete($message, $workspace, $payload, $plan->confirmation?->message?->locale, [
                'source' => 'execution-plan-progress',
            ]);
        } else {
            $message = $assistantMessageWriter->create(
                $conversation,
                $workspace,
                $plan->confirmation?->message?->locale,
                $payload,
                $plan->confirmation?->message,
                ['source' => 'execution-plan-progress'],
            );
            $plan->forceFill(['progress_message_id' => $message->id])->save();
        }

        ChatStreamed::dispatch($conversation->id, $message->id, 'execution_plan.updated', [
            'executionPlan' => $snapshot,
        ]);
    }
}
