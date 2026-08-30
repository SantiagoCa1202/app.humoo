<?php

namespace App\Jobs;

use App\AI\Orchestration\AIOrchestrator;
use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\Models\ActionConfirmation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ContinueConfirmedConversation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public string $confirmationId,
        public string $workspaceId,
        public string $userId,
    ) {
    }

    public function handle(
        AIOrchestrator $aiOrchestrator,
        ConversationContinuationLifecycle $continuationLifecycle,
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

        try {
            $aiOrchestrator->continueConfirmedConversation(
                $confirmation,
                [
                    'status' => 'completed',
                    'workflow_status' => 'completed',
                    'tool_keys' => [$confirmation->action_key],
                    'entity_refs' => [],
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
    }
}
