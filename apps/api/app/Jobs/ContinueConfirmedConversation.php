<?php

namespace App\Jobs;

use App\AI\Orchestration\AIOrchestrator;
use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\Models\ActionConfirmation;
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

final class ContinueConfirmedConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public string $confirmationId,
        public string $workspaceId,
        public string $userId,
        public ?string $conversationId = null,
    ) {
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("ai-conversation:{$this->conversationId}"))
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
        if (!$conversation || $continuationLifecycle->pendingProviderToolOutputs($conversation) === []) {
            return;
        }

        $workspaceContext->within($workspace, $membership, function () use ($aiOrchestrator, $confirmation, $membership, $user, $workspace): void {
            try {
                $aiOrchestrator->continueConfirmedConversation(
                    $confirmation,
                    [
                        'status' => 'completed',
                        'workflow_status' => 'completed',
                        'tool_keys' => [$confirmation->action_key],
                        'entity_refs' => $this->confirmedEntityRefs($confirmation),
                        'result_ref_json' => $confirmation->result_ref_json ?? [],
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
}
