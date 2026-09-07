<?php

namespace App\Jobs;

use App\AI\Orchestration\AIOrchestrator;
use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\AI\Runtime\DurableRecoveryNotice;
use App\Models\ActionConfirmation;
use App\Models\AiRun;
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
use Illuminate\Support\Facades\Log;
use Throwable;

final class ContinueConfirmedConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 540;

    public bool $failOnTimeout = true;

    public function __construct(
        public string $confirmationId,
        public string $workspaceId,
        public string $userId,
        public ?string $conversationId = null,
    ) {
        $this->timeout = max(60, (int) config('ai.deadlines.continuation_seconds', 540));
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("ai-conversation:{$this->conversationId}"))
                ->shared()
                ->releaseAfter(3)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(
        AIOrchestrator $aiOrchestrator,
        ConversationContinuationLifecycle $continuationLifecycle,
        WorkspaceContextService $workspaceContext,
    ): void {
        $confirmation = ActionConfirmation::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereKey($this->confirmationId)
            ->with('message.conversation')
            ->first();
        $workspace = Workspace::query()->find($this->workspaceId);
        $user = User::query()->find($this->userId);
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('user_id', $this->userId)
            ->where('status', 'active')
            ->first();

        if (!$confirmation || !$workspace || !$user || !$membership) {
            Log::warning('ai.confirmation.continuation_skipped', [
                'confirmation_id' => $this->confirmationId,
                'workspace_id' => $this->workspaceId,
                'reason' => 'context_not_available',
            ]);

            return;
        }

        $conversation = $confirmation->message?->conversation;
        if (!$conversation || ($continuationLifecycle->pendingProviderToolOutputs($conversation) === []
            && ! data_get($conversation->metadata, 'provider_recovery.pending'))) {
            return;
        }

        $workspaceContext->within($workspace, $membership, function () use ($aiOrchestrator, $confirmation, $membership, $user, $workspace): void {
            try {
                $resultRef = is_array($confirmation->result_ref_json) ? $confirmation->result_ref_json : [];
                $canonicalStatus = (string) (data_get($resultRef, 'objective.status') ?: 'completed');
                $aiOrchestrator->continueConfirmedConversation(
                    $confirmation,
                    [
                        'status' => $canonicalStatus,
                        'workflow_status' => $canonicalStatus,
                        'tool_keys' => [$confirmation->action_key],
                        'entity_refs' => $this->confirmedEntityRefs($confirmation),
                        'result_ref_json' => $resultRef,
                    ],
                    $workspace,
                    $membership,
                    $user,
                );
            } catch (\Throwable $exception) {
                Log::warning('ai.confirmation.continuation_failed', [
                    'confirmation_id' => $this->confirmationId,
                    'exception_class' => class_basename($exception),
                    'workspace_id' => $this->workspaceId,
                ]);

                throw $exception;
            }
        });
        $conversation->refresh();
        if (data_get($conversation->metadata, 'provider_recovery.pending') && $this->attempts() < $this->tries) {
            $delay = max(1, (int) ceil(now()->diffInSeconds(\Illuminate\Support\Carbon::parse(data_get($conversation->metadata, 'provider_recovery.retry_at')), false)));
            $this->release($delay);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function confirmedEntityRefs(ActionConfirmation $confirmation): array
    {
        if (!in_array($confirmation->action_key, ['recipes.create', 'recipes.update', 'recipes.edit', 'recipes.duplicate'], true)) {
            return [];
        }

        $recipe = is_array($confirmation->result_ref_json) ? $confirmation->result_ref_json : [];
        if (!filled($recipe['id'] ?? null)) {
            return [];
        }

        return [[
            'id' => $recipe['id'],
            'role' => 'active',
            'snapshot' => $recipe,
            'type' => 'recipe',
            'version' => $recipe['current_version'] ?? null,
        ]];
    }

    public function failed(Throwable $exception): void
    {
        $confirmation = ActionConfirmation::query()
            ->where('workspace_id', $this->workspaceId)
            ->whereKey($this->confirmationId)
            ->with('message')
            ->first();
        $run = AiRun::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('conversation_id', $this->conversationId)
            ->when($confirmation?->objective_id, fn ($query, $objectiveId) => $query->where('objective_id', $objectiveId))
            ->latest('created_at')
            ->first();
        if ($run) {
            app(DurableRecoveryNotice::class)->record(
                $run,
                $exception,
                $confirmation?->message?->locale,
                str_contains(class_basename($exception), 'Timeout')
                    ? 'RUN_DEADLINE_EXCEEDED'
                    : 'WORKFLOW_RETRY_EXHAUSTED',
            );
        }

        Log::warning('ai.confirmation.continuation_exhausted', [
            'ai_run_id' => $run?->id,
            'confirmation_id' => $this->confirmationId,
            'conversation_id' => $this->conversationId,
            'exception_class' => class_basename($exception),
            'objective_id' => $confirmation?->objective_id,
            'workspace_id' => $this->workspaceId,
        ]);
    }
}
