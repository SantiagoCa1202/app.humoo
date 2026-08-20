<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ComponentActionRequest;
use App\Models\MessageBlock;

class ChatActionController extends Controller
{
    public function __invoke(
        ComponentActionRequest $request,
        ToolExecutor $toolExecutor
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
                'membership' => app('currentMembership'),
                'source_block' => $sourceBlock,
                'user' => $user,
                'workspace' => $workspace,
            ],
            $request->validated()
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
