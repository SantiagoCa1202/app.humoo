<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ComponentActionRequest;
use App\Http\Resources\AssistantResponseResource;
use App\Models\MessageBlock;

class ChatActionController extends Controller
{
    public function __invoke(
        ComponentActionRequest $request,
        ToolExecutor $toolExecutor,
        AssistantMessageWriter $assistantMessageWriter
    ) {
        $workspace = app('currentWorkspace');
        $user = $request->user();
        $sourceBlock = MessageBlock::query()
            ->where('workspace_id', $workspace->id)
            ->where('instance_id', $request->validated('component_instance_id'))
            ->with('message.conversation.participants')
            ->firstOrFail();

        abort_unless(
            $sourceBlock->message?->conversation?->participants
                ?->contains(fn ($participant) => $participant->user_id === $user->id),
            403,
            'You do not have access to this conversation block.'
        );

        $declaredActions = collect(
            $sourceBlock->payload_json['actions'] ?? []
        )
            ->pluck('id')
            ->filter()
            ->values();

        abort_if(
            $declaredActions->isNotEmpty()
            && !$declaredActions->contains($request->validated('action_id')),
            422,
            'This action is not available for the selected component instance.'
        );

        $result = $toolExecutor->request(
            [
                'locale' => $sourceBlock->message?->locale,
                'membership' => app('currentMembership'),
                'source_block' => $sourceBlock,
                'user' => $user,
                'workspace' => $workspace,
            ],
            $request->validated()
        );
        $assistantMessage = $assistantMessageWriter->create(
            $sourceBlock->message->conversation,
            $workspace,
            $sourceBlock->message->locale,
            [
                'blocks' => $result['blocks'] ?? [],
                'suggestions' => [],
            ],
            $sourceBlock->message,
            [
                'source' => 'chat-action',
            ]
        );

        return response()->json([
            'data' => [
                'assistant_response' => new AssistantResponseResource(
                    $assistantMessage->load('blocks')
                ),
                'confirmation' => $result['confirmation'] ?? null,
                'conversation' => [
                    'id' => $assistantMessage->conversation_id,
                    'last_message_at' => $assistantMessage->conversation()->first()?->last_message_at?->toIso8601String(),
                ],
                'tool' => $result['tool'] ?? null,
            ],
        ]);
    }
}
