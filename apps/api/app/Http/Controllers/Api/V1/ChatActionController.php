<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolExecutionContext;
use App\AI\Clarifications\PendingClarificationResolver;
use App\AI\Advisory\RecipeDraftPayloadMapper;
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
        PendingClarificationResolver $pendingClarificationResolver,
        RecipeDraftPayloadMapper $recipeDraftPayloadMapper
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
        if (($payload['action_id'] ?? null) === 'continuation.draft.save') {
            $continuationId = (string) ($payload['input']['continuation_id'] ?? '');
            $metadata = is_array($context['conversation']->metadata) ? $context['conversation']->metadata : [];
            $continuation = collect($metadata['pending_continuations'] ?? [])
                ->first(fn (mixed $item): bool => is_array($item)
                    && ($item['continuation_id'] ?? null) === $continuationId
                    && ($item['kind'] ?? null) === 'draft'
                    && ($item['status'] ?? null) === 'pending'
                    && ($item['workspace_id'] ?? null) === $workspace->id
                    && ($item['conversation_id'] ?? null) === $context['conversation']->id
                    && ($item['actor_id'] ?? null) === $user->id);
            abort_unless(is_array($continuation) && ($continuation['action_key'] ?? null) === 'recipes.create', 422, 'This draft is unavailable.');
            $input = $recipeDraftPayloadMapper->toCreateInput((array) ($continuation['payload'] ?? []));
            abort_unless(is_array($input), 422, 'This draft is no longer valid.');
            $result = $toolExecutor->request($context, [
                'action_id' => 'recipes.create',
                'component_instance_id' => $sourceBlock->instance_id,
                'input' => ['recipe_draft' => $input],
            ]);
            $metadata['pending_continuations'] = collect($metadata['pending_continuations'] ?? [])
                ->map(function (mixed $item) use ($continuationId): mixed {
                    if (is_array($item) && ($item['continuation_id'] ?? null) === $continuationId) {
                        $item['status'] = 'pending_action';
                    }

                    return $item;
                })->values()->all();
            $context['conversation']->forceFill(['metadata' => $metadata])->save();
        } elseif (($payload['action_id'] ?? null) === 'entity.disambiguation.resolve') {
            $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
            $continuation = $pendingClarificationResolver->resolveEntity($context['conversation'], $workspace->id, $user->id, (string) ($input['clarification_id'] ?? ''), (string) ($input['candidate_id'] ?? ''));
            $result = $toolExecutor->request($context, $continuation);
        } elseif (($payload['action_id'] ?? null) === 'entity.disambiguation.reject') {
            $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
            $next = $pendingClarificationResolver->rejectEntity($context['conversation'], $workspace->id, $user->id, (string) ($input['clarification_id'] ?? ''));
            $result = $next === null
                ? ['status' => 'clarification_required', 'blocks' => [[
                    'component' => 'action.result',
                    'data' => ['description' => trans('chat.fallback.suggestion_rejected', [], $context['locale']), 'status' => 'partial', 'title' => trans('chat.fallback.suggestion_rejected', [], $context['locale'])],
                    'schema_version' => 1,
                    'type' => 'component',
                ]]]
                : ['status' => 'clarification_required', 'blocks' => [[
                    'actions' => [['id' => 'entity.disambiguation.resolve']],
                    'component' => 'entity.disambiguation',
                    'data' => [...$next, 'mode' => 'choose_candidate', 'selection_mode' => 'single', 'title' => trans('chat.recipe.ambiguous', [], $context['locale'])],
                    'schema_version' => 1,
                    'type' => 'component',
                ]]];
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
                    $input,
                    $user->id
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
