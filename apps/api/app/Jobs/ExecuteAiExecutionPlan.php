<?php

namespace App\Jobs;

use App\AI\Errors\ErrorResponseMapper;
use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Events\Realtime\ChatStreamed;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\AiExecutionPlanItem;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Services\WorkspaceContextService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
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
    ) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('ai-execution-plan:'.$this->executionPlanId))
                ->releaseAfter(5)
                ->expireAfter($this->timeout + 30),
        ];
    }

    public function handle(
        ToolExecutor $toolExecutor,
        AssistantMessageWriter $assistantMessageWriter,
        WorkspaceContextService $workspaceContext,
        ?ConversationContinuationLifecycle $continuationLifecycle = null,
    ): void {
        $continuationLifecycle ??= app(ConversationContinuationLifecycle::class);
        $workspace = Workspace::query()->find($this->workspaceId);
        $user = User::query()->find($this->userId);
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('user_id', $this->userId)
            ->where('status', 'active')
            ->first();
        if (! $workspace || ! $user || ! $membership) {
            $this->failPlan('context_not_available');

            return;
        }

        $workspaceContext->within($workspace, $membership, function () use ($assistantMessageWriter, $continuationLifecycle, $membership, $toolExecutor, $user, $workspace): void {
            $planContext = AiExecutionPlan::query()
                ->with('confirmation.message.conversation')
                ->where('workspace_id', $this->workspaceId)
                ->find($this->executionPlanId);
            $trace = $this->traceContext($planContext);
            $this->recoverStalledItems($trace);
            $sourceMessage = $planContext?->confirmation?->message;
            $toolExecutor->activateExecutionPlanDependencies($this->executionPlanId, $this->workspaceId, [
                'correlation_id' => $trace['correlation_id'],
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
                if ($state['terminal'] && $plan) {
                    $this->queueProviderContinuation($continuationLifecycle, $toolExecutor, $plan);
                } elseif (! $state['terminal']) {
                    self::dispatch($this->executionPlanId, $this->workspaceId, $this->userId)->delay(now()->addSeconds(5));
                }

                return;
            }

            $plan = AiExecutionPlan::query()
                ->with(['confirmation.message.conversation', 'progressMessage', 'workspace'])
                ->find($this->executionPlanId);
            if ($plan) {
                $this->writeProgressMessage($assistantMessageWriter, $toolExecutor, $plan);
            }

            $retryDelaySeconds = 0;
            foreach ($itemIds as $itemId) {
                $item = AiExecutionPlanItem::query()
                    ->with(['confirmation.message.conversation', 'plan'])
                    ->find($itemId);
                if (! $item || ! $item->plan || ! in_array($item->plan->status, ['queued', 'running'], true)) {
                    continue;
                }

                try {
                    DB::transaction(function () use ($itemId, $membership, $toolExecutor, $trace, $user, $workspace): void {
                        $item = AiExecutionPlanItem::query()
                            ->with(['confirmation.message.conversation', 'plan'])
                            ->lockForUpdate()
                            ->find($itemId);
                        if (! $item || ! $item->plan || $item->status !== 'running') {
                            return;
                        }

                        $confirmation = $item->confirmation;
                        if (! $confirmation || ! $confirmation->message?->conversation) {
                            throw new \RuntimeException('The plan item confirmation context is unavailable.');
                        }
                        $result = $toolExecutor->executeExecutionPlanItem($item, [
                            'correlation_id' => $trace['correlation_id'],
                            'conversation' => $confirmation->message->conversation,
                            'entity_refs' => [],
                            'execution_plan_id' => $this->executionPlanId,
                            'locale' => $confirmation->message->locale ?? 'en',
                            'membership' => $membership,
                            'operation_id' => $item->id,
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
                    });
                } catch (\Throwable $exception) {
                    $locale = (string) ($item?->confirmation?->message?->locale ?? 'en');
                    $publicError = (new ErrorResponseMapper)->map(
                        $exception,
                        $locale,
                        (string) $trace['correlation_id'],
                    );
                    $retryable = (bool) $publicError['retryable'] && (int) ($item?->attempts ?? 0) < $this->tries;
                    Log::warning('ai.execution_plan.item_failed', [
                        'action_key' => $item?->action_key,
                        ...$trace,
                        'error_code' => $publicError['error_code'],
                        'exception_class' => class_basename($exception),
                        'item_id' => $itemId,
                        'retryable' => $retryable,
                    ]);
                    $this->recordItemFailure($itemId, $publicError, $retryable);
                    if ($retryable) {
                        $retryDelaySeconds = max(
                            $retryDelaySeconds,
                            min(30, 2 ** max(1, (int) ($item?->attempts ?? 1))),
                        );
                    }
                }
            }

            $state = $this->finalizeBlock();
            $plan = AiExecutionPlan::query()
                ->with(['confirmation.message.conversation', 'progressMessage', 'workspace'])
                ->find($this->executionPlanId);
            if ($plan) {
                $this->writeProgressMessage($assistantMessageWriter, $toolExecutor, $plan);
            }

            if ($state['terminal'] && $plan) {
                $this->queueProviderContinuation($continuationLifecycle, $toolExecutor, $plan);
            } elseif (! $state['terminal']) {
                $dispatch = self::dispatch($this->executionPlanId, $this->workspaceId, $this->userId);
                if ($retryDelaySeconds > 0) {
                    $dispatch->delay(now()->addSeconds($retryDelaySeconds));
                }
            }
        });
    }

    private function queueProviderContinuation(
        ConversationContinuationLifecycle $continuationLifecycle,
        ToolExecutor $toolExecutor,
        AiExecutionPlan $plan,
    ): void {
        $plan->loadMissing('confirmation.message.conversation', 'items');
        $confirmation = $plan->confirmation;
        $conversation = $confirmation?->message?->conversation;
        if (!$confirmation || !$conversation) {
            return;
        }

        $metadata = is_array($plan->metadata_json) ? $plan->metadata_json : [];
        if (filled($metadata['provider_continuation_dispatched_at'] ?? null)) {
            return;
        }

        $providerCallId = $continuationLifecycle->pendingProviderToolCallId(
            $conversation,
            (string) $confirmation->id,
        );
        if ($providerCallId === null) {
            return;
        }

        $snapshot = $toolExecutor->executionPlanSnapshot($plan);
        $result = [
            'status' => $plan->status,
            'workflow_status' => $plan->status,
            'tool_keys' => ['execution_plans.create'],
            'entity_refs' => [],
            'result_ref_json' => [
                'execution_plan' => $snapshot,
                'completion_steps' => $snapshot['completion_steps'] ?? [],
            ],
        ];
        if (!$continuationLifecycle->resolvePendingProviderToolCallForConfirmation($confirmation, $result)) {
            return;
        }

        $confirmation->forceFill(['result_ref_json' => $result['result_ref_json']])->save();
        $metadata['provider_continuation_dispatched_at'] = now()->toIso8601String();
        $plan->forceFill(['metadata_json' => $metadata])->save();

        ContinueConfirmedConversation::dispatch(
            (string) $confirmation->id,
            $this->workspaceId,
            $this->userId,
            (string) $conversation->id,
        )->afterCommit();
        Log::info('ai.execution_plan.continuation_queued', [
            ...$this->traceContext($plan),
            'confirmation_id' => $confirmation->id,
            'status' => $plan->status,
        ]);
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
            if (! $plan || ! in_array($plan->status, ['queued', 'running'], true)) {
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
            if (! $plan || $plan->status === 'cancelled') {
                return ['terminal' => true];
            }

            $counts = $plan->items()
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count")
                ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count")
                ->selectRaw("SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END) AS needs_review_count")
                ->selectRaw("SUM(CASE WHEN status IN ('previewed', 'ready', 'waiting', 'preparing', 'queued', 'running') THEN 1 ELSE 0 END) AS remaining_count")
                ->first();
            $completed = (int) ($counts?->completed_count ?? 0);
            $failed = (int) ($counts?->failed_count ?? 0);
            $needsReview = (int) ($counts?->needs_review_count ?? 0);
            $remaining = (int) ($counts?->remaining_count ?? 0);
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
                ...$this->traceContext($plan),
                'failed_count' => $failed,
                'item_count' => $plan->item_count,
                'needs_review_count' => $needsReview,
                'status' => $status,
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
            ...$this->traceContext(),
            'reason' => $reason,
        ]);
    }

    /** @param array<string, mixed> $trace */
    private function recoverStalledItems(array $trace): void
    {
        DB::transaction(function () use ($trace): void {
            $stalled = AiExecutionPlanItem::query()
                ->where('execution_plan_id', $this->executionPlanId)
                ->whereHas('plan', fn ($query) => $query->where('workspace_id', $this->workspaceId))
                ->where('status', 'running')
                ->where('started_at', '<=', now()->subSeconds($this->timeout + 30))
                ->with('confirmation')
                ->lockForUpdate()
                ->get();

            foreach ($stalled as $item) {
                $retryable = $item->attempts < $this->tries
                    && $item->confirmation?->status === 'pending';
                $uncertain = ! $retryable && $item->confirmation?->status === 'confirmed';
                $item->forceFill([
                    'completed_at' => $retryable ? null : now(),
                    'error_code' => $retryable
                        ? null
                        : ($uncertain ? 'RECOVERY_STATE_UNCERTAIN' : 'WORKFLOW_RETRY_EXHAUSTED'),
                    'error_message' => $retryable
                        ? null
                        : ($uncertain
                            ? 'This interrupted step needs review before it can be retried safely.'
                            : 'This step could not be completed after bounded retries.'),
                    'started_at' => null,
                    'status' => $retryable ? 'queued' : ($uncertain ? 'needs_review' : 'failed'),
                ])->save();

                Log::warning($retryable
                    ? 'ai.execution_plan.stalled_item_recovered'
                    : ($uncertain
                        ? 'ai.execution_plan.stalled_item_needs_review'
                        : 'ai.execution_plan.stalled_item_exhausted'), [
                            ...$trace,
                            'attempts' => $item->attempts,
                            'item_id' => $item->id,
                        ]);
            }
        });
    }

    /** @param array<string, mixed> $publicError */
    private function recordItemFailure(string $itemId, array $publicError, bool $retryable): void
    {
        DB::transaction(function () use ($itemId, $publicError, $retryable): void {
            $item = AiExecutionPlanItem::query()
                ->whereHas('plan', fn ($query) => $query->where('workspace_id', $this->workspaceId))
                ->lockForUpdate()
                ->find($itemId);
            if (! $item) {
                return;
            }

            ActionConfirmation::query()
                ->whereKey($item->action_confirmation_id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->update([
                    'error_code' => $retryable ? null : $publicError['error_code'],
                    'error_message' => $retryable ? null : $publicError['message'],
                    'status' => $retryable ? 'pending' : 'failed',
                    'updated_at' => now(),
                ]);
            $item->forceFill([
                'completed_at' => $retryable ? null : now(),
                'error_code' => $retryable ? null : $publicError['error_code'],
                'error_message' => $retryable ? null : $publicError['message'],
                'started_at' => null,
                'status' => $retryable ? 'queued' : 'failed',
            ])->save();
        });
    }

    /** @return array{correlation_id:?string,execution_plan_id:string,workflow_id:string,workspace_id:string} */
    private function traceContext(?AiExecutionPlan $plan = null): array
    {
        $metadata = is_array($plan?->metadata_json) ? $plan->metadata_json : [];

        return [
            'correlation_id' => is_string($metadata['correlation_id'] ?? null)
                ? $metadata['correlation_id']
                : null,
            'execution_plan_id' => $this->executionPlanId,
            'workflow_id' => $this->executionPlanId,
            'workspace_id' => $this->workspaceId,
        ];
    }

    private function writeProgressMessage(AssistantMessageWriter $assistantMessageWriter, ToolExecutor $toolExecutor, AiExecutionPlan $plan): void
    {
        $conversation = $plan->confirmation?->message?->conversation;
        $workspace = $plan->workspace;
        if (! $conversation || ! $workspace) {
            return;
        }

        $snapshot = $toolExecutor->executionPlanSnapshot($plan->fresh());
        $status = $plan->status === 'completed' ? 'success' : ($plan->status === 'partial' ? 'partial' : 'pending');
        $payload = [
            'blocks' => [[
                'actions' => $toolExecutor->executionPlanComponentActions($plan),
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
            'executionPlan' => $this->executionPlanBroadcastSnapshot($snapshot),
        ]);
    }

    /**
     * Realtime only needs stable identities and progress. Full previews and
     * result references remain persisted in the message/plan and are fetched
     * after a terminal event; broadcasting them can exceed provider limits.
     *
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function executionPlanBroadcastSnapshot(array $snapshot): array
    {
        $steps = collect($snapshot['steps'] ?? [])
            ->filter(fn (mixed $step): bool => is_array($step))
            ->map(fn (array $step): array => array_intersect_key($step, array_flip([
                'action_key',
                'error_code',
                'id',
                'status',
                'step_key',
            ])))
            ->values()
            ->all();
        $completionSteps = collect($snapshot['completion_steps'] ?? [])
            ->filter(fn (mixed $step): bool => is_array($step))
            ->map(fn (array $step): array => array_intersect_key($step, array_flip([
                'action_key',
                'status',
                'step_key',
            ])))
            ->values()
            ->all();

        return [
            ...array_intersect_key($snapshot, array_flip([
                'completed_count',
                'current_operation',
                'failed_count',
                'id',
                'item_count',
                'needs_review_count',
                'revision',
                'status',
                'title',
            ])),
            'completion_steps' => $completionSteps,
            'steps' => $steps,
        ];
    }
}
