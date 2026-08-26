<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolExecutionContext;
use App\AI\Clarifications\PendingClarificationResolver;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ComponentActionRequest;
use App\Http\Resources\AssistantResponseResource;
use App\Models\MessageBlock;
use Illuminate\Support\Facades\Log;

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

        $context = (new ToolExecutionContext(
            workspace: $workspace,
            user: $user,
            membership: app('currentMembership'),
            conversation: $sourceBlock->message->conversation,
            locale: (string) ($sourceBlock->message?->locale ?? 'en'),
            timezone: (string) ($workspace->timezone ?? 'UTC'),
        ))->toArray([
            'source_block' => $sourceBlock,
        ]);
        $payload = $request->validated();
        if (($payload['action_id'] ?? null) === 'entity.disambiguation.resolve') {
            $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
            $continuation = $pendingClarificationResolver->resolveEntity($context['conversation'], $workspace->id, $user->id, (string) ($input['clarification_id'] ?? ''), (string) ($input['candidate_id'] ?? ''));
            $result = $toolExecutor->request($context, $continuation);
        } elseif (($payload['action_id'] ?? null) === 'clarification.resolve') {
            $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
            $clarificationId = (string) ($input['clarification_id'] ?? '');
            Log::info('ai.clarification.resolve_requested', [
                'clarification_id' => $clarificationId,
                'conversation_id' => $context['conversation']->id,
                'expected_type' => 'number',
                'selected_option_id' => $input['selected_option_id'] ?? null,
                'workflow' => 'recipes.create',
                'workspace_id' => $workspace->id,
            ]);

            try {
                $resolved = $pendingClarificationResolver->resolve(
                    $context['conversation'],
                    $workspace->id,
                    $clarificationId,
                    $input
                );
            } catch (\Throwable $exception) {
                Log::warning('ai.clarification.resolve_failed', [
                    'clarification_id' => $clarificationId,
                    'conversation_id' => $context['conversation']->id,
                    'failure_stage' => 'resolver',
                    'internal_code' => class_basename($exception),
                    'selected_option_id' => $input['selected_option_id'] ?? null,
                    'workflow' => 'recipes.create',
                    'workspace_id' => $workspace->id,
                ]);
                throw $exception;
            }

            try {
                $result = $toolExecutor->request($context, [
                    ...$payload,
                    'action_id' => 'recipes.create',
                    'input' => ['recipe_draft' => $resolved['draft']],
                ]);
            } catch (\Throwable $exception) {
                Log::warning('ai.clarification.resolve_failed', [
                    'clarification_id' => $clarificationId,
                    'conversation_id' => $context['conversation']->id,
                    'failure_stage' => 'workflow_continuation',
                    'internal_code' => class_basename($exception),
                    'selected_option_id' => $input['selected_option_id'] ?? null,
                    'workflow' => 'recipes.create',
                    'workspace_id' => $workspace->id,
                ]);
                throw $exception;
            }
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
