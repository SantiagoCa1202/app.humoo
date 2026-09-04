<?php

namespace App\Http\Controllers\Api\V1;

use App\AI\Tools\ToolExecutor;
use App\AI\EntityResolution\EntityAliasStore;
use App\AI\EntityResolution\EntityCandidate;
use App\AI\EntityResolution\EntityResolutionRequest;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Orchestration\ConversationContinuationLifecycle;
use App\AI\Orchestration\ToolLoopResultComposer;
use App\AI\Runtime\AiRunLifecycle;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssistantResponseResource;
use App\Models\ActionConfirmation;
use App\Jobs\ContinueConfirmedConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfirmationController extends Controller
{
    public function confirm(
        Request $request,
        string $token,
        ToolExecutor $toolExecutor,
        EntityAliasStore $entityAliasStore,
        AssistantMessageWriter $assistantMessageWriter,
        RecordConversationEntityRefs $recordConversationEntityRefs,
        ConversationContinuationLifecycle $conversationContinuationLifecycle,
        AiRunLifecycle $aiRunLifecycle,
    ) {
        $workspace = app('currentWorkspace');
        $user = $request->user();
        $overrideInput = $request->input('input');
        $requestedIdempotencyKey = trim((string) $request->input('idempotency_key', ''));

        $response = DB::transaction(function () use (
            $assistantMessageWriter,
            $entityAliasStore,
            $recordConversationEntityRefs,
            $token,
            $toolExecutor,
            $user,
            $workspace,
            $overrideInput,
            $requestedIdempotencyKey,
            $conversationContinuationLifecycle
        ): array {
            $confirmation = ActionConfirmation::query()
                ->where('workspace_id', $workspace->id)
                ->where('token_hash', hash('sha256', $token))
                ->with('message.conversation')
                ->lockForUpdate()
                ->firstOrFail();

            if ($confirmation->status === 'executed'
                && $requestedIdempotencyKey !== ''
                && hash_equals((string) $confirmation->idempotency_key, $requestedIdempotencyKey)) {
                Log::info('ai.confirmation.idempotent_replay', [
                    'action_key' => $confirmation->action_key,
                    'confirmation_id' => $confirmation->id,
                    'workspace_id' => $workspace->id,
                ]);

                return [
                    'assistant_response' => null,
                    'confirmation' => [
                        'id' => $confirmation->id,
                        'status' => 'executed',
                        'token' => null,
                        'idempotency_key' => $confirmation->idempotency_key,
                    ],
                    'conversation' => [
                        'id' => $confirmation->message?->conversation_id,
                        'last_message_at' => $confirmation->message?->conversation?->last_message_at?->toIso8601String(),
                    ],
                    'tool' => null,
                ];
            }

            $this->guardConfirmation($confirmation, $user->id);

            $confirmation->forceFill([
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
                'status' => 'confirmed',
            ])->save();

            try {
                $result = $toolExecutor->confirm(
                    $confirmation,
                    [
                        'locale' => $confirmation->message?->locale,
                        'membership' => app('currentMembership'),
                        'user' => $user,
                        'workspace' => $workspace,
                    ],
                    is_array($overrideInput) ? $overrideInput : null
                );
                $presentationContext = is_array($confirmation->draft_json['presentation_context'] ?? null)
                    ? $confirmation->draft_json['presentation_context']
                    : [];
                $supportingResults = is_array($presentationContext['supporting_results'] ?? null)
                    ? $presentationContext['supporting_results']
                    : [];
                if ($supportingResults !== []) {
                    $result = ToolLoopResultComposer::compose($supportingResults, $result);
                }
                Log::info('ai.confirmation.resolved', [
                    'action_key' => $confirmation->action_key,
                    'confirmation_id' => $confirmation->id,
                    'draft_id' => $confirmation->draft_json['draft_state']['draft_id'] ?? null,
                    'revision' => $confirmation->draft_json['draft_state']['revision'] ?? null,
                    'correlation_id' => $confirmation->draft_json['orchestration_correlation_id'] ?? $confirmation->correlation_id,
                    'workspace_id' => $workspace->id,
                ]);

                $pattern = $this->observePatternSafely($confirmation, $workspace->id);

                $confirmation->forceFill([
                    'executed_at' => now(),
                    'result_ref_json' => $result['result_ref_json'] ?? null,
                    'status' => 'executed',
                ])->save();

                if (!$this->isExecutionPlanConfirmation($confirmation, $result)) {
                    $conversationContinuationLifecycle->resolvePendingProviderToolCallForConfirmation(
                        $confirmation,
                        $result
                    );
                }
                $conversationContinuationLifecycle->completeAfterConfirmation($confirmation);
                $this->updateOperationalContextAfterConfirmation($confirmation, $result, 'executed');

                $this->rememberConfirmedEntityAlias($confirmation, $workspace->id, $user->id, $entityAliasStore);

                $recordConversationEntityRefs->execute(
                    $confirmation->message->conversation,
                    $workspace,
                    $result['entity_refs'] ?? []
                );

                $assistantMessage = $assistantMessageWriter->create(
                    $confirmation->message->conversation,
                    $workspace,
                    $confirmation->message->locale,
                    [
                        'blocks' => $result['blocks'] ?? [],
                        'suggestions' => [],
                        'tool' => $result['tool'] ?? null,
                    ],
                    $confirmation->message,
                    [
                        'source' => 'confirmation-result',
                    ]
                );
                $toolExecutor->attachExecutionPlanProgressMessage(
                    $result,
                    $assistantMessage,
                    $workspace->id,
                );

                return [
                    'assistant_response' => new AssistantResponseResource(
                        $assistantMessage->load('blocks')
                    ),
                    'confirmation' => [
                        'id' => $confirmation->id,
                        'status' => 'executed',
                        'token' => null,
                        'idempotency_key' => $confirmation->idempotency_key,
                    ],
                    'conversation' => [
                        'id' => $assistantMessage->conversation_id,
                        'last_message_at' => $assistantMessage->conversation()->first()?->last_message_at?->toIso8601String(),
                    ],
                    'tool' => $result['tool'] ?? null,
                    // Internal handoff for a dependent provider continuation;
                    // removed before the HTTP response is serialized.
                    'continuation_result' => $result,
                    'pattern_observation' => $pattern ? [
                        'action_key' => $pattern->action_key,
                        'occurrences' => $pattern->occurrences,
                        'pattern_id' => $pattern->id,
                        'status' => $pattern->status,
                    ] : null,
                ];
            } catch (\Throwable $exception) {
                $this->recordPatternFailureSafely($confirmation, $workspace->id);
                Log::warning('ai.confirmation.failed', [
                    'action_key' => $confirmation->action_key,
                    'confirmation_id' => $confirmation->id,
                    'correlation_id' => $confirmation->draft_json['orchestration_correlation_id'] ?? $confirmation->correlation_id,
                    'error_code' => $exception instanceof \Illuminate\Validation\ValidationException
                        ? 'VALIDATION_FAILED'
                        : 'CONFIRMATION_EXECUTION_FAILED',
                    'exception_class' => class_basename($exception),
                    'validation_fields' => $exception instanceof \Illuminate\Validation\ValidationException
                        ? array_keys($exception->errors())
                        : [],
                    'workspace_id' => $workspace->id,
                ]);
                $confirmation->forceFill([
                    'error_code' => method_exists($exception, 'getCode') && $exception->getCode()
                        ? (string) $exception->getCode()
                        : 'CONFIRMATION_EXECUTION_FAILED',
                    'error_message' => $exception->getMessage(),
                    'status' => 'failed',
                ])->save();

                throw $exception;
            }
        });

        $confirmationId = data_get($response, 'confirmation.id');
        $shouldQueueContinuation = array_key_exists('continuation_result', $response);
        unset($response['continuation_result']);
        if ($shouldQueueContinuation && filled($confirmationId)) {
            $confirmation = ActionConfirmation::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($confirmationId)
                ->with('message.conversation')
                ->first();
            if ($confirmation && $confirmation->message?->conversation
                && $conversationContinuationLifecycle->pendingProviderToolOutputs($confirmation->message->conversation) !== []) {
                try {
                    // The write is already committed. A provider continuation
                    // can take longer than a user-facing HTTP request, so it
                    // must run through the retryable worker and publish its
                    // progress through the existing chat stream.
                    ContinueConfirmedConversation::dispatch(
                        (string) $confirmation->id,
                        (string) $workspace->id,
                        (string) $user->id,
                        (string) $confirmation->message->conversation->id,
                    )->afterCommit();
                    Log::info('ai.confirmation.continuation_queued', [
                        'action_key' => $confirmation->action_key,
                        'confirmation_id' => $confirmation->id,
                        'workspace_id' => $workspace->id,
                    ]);
                    $response['continuation'] = ['status' => 'queued'];
                } catch (\Throwable $exception) {
                    // Do not turn an already committed confirmation into an
                    // apparent client failure if the queue is temporarily
                    // unavailable. The persisted provider output remains
                    // available for operational recovery and auditing.
                    Log::error('ai.confirmation.continuation_queue_failed', [
                        'action_key' => $confirmation->action_key,
                        'confirmation_id' => $confirmation->id,
                        'exception_class' => class_basename($exception),
                        'workspace_id' => $workspace->id,
                    ]);
                    $response['continuation'] = ['status' => 'deferred'];
                }
            }
        }

        if (filled($confirmationId)) {
            $this->finishOriginatingRun(
                (string) $confirmationId,
                (string) $workspace->id,
                $aiRunLifecycle,
                'completed',
            );
        }

        return response()->json([
            'data' => $response,
        ]);
    }

    private function observePatternSafely(
        ActionConfirmation $confirmation,
        string $workspaceId
    ): mixed {
        if ((bool) config('ai.routing.tool_loop_enabled', true)) {
            return null;
        }

        try {
            return app(IntentPatternRegistry::class)->observe($workspaceId, [
                'routing' => is_array($confirmation->draft_json['routing'] ?? null)
                    ? $confirmation->draft_json['routing']
                    : [],
                'slots' => [],
            ], true);
        } catch (\Throwable $exception) {
            Log::warning('ai.intent_pattern.observe_failed', [
                'action_key' => $confirmation->action_key,
                'confirmation_id' => $confirmation->id,
                'exception_class' => class_basename($exception),
                'workspace_id' => $workspaceId,
            ]);

            return null;
        }
    }

    private function recordPatternFailureSafely(
        ActionConfirmation $confirmation,
        string $workspaceId
    ): void {
        if ((bool) config('ai.routing.tool_loop_enabled', true)) {
            return;
        }

        try {
            app(IntentPatternRegistry::class)->recordFailure($workspaceId, [
                'routing' => is_array($confirmation->draft_json['routing'] ?? null)
                    ? $confirmation->draft_json['routing']
                    : [],
            ]);
        } catch (\Throwable $exception) {
            Log::warning('ai.intent_pattern.failure_observation_failed', [
                'action_key' => $confirmation->action_key,
                'confirmation_id' => $confirmation->id,
                'exception_class' => class_basename($exception),
                'workspace_id' => $workspaceId,
            ]);
        }
    }

    private function rememberConfirmedEntityAlias(
        ActionConfirmation $confirmation,
        string $workspaceId,
        string $actorId,
        EntityAliasStore $entityAliasStore
    ): void {
        $alias = is_array($confirmation->draft_json['entity_reference_alias'] ?? null)
            ? $confirmation->draft_json['entity_reference_alias']
            : [];
        $rawAlias = trim((string) ($alias['alias'] ?? ''));
        $entityId = trim((string) ($alias['entity_id'] ?? ''));
        $entityType = trim((string) ($alias['entity_type'] ?? ''));
        if ($rawAlias === '' || $entityId === '' || $entityType === '') {
            return;
        }

        $entityAliasStore->remember(
            new EntityResolutionRequest(
                workspaceId: $workspaceId,
                actorId: $actorId,
                conversationId: $confirmation->message?->conversation_id,
                actionKey: $confirmation->action_key,
                entityType: $entityType,
                unresolvedField: 'entity_id',
                locale: (string) ($alias['locale'] ?? $confirmation->message?->locale ?? 'en'),
                riskLevel: 'write',
            ),
            new EntityCandidate($entityId, $entityType, $entityId),
            $rawAlias
        );
    }

    public function cancel(
        Request $request,
        string $token,
        ToolExecutor $toolExecutor,
        AssistantMessageWriter $assistantMessageWriter,
        ConversationContinuationLifecycle $conversationContinuationLifecycle,
        AiRunLifecycle $aiRunLifecycle,
    ) {
        $workspace = app('currentWorkspace');
        $user = $request->user();
        $confirmation = ActionConfirmation::query()
            ->where('workspace_id', $workspace->id)
            ->where('token_hash', hash('sha256', $token))
            ->with('message.conversation')
            ->firstOrFail();

        $this->guardConfirmation($confirmation, $user->id);

        $confirmation->forceFill([
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'status' => 'cancelled',
        ])->save();
        $toolExecutor->cancelExecutionPlanForConfirmation($confirmation, $user->id);
        $conversationContinuationLifecycle->resolvePendingProviderToolCallForConfirmation(
            $confirmation,
            ['status' => 'cancelled']
        );
        $this->updateOperationalContextAfterConfirmation($confirmation, [], 'cancelled');
        $this->finishOriginatingRun(
            (string) $confirmation->id,
            (string) $workspace->id,
            $aiRunLifecycle,
            'cancelled',
        );

        $assistantMessage = $assistantMessageWriter->create(
            $confirmation->message->conversation,
            $workspace,
            $confirmation->message->locale,
            [
                'blocks' => [
                    [
                        'text' => 'La accion confirmable fue cancelada y no modifico datos.',
                        'type' => 'text',
                    ],
                    [
                        'component' => 'action.result',
                        'data' => [
                            'description' => 'La mutacion quedo detenida antes de ejecutarse.',
                            'details' => [
                                [
                                    'label' => 'Accion',
                                    'value' => $confirmation->action_key,
                                ],
                            ],
                            'status' => 'partial',
                            'title' => 'Accion cancelada',
                        ],
                        'schema_version' => 1,
                        'type' => 'component',
                    ],
                ],
                'suggestions' => [],
            ],
            $confirmation->message,
            [
                'source' => 'confirmation-cancelled',
            ]
        );

        return response()->json([
            'data' => [
                'assistant_response' => new AssistantResponseResource(
                    $assistantMessage->load('blocks')
                ),
                'confirmation' => [
                    'id' => $confirmation->id,
                    'status' => 'cancelled',
                    'token' => null,
                ],
                'conversation' => [
                    'id' => $assistantMessage->conversation_id,
                    'last_message_at' => $assistantMessage->conversation()->first()?->last_message_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    public function reject(
        Request $request,
        string $token,
        ToolExecutor $toolExecutor,
        AssistantMessageWriter $assistantMessageWriter,
        ConversationContinuationLifecycle $conversationContinuationLifecycle,
        AiRunLifecycle $aiRunLifecycle,
    ) {
        return $this->cancel($request, $token, $toolExecutor, $assistantMessageWriter, $conversationContinuationLifecycle, $aiRunLifecycle);
    }

    private function finishOriginatingRun(
        string $confirmationId,
        string $workspaceId,
        AiRunLifecycle $lifecycle,
        string $status,
    ): void {
        $confirmation = ActionConfirmation::query()
            ->where('workspace_id', $workspaceId)
            ->with('message')
            ->find($confirmationId);
        if (! $confirmation?->message_id) {
            return;
        }

        $hasExecutionPlan = \App\Models\AiExecutionPlan::query()
            ->where('workspace_id', $workspaceId)
            ->where('confirmation_id', $confirmationId)
            ->exists();
        if ($hasExecutionPlan && $status === 'completed') {
            return;
        }

        $run = \App\Models\AiRun::query()
            ->where('workspace_id', $workspaceId)
            ->where('message_id', $confirmation->message_id)
            ->latest('created_at')
            ->first();
        if (! $run || in_array($run->status, AiRunLifecycle::TERMINAL_STATUSES, true)) {
            return;
        }

        if ($status === 'completed') {
            $lifecycle->resumeAndFinishConfirmation($run);

            return;
        }

        $lifecycle->transition($run, $status, $status);
    }

    private function updateOperationalContextAfterConfirmation(ActionConfirmation $confirmation, array $result, string $status): void
    {
        $conversation = $confirmation->message?->conversation;
        if (!$conversation) {
            return;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = is_array($metadata['ai_operational_context'] ?? null) ? $metadata['ai_operational_context'] : [];
        $confirmedRefs = $status === 'executed'
            ? $this->confirmedEntityRefs($confirmation, $result)
            : [];
        $activeRefs = collect([
            ...(is_array($state['active_entity_refs'] ?? null) ? $state['active_entity_refs'] : []),
            ...$confirmedRefs,
        ])->filter(fn (mixed $ref): bool => is_array($ref) && filled($ref['id'] ?? null) && filled($ref['type'] ?? null))
            ->reverse()
            ->unique(fn (array $ref): string => (string) $ref['type'].':'.(string) $ref['id'])
            ->reverse()
            ->values()
            ->all();
        $metadata['ai_operational_context'] = [
            ...$state,
            'active_entity_refs' => $activeRefs,
            'pending_confirmation' => null,
            'draft' => null,
            'last_operation' => [
                'action_key' => $confirmation->action_key,
                'status' => $status,
                'result_ref' => $result['result_ref_json'] ?? null,
                'updated_at' => now()->toIso8601String(),
            ],
        ];
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    /** @return array<int, array<string, mixed>> */
    private function confirmedEntityRefs(ActionConfirmation $confirmation, array $result): array
    {
        $refs = collect($result['entity_refs'] ?? [])
            ->filter(fn (mixed $ref): bool => is_array($ref))
            ->values();
        $resource = is_array($result['result_ref_json'] ?? null) ? $result['result_ref_json'] : [];
        $entityId = $resource['id'] ?? data_get($resource, 'data.id');
        $entityType = data_get($result, 'tool.entity_type');
        if ($refs->isEmpty() && filled($entityId) && filled($entityType)) {
            $refs->push([
                'id' => (string) $entityId,
                'role' => 'active',
                'snapshot' => $resource,
                'type' => (string) $entityType,
            ]);
        }

        return $refs->map(function (array $ref) use ($confirmation): array {
            $snapshot = is_array($ref['snapshot'] ?? null) ? $ref['snapshot'] : [];
            $currentVersion = $snapshot['current_version_record'] ?? $snapshot['currentVersionRecord'] ?? [];

            return array_filter([
                'id' => (string) ($ref['id'] ?? ''),
                'type' => (string) ($ref['type'] ?? ''),
                'role' => 'active',
                'entity_id' => (string) ($ref['id'] ?? ''),
                'entity_type' => (string) ($ref['type'] ?? ''),
                'name' => $snapshot['name'] ?? null,
                'title' => $snapshot['title'] ?? null,
                'current_version_id' => $snapshot['current_version_id']
                    ?? (is_array($currentVersion) ? ($currentVersion['id'] ?? null) : null),
                'originating_action' => (string) $confirmation->action_key,
                'confirmation_id' => (string) $confirmation->id,
                'snapshot' => array_filter([
                    'id' => $ref['id'] ?? null,
                    'name' => $snapshot['name'] ?? null,
                    'title' => $snapshot['title'] ?? null,
                    'status' => $snapshot['status'] ?? null,
                    'current_version_id' => $snapshot['current_version_id']
                        ?? (is_array($currentVersion) ? ($currentVersion['id'] ?? null) : null),
                    'revision' => is_array($currentVersion) ? ($currentVersion['revision'] ?? null) : ($snapshot['revision'] ?? null),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
        })->filter(fn (array $ref): bool => $ref['id'] !== '' && $ref['type'] !== '')
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $result */
    private function isExecutionPlanConfirmation(ActionConfirmation $confirmation, array $result): bool
    {
        return filled($result['execution_plan_id'] ?? null);
    }

    private function guardConfirmation(
        ActionConfirmation $confirmation,
        string $userId
    ): void {
        abort_if(
            $confirmation->status !== 'pending',
            409,
            'This confirmation can no longer be executed.'
        );

        abort_if(
            $confirmation->expires_at && $confirmation->expires_at->isPast(),
            409,
            'This confirmation has expired.'
        );

        abort_if(
            $confirmation->message?->conversation?->created_by !== $userId
            && !$confirmation->message?->conversation?->participants()
                ->where('user_id', $userId)
                ->exists(),
            403,
            'You do not have access to this confirmation.'
        );
    }
}
