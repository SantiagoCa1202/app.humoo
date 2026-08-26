<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\AI\Clarifications\PendingClarificationResolver;
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
        AssistantMessageWriter $assistantMessageWriter,
        PendingClarificationResolver $pendingClarificationResolver
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

        $context = [
            'conversation' => $sourceBlock->message->conversation,
            'locale' => $sourceBlock->message?->locale,
            'membership' => app('currentMembership'),
            'source_block' => $sourceBlock,
            'user' => $user,
            'workspace' => $workspace,
        ];
        $payload = $request->validated();
        if (($payload['action_id'] ?? null) === 'clarification.resolve') {
            $resolved = $pendingClarificationResolver->resolve(
                $context['conversation'],
                $workspace->id,
                (string) ($payload['input']['clarification_id'] ?? ''),
                is_array($payload['input'] ?? null) ? $payload['input'] : []
            );
            $result = $toolExecutor->request($context, [
                ...$payload,
                'action_id' => 'recipes.create',
                'input' => ['recipe_draft' => $resolved['draft']],
            ]);
        } elseif (($payload['action_id'] ?? null) === 'clarification.cancel') {
            $pendingClarificationResolver->cancel($context['conversation'], $workspace->id, (string) ($payload['input']['clarification_id'] ?? ''));
            $result = ['blocks' => [['component' => 'action.result', 'data' => ['description' => trans('chat.clarification.cancelled', [], $context['locale']), 'status' => 'partial', 'title' => trans('chat.clarification.cancelled', [], $context['locale'])], 'schema_version' => 1, 'type' => 'component']]];
        } else {
            $result = $toolExecutor->request($context, $payload);
        }
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
