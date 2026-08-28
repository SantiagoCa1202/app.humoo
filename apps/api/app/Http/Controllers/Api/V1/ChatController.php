<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Chat\SendMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Resources\AssistantResponseResource;
use App\Http\Resources\ChatConversationSummaryResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function show(
        Request $request,
        SendMessage $action
    ) {
        $workspace = app('currentWorkspace');
        $membership = app('currentMembership');
        $conversation = $this->resolveConversation(
            $request,
            $request->query('conversation_id')
        );

        if (!$conversation->messages()->exists()) {
            $action->bootstrap(
                $conversation,
                $workspace,
                $membership,
                $request->user()
            );
        }

        $conversation->load('messages.blocks');

        return response()->json([
            'data' => [
                'conversation' => new ConversationResource($conversation),
            ],
        ]);
    }

    public function history(Request $request)
    {
        $workspace = app('currentWorkspace');
        $user = $request->user();
        $conversations = Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('created_by', $user->id)
            ->where('scope_type', 'general')
            ->where('visibility', 'private')
            ->whereHas('messages', fn ($messages) => $messages->where('sender_type', 'user'))
            ->withCount('messages')
            ->addSelect([
                'latest_user_message' => Message::query()
                    ->select('content_text')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->where('sender_type', 'user')
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'conversations' => ChatConversationSummaryResource::collection($conversations),
            ],
        ]);
    }

    public function destroy(Request $request, string $conversationId)
    {
        $workspace = app('currentWorkspace');

        $conversation = Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->where('id', $conversationId)
            ->where('created_by', $request->user()->id)
            ->where('scope_type', 'general')
            ->where('visibility', 'private')
            ->firstOrFail();

        $conversation->delete();

        return response()->noContent();
    }

    public function send(
        SendMessageRequest $request,
        SendMessage $action
    ) {
        $workspace = app('currentWorkspace');
        $membership = app('currentMembership');
        $conversation = $this->resolveConversation(
            $request,
            $request->validated('conversation_id')
        );
        $result = $action->execute(
            $conversation,
            $workspace,
            $membership,
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'data' => [
                'assistant_response' => new AssistantResponseResource(
                    $result['assistant_message']
                ),
                'conversation' => [
                    'id' => $conversation->id,
                    'last_message_at' => $conversation->fresh()->last_message_at?->toIso8601String(),
                ],
                'user_message' => new MessageResource($result['user_message']),
            ],
        ], 201);
    }

    private function resolveConversation(
        Request $request,
        ?string $conversationId = null
    ): Conversation {
        $workspace = app('currentWorkspace');
        $user = $request->user();

        $conversation = Conversation::query()
            ->where('workspace_id', $workspace->id)
            ->when($conversationId, fn ($query) => $query
                ->where('id', $conversationId)
                ->whereHas('participants', fn ($participants) => $participants->where('user_id', $user->id)))
            ->when(!$conversationId, fn ($query) => $query
                ->where('created_by', $user->id)
                ->where('scope_type', 'general')
                ->where('visibility', 'private'))
            ->when(!$conversationId, fn ($query) => $query->whereHas(
                'messages',
                fn ($messages) => $messages->where('sender_type', 'user')
            ))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->first();

        if ($conversationId && !$conversation) {
            abort(404, 'Conversation not found.');
        }

        if (!$conversation && !$conversationId) {
            $conversation = Conversation::query()
                ->where('workspace_id', $workspace->id)
                ->where('created_by', $user->id)
                ->where('scope_type', 'general')
                ->where('visibility', 'private')
                ->orderByDesc('created_at')
                ->first();
        }

        if ($conversation) {
            return $conversation;
        }

        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'title' => 'Humoo AI',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
            'metadata' => [
                'source' => 'chat',
            ],
        ]);

        ConversationParticipant::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return $conversation;
    }
}
