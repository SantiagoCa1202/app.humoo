<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Runtime\AiRunLifecycle;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiRunResource;
use App\Http\Resources\MessageResource;
use App\Models\AiRun;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiRunController extends Controller
{
    public function index(Request $request)
    {
        $workspace = app('currentWorkspace');
        $conversationId = trim((string) $request->query('conversation_id', ''));
        abort_if($conversationId === '', 422, 'A conversation is required.');

        $this->authorizeConversation($request, $conversationId, (string) $workspace->id);

        $runs = AiRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('conversation_id', $conversationId)
            ->where(function ($query): void {
                $query->whereIn('status', [
                    ...AiRunLifecycle::ACTIVE_STATUSES,
                    ...AiRunLifecycle::PAUSED_STATUSES,
                    'pending',
                ])->orWhere('updated_at', '>=', now()->subMinutes(10));
            })
            ->with('assistantMessage.blocks')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        Log::info('ai.run.snapshot_loaded', [
            'conversation_id' => $conversationId,
            'run_count' => $runs->count(),
            'workspace_id' => $workspace->id,
        ]);

        return response()->json([
            'data' => [
                'runs' => AiRunResource::collection($runs),
                'messages' => MessageResource::collection(
                    $runs->pluck('assistantMessage')->filter()->unique('id')->values()
                ),
            ],
        ]);
    }

    public function show(Request $request, string $runId)
    {
        $workspace = app('currentWorkspace');
        $run = AiRun::query()
            ->where('workspace_id', $workspace->id)
            ->with('assistantMessage.blocks')
            ->findOrFail($runId);

        abort_unless($run->conversation_id, 404);
        $this->authorizeConversation($request, (string) $run->conversation_id, (string) $workspace->id);

        return response()->json([
            'data' => [
                'run' => new AiRunResource($run),
                'assistant_message' => $run->assistantMessage
                    ? new MessageResource($run->assistantMessage)
                    : null,
            ],
        ]);
    }

    private function authorizeConversation(Request $request, string $conversationId, string $workspaceId): void
    {
        Conversation::query()
            ->whereKey($conversationId)
            ->where('workspace_id', $workspaceId)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();
    }
}
