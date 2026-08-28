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
use App\Http\Controllers\Controller;
use App\Http\Resources\AssistantResponseResource;
use App\Models\ActionConfirmation;
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
        IntentPatternRegistry $intentPatternRegistry,
        ConversationContinuationLifecycle $conversationContinuationLifecycle
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
            $intentPatternRegistry,
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
                Log::info('ai.confirmation.resolved', [
                    'action_key' => $confirmation->action_key,
                    'confirmation_id' => $confirmation->id,
                    'draft_id' => $confirmation->draft_json['draft_state']['draft_id'] ?? null,
                    'revision' => $confirmation->draft_json['draft_state']['revision'] ?? null,
                    'correlation_id' => $confirmation->draft_json['orchestration_correlation_id'] ?? $confirmation->correlation_id,
                    'workspace_id' => $workspace->id,
                ]);

                $pattern = $this->observePatternSafely($intentPatternRegistry, $confirmation, $workspace->id);

                $confirmation->forceFill([
                    'executed_at' => now(),
                    'result_ref_json' => $result['result_ref_json'] ?? null,
                    'status' => 'executed',
                ])->save();

                $conversationContinuationLifecycle->completeAfterConfirmation($confirmation);

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
                    ],
                    $confirmation->message,
                    [
                        'source' => 'confirmation-result',
                    ]
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
                    'pattern_observation' => $pattern ? [
                        'action_key' => $pattern->action_key,
                        'occurrences' => $pattern->occurrences,
                        'pattern_id' => $pattern->id,
                        'status' => $pattern->status,
                    ] : null,
                ];
            } catch (\Throwable $exception) {
                $this->recordPatternFailureSafely($intentPatternRegistry, $confirmation, $workspace->id);
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

        return response()->json([
            'data' => $response,
        ]);
    }

    private function observePatternSafely(
        IntentPatternRegistry $intentPatternRegistry,
        ActionConfirmation $confirmation,
        string $workspaceId
    ): mixed {
        try {
            return $intentPatternRegistry->observe($workspaceId, [
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
        IntentPatternRegistry $intentPatternRegistry,
        ActionConfirmation $confirmation,
        string $workspaceId
    ): void {
        try {
            $intentPatternRegistry->recordFailure($workspaceId, [
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
        AssistantMessageWriter $assistantMessageWriter
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
        AssistantMessageWriter $assistantMessageWriter
    ) {
        return $this->cancel($request, $token, $assistantMessageWriter);
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
