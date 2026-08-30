<?php

namespace App\AI\Orchestration;

use App\AI\Advisory\AdvisoryOrchestrator;
use App\AI\Advisory\RecipeDraftPayloadMapper;
use App\AI\Capabilities\CapabilityCall;
use App\AI\Capabilities\CapabilityFunctionRouter;
use App\AI\Capabilities\OpenAiFunctionSchemaFactory;
use App\AI\Capabilities\Drafts\RecipeCreateDraftData;
use App\AI\Clarifications\PendingClarificationResolver;
use App\AI\Conversations\OpenAIConversationService;
use App\AI\Contracts\ToolCallingProvider;
use App\AI\Errors\ErrorResponseMapper;
use App\AI\Exceptions\AiProviderException;
use App\AI\Intent\HybridIntentRouter;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Intent\RoutingDecisionValidator;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolExecutionContext;
use App\AI\Tools\ToolRegistry;
use App\AI\Tools\ToolProfileSelector;
use App\AI\Temporal\TemporalContextResolver;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\Application\Actions\Chat\RecordUnsupportedCapability;
use App\Models\AiRun;
use App\Models\AiToolCall;
use App\Models\ActionConfirmation;
use App\Models\CapabilityRequest;
use App\Models\Conversation;
use App\Models\ConversationEntityRef;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIOrchestrator
{
    public function __construct(
        private HybridIntentRouter $hybridIntentRouter,
        private IntentPatternRegistry $intentPatternRegistry,
        private HumooSystemInstructions $systemInstructions,
        private AssistantMessageWriter $assistantMessageWriter,
        private RecordConversationEntityRefs $recordConversationEntityRefs,
        private RecordUnsupportedCapability $recordUnsupportedCapability,
        private ToolExecutor $toolExecutor,
        private ToolRegistry $toolRegistry,
        private AdvisoryOrchestrator $advisoryOrchestrator,
        private RecipeDraftPayloadMapper $recipeDraftPayloadMapper,
        private ContinuationResolver $continuationResolver,
        private ConversationContinuationLifecycle $conversationContinuationLifecycle,
        private PendingClarificationResolver $pendingClarificationResolver,
        private RoutingDecisionValidator $routingDecisionValidator,
        private MessageLocaleResolver $messageLocaleResolver,
        private CapabilityFunctionRouter $capabilityFunctionRouter,
        private ?ToolCallingProvider $toolCallingProvider = null,
        private ?ToolProfileSelector $toolProfileSelector = null,
        private ?OpenAIConversationService $openAIConversationService = null,
        private ?TemporalContextResolver $temporalContextResolver = null,
    ) {
    }

    /**
     * Continue a provider tool loop after the confirmation endpoint has
     * executed a pending write. The endpoint uses this public handoff so the
     * canonical loop remains the only owner of dependent follow-up calls.
     */
    public function continueConfirmedConversation(
        ActionConfirmation $confirmation,
        array $result,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user
    ): ?Message {
        if (!$this->toolCallingProvider instanceof ToolCallingProvider) {
            return null;
        }

        $conversation = $confirmation->message?->conversation;
        if (!$conversation || $this->conversationContinuationLifecycle->pendingProviderToolOutputs($conversation) === []) {
            return null;
        }

        $locale = (string) ($confirmation->message?->locale ?? 'en');
        $timezone = (string) ($workspace->timezone ?? config('app.timezone', 'UTC'));
        $correlationId = (string) ($confirmation->draft_json['orchestration_correlation_id']
            ?? $confirmation->correlation_id
            ?? OrchestrationContext::correlationId());
        $assistantMessage = $this->assistantMessageWriter->createPending(
            $conversation,
            $workspace,
            $locale,
            $confirmation->message,
            ['source' => 'confirmation-continuation', 'orchestration_version' => 'tool-loop-v1']
        );
        $aiRun = $this->startRun(
            $assistantMessage,
            $confirmation->message,
            $workspace,
            $locale,
            $timezone,
            $correlationId
        );

        $contextObject = $this->buildContext(
            $conversation,
            $workspace,
            $membership,
            $user,
            $confirmation->message,
            $assistantMessage,
            $locale,
            $timezone,
            $correlationId
        );
        $context = [
            ...$contextObject->toArray(),
            'message' => '',
            'message_id' => $confirmation->message->id,
            'operational_context' => $this->operationalContextSnapshot($conversation, $workspace, $user),
            'openai_conversation_id' => $conversation->openai_conversation_id,
            'correlation_id' => $correlationId,
        ];
        $temporalContext = ($this->temporalContextResolver ?? app(TemporalContextResolver::class))->resolve(
            $workspace,
            $user,
            $locale,
            (array) ($context['active_entities'] ?? []),
        );
        $context['timezone'] = (string) ($temporalContext['timezone'] ?? $timezone);
        $context['temporal_context'] = $temporalContext;

        try {
            $continuedResult = $this->continueToolLoopAfterConfirmation(
                $conversation,
                $workspace,
                $user,
                $assistantMessage,
                $aiRun,
                $context,
                $result,
                $locale
            );
        } catch (\Throwable $exception) {
            Log::warning('ai.confirmation.continuation_failed', [
                'action_key' => $confirmation->action_key,
                'confirmation_id' => $confirmation->id,
                'exception_class' => class_basename($exception),
                'workspace_id' => $workspace->id,
            ]);
            $continuedResult = $result;
        }
        $continuedResult['workflow_status'] ??= $continuedResult['status'] ?? 'completed';
        $continuedResult['tool_keys'] = array_values(array_unique([
            $confirmation->action_key,
            ...(array) ($continuedResult['tool_keys'] ?? []),
        ]));
        $this->recordAndCompleteToolLoop(
            $conversation,
            $workspace,
            $assistantMessage,
            $aiRun,
            $continuedResult,
            $locale,
            $correlationId,
            ['model' => $aiRun->model_key, 'provider' => 'openai', 'tool_profile' => 'all'],
            [],
            $continuedResult['tool_keys']
        );

        return $assistantMessage->fresh('blocks');
    }

    public function respond(
        Conversation $conversation,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
        Message $userMessage,
        array $payload
    ): Message {
        $locale = $this->messageLocaleResolver->resolve(
            $payload['locale'] ?? null,
            (string) ($userMessage->content_text ?? ''),
            $workspace,
            $user,
        );
        $temporalResolver = $this->temporalContextResolver ?? app(TemporalContextResolver::class);
        $timezone = (string) ($temporalResolver->resolve(
            $workspace,
            $user,
            $locale,
        )['timezone'] ?? 'UTC');
        $correlationId = OrchestrationContext::correlationId();

        if ($this->toolLoopEnabled() && $this->toolCallingProvider instanceof ToolCallingProvider) {
            return $this->respondWithToolLoop(
                $conversation,
                $workspace,
                $membership,
                $user,
                $userMessage,
                $locale,
                $timezone,
                $correlationId
            );
        }

        if (config('ai.providers.openai.debug_logging', false)) {
            Log::info('ai.chat.message_received', [
                'conversation_id' => $conversation->id,
                'correlation_id' => $correlationId,
                'message_id' => $userMessage->id,
                'workspace_id' => $workspace->id,
            ]);
        }

        $assistantMessage = $this->assistantMessageWriter->createPending(
            $conversation,
            $workspace,
            $locale,
            $userMessage,
            [
                'source' => 'assistant-response',
            ]
        );
        $aiRun = $this->startRun(
            $assistantMessage,
            $userMessage,
            $workspace,
            $locale,
            $timezone,
            $correlationId
        );
        $decision = [];
        $capabilityCall = null;
        $orchestrationContext = null;
        $continuation = null;
        $failureStage = 'context_initialization';

        try {
            $orchestrationContext = $this->buildContext(
                $conversation,
                $workspace,
                $membership,
                $user,
                $userMessage,
                $assistantMessage,
                $locale,
                $timezone,
                $correlationId,
            );
            $context = $orchestrationContext->toArray();
            $routerContext = [
                'available_tools' => $context['available_tools'],
                'conversation_id' => $conversation->id,
                'correlation_id' => $correlationId,
                'entity_refs' => $context['entity_refs'],
                'operational_context' => $this->operationalContextSnapshot($conversation, $workspace, $user),
                'locale' => $locale,
                'message' => $userMessage->content_text ?? '',
                'message_id' => $userMessage->id,
                'recent_entity_refs' => $context['recent_entity_refs'],
                'recent_messages' => $context['recent_messages'],
                'system_instructions' => $this->systemInstructions->toText(),
                'timezone' => $timezone,
                'user' => $user,
                'workspace' => $workspace,
                'workspace_id' => $workspace->id,
            ];
            $failureStage = 'continuation_resolution';
            $continuation = $this->continuationResolver->resolve($orchestrationContext);
            $this->logContinuation('conversation.continuation.detected', $orchestrationContext, $continuation);
            $result = null;
            if ($continuation->status === 'resolved' && $continuation->source === 'cancellation') {
                $result = $this->cancelToolLoopContinuation($conversation, $workspace, $user, $continuation, $routerContext);
                $decision = ['intent' => 'continuation', 'interaction_mode' => 'continuation', 'routing' => ['source' => 'continuation_cancellation']];
                $this->logContinuation('conversation.continuation.cancelled', $orchestrationContext, $continuation);
            } elseif ($continuation->status === 'resolved') {
                if ($continuation->source === 'clarification') {
                    $resolved = $this->pendingClarificationResolver->resolve(
                        $conversation,
                        $workspace->id,
                        $continuation->continuationId ?? '',
                        (array) ($continuation->data['input'] ?? []),
                        $user->id
                    );
                    $actionKey = $continuation->actionKey
                        ?? ($resolved['clarification']['action_key'] ?? null)
                        ?? ($resolved['clarification']['workflow'] ?? '');
                    $resolvedInput = $actionKey === 'recipes.create'
                        ? ['recipe_draft' => $resolved['draft']]
                        : (is_array($resolved['input'] ?? null) ? $resolved['input'] : []);
                    $decision = $this->continuationActionDecision(
                        $actionKey,
                        $resolvedInput,
                        'clarification'
                    );
                } elseif ($continuation->source === 'confirmation') {
                    $decision = $this->continuationActionDecision(
                        $continuation->actionKey ?? '',
                        [],
                        'confirmation'
                    );
                    $result = $this->resumeConfirmation($continuation, $context);
                } elseif ($continuation->source === 'draft') {
                    $draft = is_array($continuation->data['draft']['payload'] ?? null)
                        ? $continuation->data['draft']['payload']
                        : [];
                    $actionKey = $continuation->actionKey ?? '';
                    $input = $actionKey === 'recipes.create'
                        ? $this->recipeDraftPayloadMapper->toCreateInput($draft)
                        : (is_array($draft['input'] ?? null) ? $draft['input'] : $draft);
                    if ($actionKey === '' || $input === null) {
                        throw new \RuntimeException('The draft is no longer valid.');
                    }
                    $decision = $this->continuationActionDecision(
                        $actionKey,
                        $actionKey === 'recipes.create' ? ['recipe_draft' => $input] : $input,
                        'draft'
                    );
                }
                $this->logContinuation('conversation.continuation.resolved', $orchestrationContext, $continuation);
            } elseif ($continuation->status === 'ambiguous') {
                $this->logContinuation('conversation.continuation.ambiguous', $orchestrationContext, $continuation);
                $decision = ['intent' => 'continuation', 'interaction_mode' => 'continuation', 'routing' => ['source' => 'continuation']];
                $result = $this->continuationFeedback($context, $continuation, 'ambiguous');
            } elseif (in_array($continuation->status, ['invalid', 'expired'], true)) {
                $decision = ['intent' => 'continuation', 'interaction_mode' => 'continuation', 'routing' => ['source' => 'continuation']];
                $result = $this->continuationFeedback($context, $continuation, $continuation->status);
            } else {
                if (config('ai.routing.function_calling_v2', false)) {
                    $failureStage = 'capability_call_routing';
                    $capabilityCall = $this->capabilityFunctionRouter->route($routerContext);
                }
                $decision = $capabilityCall instanceof CapabilityCall
                    ? $this->capabilityCallDecision($capabilityCall)
                    : $this->hybridIntentRouter->route($routerContext);
            }
            $proposedActionKey = data_get($decision, 'routing.action_key') ?? data_get($decision, 'slots.action_key');
            $failureStage = 'routing_decision_validation';
            $validation = $this->routingDecisionValidator->validate($decision, $context);
            $decision = $validation['decision'];
            $routing = is_array($decision['routing'] ?? null) ? $decision['routing'] : [];
            $shape = $validation['shape'];
            $routingLog = [
                'conversation_id' => $conversation->id,
                'correlation_id' => $correlationId,
                'message_shape' => $shape['message_shape'] ?? null,
                'proposed_action_key' => $proposedActionKey,
                'final_action_key' => $routing['action_key'] ?? null,
                'reason_code' => $validation['reason_code'],
                'routing_source' => $routing['source'] ?? null,
                'workspace_id' => $workspace->id,
            ];
            Log::info('chat.message.shape_detected', $routingLog);
            Log::info('chat.routing.proposed', $routingLog);
            if ($validation['status'] === 'repaired') {
                Log::warning('chat.routing.rejected', $routingLog);
                Log::info('chat.routing.decision_repaired', $routingLog);
            }
            Log::info('chat.routing.validated', [...$routingLog, 'status' => $validation['status']]);
            Log::info('chat.routing.finalized', $routingLog);
            if ($validation['status'] === 'rejected') {
                $result = $this->recoveryResult(
                    $context['locale'],
                    'AI_ROUTING_INVALID',
                    $this->t($context['locale'], 'recovery.provider_validation')
                );
            }
            Log::info('ai.hybrid_router.resolved', [
                'action_policy' => $routing['action_policy'] ?? null,
                'ai_fallback_used' => (bool) ($routing['ai_fallback_used'] ?? false),
                'matched_pattern_id' => $routing['matched_pattern_id'] ?? null,
                'resolved_action_key' => $routing['action_key'] ?? null,
                'router_confidence' => $routing['confidence'] ?? null,
                'router_source' => $routing['source'] ?? null,
                'interaction_mode' => $decision['interaction_mode'] ?? null,
                'correlation_id' => $correlationId,
                'workspace_id' => $workspace->id,
            ]);
            $failureStage = 'tool_execution';
            $result ??= $this->executeDecision(
                $decision,
                $context,
                $assistantMessage,
                $aiRun,
                0
            );
            if ($continuation->source === 'draft' && !empty($result['confirmation']) && $continuation->continuationId) {
                $this->updateContinuationStatus($conversation, $continuation->continuationId, 'pending_action');
            }
            if ($continuation->status === 'resolved') {
                $this->logContinuation('conversation.continuation.resumed', $orchestrationContext, $continuation);
            }
            Log::info('ai.hybrid_router.execution', [
                'execution_result' => $result['workflow_status'] ?? (!empty($result['confirmation']) ? 'preview' : (!empty($result['tool_keys']) ? 'completed' : 'response')),
                'resolved_action_key' => $routing['action_key'] ?? null,
                'router_source' => $routing['source'] ?? null,
                'correlation_id' => $correlationId,
                'workspace_id' => $workspace->id,
            ]);

            $patternObservation = $this->observeSuccessfulPatternSafely($decision, $context, $result);
            if ($patternObservation !== null) {
                $result['pattern_observation'] = $patternObservation;
            }

            $this->recordConversationEntityRefs->execute(
                $conversation,
                $workspace,
                $result['entity_refs'] ?? []
            );

            $assistantMessage = $this->assistantMessageWriter->complete(
                $assistantMessage,
                $workspace,
                [
                    'blocks' => $result['blocks'],
                    'suggestions' => $result['suggestions'] ?? [],
                    'tool' => $result['tool'] ?? null,
                ],
                $locale,
                [
                    'entity_refs' => $result['entity_refs'] ?? [],
                    'orchestration' => [
                        'analysis_type' => $result['analysis_type'] ?? null,
                        'capability_request' => $result['capability_request'] ?? null,
                        'interaction_mode' => $result['interaction_mode'] ?? $decision['interaction_mode'] ?? null,
                        'intent' => $decision['intent'] ?? null,
                        'provider' => $this->resolvedProvider($decision, 'hybrid_router'),
                        'routing' => $decision['routing'] ?? null,
                        'pattern_observation' => $result['pattern_observation'] ?? null,
                        'tool_calls' => $result['tool_keys'] ?? [],
                    ],
                    'source' => 'assistant-response',
                ]
            );

            $this->completeAiRunSafely($aiRun, [
                'completed_at' => now(),
                'metadata' => [
                    ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                    'ai_fallback_used' => (bool) ($routing['ai_fallback_used'] ?? false),
                    'routing_source' => $routing['source'] ?? null,
                    'selected_action_key' => $routing['action_key'] ?? null,
                    'interaction_mode' => $result['interaction_mode'] ?? $decision['interaction_mode'] ?? null,
                    'safe_reason_code' => $result['workflow_status'] ?? null,
                ],
                'model_key' => $this->resolvedModel($decision, $aiRun->model_key),
                'provider' => $this->resolvedProvider($decision, $aiRun->provider),
                'latency_ms' => $this->latencyMilliseconds($aiRun->started_at),
                'status' => 'completed',
                'usage_json' => $decision['usage'] ?? null,
            ], $correlationId);

            return $assistantMessage;
        } catch (\Throwable $exception) {
            if ($orchestrationContext instanceof OrchestrationContext && $continuation instanceof ContinuationResolution && $continuation->status !== 'not_applicable') {
                $this->logContinuation('conversation.continuation.failed', $orchestrationContext, $continuation);
            }
            $publicError = (new ErrorResponseMapper())->map($exception, $locale, $correlationId);
            $errorPayload = $this->errorPayload($publicError);
            $errorCode = $publicError['error_code'];
            $validationMeta = [];
            if ($exception instanceof \Illuminate\Validation\ValidationException) {
                $errors = $exception->errors();
                $fieldPath = (string) (array_key_first($errors) ?? 'unknown');
                $validationMeta = [
                    'error_code' => 'validation_failed',
                    'field_path' => $fieldPath,
                    'reason_code' => $failureStage === 'capability_call_routing'
                        ? 'capability_call_rejected'
                        : 'validation_rejected',
                ];
            }

            Log::warning('ai.orchestrator.failed', [
                ...$this->providerDiagnosticMetadata($exception),
                'correlation_id' => $correlationId,
                'exception_class' => class_basename($exception),
                'internal_code' => $errorCode,
                'stage' => $failureStage,
                'validator' => match ($failureStage) {
                    'capability_call_routing' => 'CapabilityCallValidator',
                    'routing_decision_validation' => 'RoutingDecisionValidator',
                    'tool_execution' => 'ToolExecutor',
                    default => null,
                },
                ...$validationMeta,
            ]);

            $assistantMessage = $this->assistantMessageWriter->fail(
                $assistantMessage,
                $workspace,
                $errorCode,
                $publicError['message'],
                $errorPayload,
                $locale,
                [
                    'source' => 'assistant-response',
                ]
            );

            $this->completeAiRunSafely($aiRun, [
                'completed_at' => now(),
                'error_code' => $errorCode,
                'error_message' => $errorCode,
                'metadata' => [
                    ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                    'correlation_id' => $correlationId,
                    'failure_reason' => $errorCode,
                ],
                'status' => 'failed',
            ], $correlationId);

            return $assistantMessage;
        }
    }

    /**
     * Canonical chat path. Server-owned continuations are resolved first;
     * otherwise the model chooses from the registry, receives structured tool
     * results, and may choose the next tool. No local intent router, parser,
     * regex classifier, or entity fuzzy matcher participates in normal turns.
     */
    private function respondWithToolLoop(
        Conversation $conversation,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
        Message $userMessage,
        string $locale,
        string $timezone,
        string $correlationId
    ): Message {
        Log::info('ai.chat.message_received', [
            'conversation_id' => $conversation->id,
            'correlation_id' => $correlationId,
            'message_id' => $userMessage->id,
            'workspace_id' => $workspace->id,
        ]);
        $assistantMessage = $this->assistantMessageWriter->createPending(
            $conversation,
            $workspace,
            $locale,
            $userMessage,
            ['source' => 'assistant-response', 'orchestration_version' => 'tool-loop-v1']
        );
        $aiRun = $this->startRun(
            $assistantMessage,
            $userMessage,
            $workspace,
            $locale,
            $timezone,
            $correlationId
        );
        $aiRun->forceFill(['orchestrator_version' => 'tool-loop-v1'])->save();
        $contextObject = null;
        $responseId = null;
        $nextInput = [];
        $toolCount = 0;
        $toolKeys = [];
        $entityRefs = [];
        $lastToolResult = [];
        $supportingResults = [];
        $usage = [];
        $providerMetadata = [];
        $providerProtocolRecoveryAttempted = false;

        try {
            $contextObject = $this->buildContext(
                $conversation,
                $workspace,
                $membership,
                $user,
                $userMessage,
                $assistantMessage,
                $locale,
                $timezone,
                $correlationId,
            );
            $context = [
                ...$contextObject->toArray(),
                'message' => (string) ($userMessage->content_text ?? ''),
                'message_id' => $userMessage->id,
                'operational_context' => $this->operationalContextSnapshot($conversation, $workspace, $user),
            ];
            if ($this->shouldResetToolLoopContext(
                (string) ($userMessage->content_text ?? ''),
                (array) ($context['operational_context'] ?? [])
            )) {
                $context['entity_refs'] = [];
                $context['recent_entity_refs'] = [];
                $context['active_entities'] = [];
                $context['operational_context']['active_entity_refs'] = [];
                $context['operational_context']['last_operation'] = null;
            }
            $temporalContext = ($this->temporalContextResolver ?? app(TemporalContextResolver::class))->resolve(
                $workspace,
                $user,
                $locale,
                (array) ($context['active_entities'] ?? []),
            );
            $timezone = (string) ($temporalContext['timezone'] ?? 'UTC');
            $contextObject->timezone = $timezone;
            $context['timezone'] = $timezone;
            $context['temporal_context'] = $temporalContext;

            // Server-owned continuations always win over model routing. A
            // short reply such as "3 libras" or "confirmar" is not a new
            // tool-loop request when a clarification or confirmation is
            // already pending for this conversation.
            $continuation = $this->continuationResolver->resolve($contextObject);
            if ($continuation->status !== 'not_applicable') {
                return $this->respondToToolLoopContinuation(
                    $conversation,
                    $workspace,
                    $user,
                    $assistantMessage,
                    $aiRun,
                    $contextObject,
                    $context,
                    $continuation,
                    $locale,
                    $correlationId
                );
            }

            $openAIConversationService = $this->openAIConversationService ?? app(OpenAIConversationService::class);
            $toolProfileSelector = $this->toolProfileSelector ?? app(ToolProfileSelector::class);
            $openAIConversationId = $openAIConversationService->ensure(
                $conversation,
                $workspace,
                $user,
                $userMessage->id,
                [
                    'operational_context' => $context['operational_context'] ?? [],
                    'temporal' => $temporalContext,
                ]
            );
            if ($openAIConversationId !== null) {
                $context['openai_conversation_id'] = $openAIConversationId;
            }
            $context['pending_provider_tool_outputs'] = $this->conversationContinuationLifecycle
                ->pendingProviderToolOutputs($conversation);

            $profile = $toolProfileSelector->select($context, $this->toolRegistry->allMetadata());
            $definitions = $this->toolLoopDefinitions($profile['metadata']);
            $definitionMap = collect($profile['metadata'])->mapWithKeys(
                fn (array $definition): array => [str_replace('.', '_', (string) $definition['key']) => (string) $definition['key']]
            )->all();
            $maxIterations = max(1, (int) config('ai.max_orchestration_iterations', 5));
            $maxToolCalls = max(1, (int) config('ai.max_tool_calls_per_turn', 4));

            Log::info('ai.tool_loop.started', [
                'correlation_id' => $correlationId,
                'conversation_id' => $conversation->id,
                'profile' => $profile['profile'],
                'tool_count' => count($definitions),
                'workspace_id' => $workspace->id,
            ]);

            for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
                try {
                    $providerResult = $this->toolCallingProvider->toolTurn(
                        [
                            ...$context,
                            'tool_instructions' => $this->toolLoopInstructions($context, $profile['metadata']),
                            'tool_dynamic_context' => $this->toolLoopDynamicContext($context),
                            'prompt_cache_key' => $this->promptCacheKey($profile['profile']),
                        ],
                        $definitions,
                        $responseId,
                        $nextInput,
                    );
                } catch (\Throwable $exception) {
                    if ($providerProtocolRecoveryAttempted || !$this->isOrphanedProviderToolCall($exception)) {
                        throw $exception;
                    }

                    $providerProtocolRecoveryAttempted = true;
                    $openAIConversationService->resetAfterProviderProtocolError($conversation);
                    $this->conversationContinuationLifecycle->clearProviderToolOutputs($conversation);
                    $openAIConversationId = $openAIConversationService->ensure(
                        $conversation,
                        $workspace,
                        $user,
                        $userMessage->id,
                        [
                            'operational_context' => $context['operational_context'] ?? [],
                            'temporal' => $temporalContext,
                        ]
                    );
                    if ($openAIConversationId !== null) {
                        $context['openai_conversation_id'] = $openAIConversationId;
                    }
                    $context['pending_provider_tool_outputs'] = [];
                    $responseId = null;
                    $nextInput = [];

                    Log::warning('ai.tool_loop.provider_protocol_recovered', [
                        'correlation_id' => $correlationId,
                        'conversation_id' => $conversation->id,
                        'workspace_id' => $workspace->id,
                    ]);

                    $providerResult = $this->toolCallingProvider->toolTurn(
                        [
                            ...$context,
                            'tool_instructions' => $this->toolLoopInstructions($context, $profile['metadata']),
                            'tool_dynamic_context' => $this->toolLoopDynamicContext($context),
                            'prompt_cache_key' => $this->promptCacheKey($profile['profile']),
                        ],
                        $definitions,
                        $responseId,
                        $nextInput,
                    );
                }
                $consumedProviderCallIds = collect($context['pending_provider_tool_outputs'] ?? [])
                    ->map(fn (mixed $item): ?string => is_array($item) && isset($item['call_id'])
                        ? (string) $item['call_id']
                        : null)
                    ->filter()
                    ->values()
                    ->all();
                $this->conversationContinuationLifecycle->consumeProviderToolOutputs(
                    $conversation,
                    $consumedProviderCallIds
                );
                $context['pending_provider_tool_outputs'] = [];
                $responseId = is_string($providerResult['response_id'] ?? null)
                    ? $providerResult['response_id']
                    : $responseId;
                $providerMetadata = [
                    'model' => $providerResult['model'] ?? null,
                    'provider' => $providerResult['provider'] ?? 'openai',
                    'tool_profile' => $profile['profile'],
                    'cached_input_tokens' => data_get($providerResult, 'usage.input_tokens_details.cached_tokens'),
                ];
                $usage = $this->mergeUsage($usage, (array) ($providerResult['usage'] ?? []));
                $nextInput = [];
                $calls = collect($providerResult['output'] ?? [])
                    ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'function_call')
                    ->values()
                    ->all();

                if ($calls === []) {
                    $text = trim((string) ($providerResult['output_text'] ?? ''));
                    $result = $this->toolLoopFinalResult(
                        ToolLoopResultComposer::compose($supportingResults, $lastToolResult),
                        $text,
                        $locale
                    );
                    $result['entity_refs'] = $entityRefs !== [] ? $entityRefs : ($result['entity_refs'] ?? []);
                    $result['tool_keys'] = $toolKeys;
                    $result['interaction_mode'] = 'tool_loop';
                    $result['usage'] = $usage;
                    $this->recordAndCompleteToolLoop(
                        $conversation,
                        $workspace,
                        $assistantMessage,
                        $aiRun,
                        $result,
                        $locale,
                        $correlationId,
                        $providerMetadata,
                        $usage,
                        $toolKeys
                    );

                    return $assistantMessage->fresh('blocks');
                }

                // Persistent Conversations already retain the provider
                // response items. Only the function outputs are sent back;
                // the stateless compatibility path keeps the old replay.
                $nextInput = filled($context['openai_conversation_id'] ?? null)
                    ? []
                    : collect($providerResult['output'] ?? [])
                        ->filter(fn (mixed $item): bool => is_array($item))
                        ->values()
                        ->all();

                foreach ($calls as $call) {
                    if ($toolCount >= $maxToolCalls) {
                        throw ValidationException::withMessages(['tools' => ['The tool call limit was reached.']]);
                    }
                    $functionName = (string) ($call['name'] ?? '');
                    $actionKey = $definitionMap[$functionName] ?? null;
                    $callId = is_string($call['call_id'] ?? null) && $call['call_id'] !== ''
                        ? $call['call_id']
                        : (string) Str::ulid();
                    $arguments = json_decode((string) ($call['arguments'] ?? '{}'), true);
                    $arguments = is_array($arguments) ? $arguments : null;
                    Log::info('ai.tool_call.requested', [
                        'action_key' => $actionKey,
                        'call_id' => $callId,
                        'correlation_id' => $correlationId,
                        'iteration' => $iteration + 1,
                        'position' => $toolCount,
                        'workspace_id' => $workspace->id,
                    ]);

                    if ($actionKey === null || $arguments === null) {
                        $toolResult = [
                            'ok' => false,
                            'code' => $actionKey === null ? 'TOOL_NOT_FOUND' : 'INVALID_TOOL_ARGUMENTS',
                            'message_for_model' => 'The requested tool or its arguments are invalid.',
                            'retryable' => true,
                            'allowed_next_actions' => ['ask_user_for_clarification'],
                            'safe_details' => [],
                        ];
                    } else {
                        $tool = $this->toolRegistry->resolve($actionKey);
                        $rawResult = null;
                        $referenceError = $this->toolLoopReferenceError($tool, $arguments);
                        if ($referenceError !== null) {
                            $toolKeys[] = $actionKey;
                            $lastToolResult = [
                                'status' => 'failed',
                                'blocks' => [],
                                'entity_refs' => [],
                                'result_ref_json' => [],
                            ];
                            Log::warning('ai.tool_call.rejected', [
                                'action_key' => $actionKey,
                                'call_id' => $callId,
                                'correlation_id' => $correlationId,
                                'error_code' => $referenceError['code'] ?? 'INVALID_TOOL_REFERENCE',
                                'input_keys' => array_keys($arguments),
                                'workspace_id' => $workspace->id,
                            ]);
                            $toolResult = $referenceError;
                        } else {
                            $toolInput = $actionKey === 'recipes.create'
                                ? ['recipe_draft' => $this->mergePendingRecipeDraft($conversation, $arguments)]
                                : $arguments;
                            try {
                                $rawResult = $this->runTool(
                                    [...$context, 'provider_call_id' => $callId, 'tool_loop' => true],
                                    $assistantMessage,
                                    $aiRun,
                                    $toolCount,
                                    $actionKey,
                                    $toolInput,
                                    $this->toolLoopEntity($tool, $arguments),
                                );
                                $lastToolResult = $rawResult;
                                $toolKeys[] = $actionKey;
                                $entityRefs = [...$entityRefs, ...(array) ($rawResult['entity_refs'] ?? [])];
                                if (($tool['mode'] ?? null) === 'read') {
                                    $supportingResults[] = [
                                        'blocks' => (array) ($rawResult['blocks'] ?? []),
                                        'entity_refs' => (array) ($rawResult['entity_refs'] ?? []),
                                    ];
                                }
                                $toolResult = $this->toolResultForModel($tool, $rawResult);
                                $this->persistOperationalContext($conversation, $workspace, $user, $entityRefs, $actionKey, $rawResult);
                                Log::info('ai.tool_call.result', [
                                    'action_key' => $actionKey,
                                    'call_id' => $callId,
                                    'correlation_id' => $correlationId,
                                    'result_status' => $rawResult['status']
                                        ?? $rawResult['workflow_status']
                                        ?? (is_array($rawResult['confirmation'] ?? null) ? 'confirmation_required' : null),
                                    'workspace_id' => $workspace->id,
                                ]);
                            } catch (\Throwable $exception) {
                                $toolKeys[] = $actionKey;
                                $lastToolResult = [
                                    'status' => 'failed',
                                    'blocks' => [],
                                    'entity_refs' => [],
                                    'result_ref_json' => [],
                                ];
                                $mappedError = (new ErrorResponseMapper())->map($exception, $locale, $correlationId);
                                Log::warning('ai.tool_call.failed', [
                                    'action_key' => $actionKey,
                                    'call_id' => $callId,
                                    'correlation_id' => $correlationId,
                                    'error_code' => $mappedError['error_code'],
                                    'exception_class' => class_basename($exception),
                                    'input_keys' => array_keys($arguments),
                                    'validation_fields' => method_exists($exception, 'errors')
                                        ? array_keys((array) $exception->errors())
                                        : [],
                                    'workspace_id' => $workspace->id,
                                ]);
                                $toolResult = (new ErrorResponseMapper())->forModel($exception, $locale, $correlationId);
                            }
                        }
                    }
                    $toolCount++;
                    $currentToolResult = isset($rawResult) && is_array($rawResult) ? $rawResult : [];
                    $status = $currentToolResult['status']
                        ?? $currentToolResult['workflow_status']
                        ?? (
                        is_array($currentToolResult['confirmation'] ?? null)
                            ? 'confirmation_required'
                            : null
                        );
                    if ($status === 'clarification_required') {
                        // Keep the provider call linked to the pending
                        // clarification, but let the model receive the tool
                        // result so it can phrase the question naturally.
                        $continuationId = data_get($lastToolResult, 'clarification.clarification_id');
                        $this->conversationContinuationLifecycle->registerPendingProviderToolCall(
                            $conversation,
                            $callId,
                            is_string($continuationId) ? $continuationId : null,
                            (string) ($actionKey ?? $functionName)
                        );
                    }
                    if ($status === 'confirmation_required') {
                        $continuationId = data_get($lastToolResult, 'confirmation.confirmation_id')
                            ?? data_get($lastToolResult, 'confirmation.id');
                        $this->conversationContinuationLifecycle->registerPendingProviderToolCall(
                            $conversation,
                            $callId,
                            is_string($continuationId) ? $continuationId : null,
                            (string) ($actionKey ?? $functionName)
                        );
                        $composedResult = ToolLoopResultComposer::compose($supportingResults, $lastToolResult);
                        $this->persistConfirmationPresentationContext(
                            $workspace,
                            $lastToolResult,
                            $supportingResults
                        );
                        $result = [
                            'blocks' => $composedResult['blocks'] ?? [],
                            'entity_refs' => $entityRefs,
                            'suggestions' => [],
                            'tool_keys' => $toolKeys,
                            'workflow_status' => $status,
                            'interaction_mode' => 'tool_loop',
                            'usage' => $usage,
                        ];
                        $this->recordAndCompleteToolLoop(
                            $conversation,
                            $workspace,
                            $assistantMessage,
                            $aiRun,
                            $result,
                            $locale,
                            $correlationId,
                            $providerMetadata,
                            $usage,
                            $toolKeys
                        );

                        return $assistantMessage->fresh('blocks');
                    }
                    $nextInput[] = [
                        'type' => 'function_call_output',
                        'call_id' => $callId,
                        'output' => json_encode($toolResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ];
                }
                if ($responseId === null && blank($context['openai_conversation_id'] ?? null)) {
                    throw new \RuntimeException('The tool loop response did not contain a continuation id.');
                }
            }

            throw ValidationException::withMessages(['tools' => ['The tool loop did not reach a final response.']]);
        } catch (\Throwable $exception) {
            $publicError = (new ErrorResponseMapper())->map($exception, $locale, $correlationId);
            Log::warning('ai.tool_loop.failed', [
                'correlation_id' => $correlationId,
                'exception_class' => class_basename($exception),
                'error_code' => $publicError['error_code'],
                'workspace_id' => $workspace->id,
            ]);
            $this->assistantMessageWriter->fail(
                $assistantMessage,
                $workspace,
                $publicError['error_code'],
                $publicError['message'],
                $this->errorPayload($publicError),
                $locale,
                ['source' => 'assistant-response', 'orchestration_version' => 'tool-loop-v1']
            );
            $this->completeAiRunSafely($aiRun, [
                'completed_at' => now(),
                'error_code' => $publicError['error_code'],
                'error_message' => $publicError['error_code'],
                'status' => 'failed',
                'usage_json' => $usage,
            ], $correlationId);

            return $assistantMessage->fresh('blocks');
        }
    }

    private function toolLoopEnabled(): bool
    {
        return (bool) config('ai.routing.tool_loop_enabled', true);
    }

    /**
     * Resolve a server-owned continuation before invoking the model tool
     * loop. This deliberately reuses the existing continuation, decision,
     * and ToolExecutor lifecycle instead of creating a second pipeline.
     *
     * @param array<string, mixed> $context
     */
    private function respondToToolLoopContinuation(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        Message $assistantMessage,
        AiRun $aiRun,
        OrchestrationContext $orchestrationContext,
        array $context,
        ContinuationResolution $continuation,
        string $locale,
        string $correlationId
    ): Message {
        $this->logContinuation('conversation.continuation.detected', $orchestrationContext, $continuation);

        $decision = null;
        $result = null;
        $toolKeys = [];

        if ($continuation->status === 'resolved' && $continuation->source === 'cancellation') {
            $result = $this->cancelToolLoopContinuation($conversation, $workspace, $user, $continuation, $context);
            $this->logContinuation('conversation.continuation.cancelled', $orchestrationContext, $continuation);
        } elseif ($continuation->status === 'resolved') {
            if ($continuation->source === 'clarification') {
                $resolved = $this->pendingClarificationResolver->resolve(
                    $conversation,
                    $workspace->id,
                    $continuation->continuationId ?? '',
                    (array) ($continuation->data['input'] ?? []),
                    $user->id
                );
                $providerCallId = $this->conversationContinuationLifecycle->pendingProviderToolCallId(
                    $conversation,
                    $continuation->continuationId
                );
                if ($providerCallId !== null) {
                    $context['provider_call_id'] = $providerCallId;
                }
                $actionKey = $continuation->actionKey
                    ?? ($resolved['clarification']['action_key'] ?? null)
                    ?? ($resolved['clarification']['workflow'] ?? '');
                $resolvedInput = $actionKey === 'recipes.create'
                    ? ['recipe_draft' => $resolved['draft']]
                    : (is_array($resolved['input'] ?? null) ? $resolved['input'] : []);
                $decision = $this->continuationActionDecision($actionKey, $resolvedInput, 'clarification');
            } elseif ($continuation->source === 'confirmation') {
                $decision = $this->continuationActionDecision(
                    $continuation->actionKey ?? '',
                    [],
                    'confirmation'
                );
                $result = $this->resumeConfirmation($continuation, $context);
                $result = $this->continueToolLoopAfterConfirmation(
                    $conversation,
                    $workspace,
                    $user,
                    $assistantMessage,
                    $aiRun,
                    $context,
                    $result,
                    $locale
                );
                $toolKeys = array_values(array_filter([$continuation->actionKey]));
                $toolKeys = array_values(array_unique([
                    ...$toolKeys,
                    ...(array) ($result['tool_keys'] ?? []),
                ]));
            } elseif ($continuation->source === 'draft') {
                $draft = is_array($continuation->data['draft']['payload'] ?? null)
                    ? $continuation->data['draft']['payload']
                    : [];
                $actionKey = $continuation->actionKey ?? '';
                $input = $actionKey === 'recipes.create'
                    ? $this->recipeDraftPayloadMapper->toCreateInput($draft)
                    : (is_array($draft['input'] ?? null) ? $draft['input'] : $draft);
                if ($actionKey === '' || $input === null) {
                    throw new \RuntimeException('The draft is no longer valid.');
                }
                $decision = $this->continuationActionDecision(
                    $actionKey,
                    $actionKey === 'recipes.create' ? ['recipe_draft' => $input] : $input,
                    'draft'
                );
            }
            $this->logContinuation('conversation.continuation.resolved', $orchestrationContext, $continuation);
        } elseif (in_array($continuation->status, ['ambiguous', 'invalid', 'expired'], true)) {
            $decision = [
                'intent' => 'continuation',
                'interaction_mode' => 'continuation',
                'routing' => ['source' => 'continuation'],
            ];
            $result = $this->continuationFeedback($context, $continuation, $continuation->status);
            $this->logContinuation('conversation.continuation.'.$continuation->status, $orchestrationContext, $continuation);
        }

        if ($decision !== null && $result === null) {
            $validation = $this->routingDecisionValidator->validate($decision, $context);
            $decision = $validation['decision'];
            if ($validation['status'] === 'rejected') {
                $result = $this->recoveryResult(
                    $locale,
                    'AI_ROUTING_INVALID',
                    $this->t($locale, 'recovery.provider_validation')
                );
            } else {
                $result = $this->executeDecision(
                    $decision,
                    $context,
                    $assistantMessage,
                    $aiRun,
                    0
                );
                if ($continuation->source === 'clarification'
                    && $continuation->continuationId !== null
                    && !is_array($result['confirmation'] ?? null)
                    && ($result['workflow_status'] ?? $result['status'] ?? null) !== 'clarification_required') {
                    $this->conversationContinuationLifecycle->resolvePendingProviderToolCall(
                        $conversation,
                        $continuation->continuationId,
                        (string) ($continuation->actionKey ?? ''),
                        $result
                    );
                }
                $toolKeys = (array) ($result['tool_keys'] ?? []);
            }
        }

        $result ??= $this->recoveryResult(
            $locale,
            'AI_EMPTY_RESPONSE',
            $this->t($locale, 'recovery.internal_error')
        );
        $result['tool_keys'] = $toolKeys !== [] ? $toolKeys : (array) ($result['tool_keys'] ?? []);
        $result['workflow_status'] ??= $result['status'] ?? (
            !empty($result['confirmation']) ? 'confirmation_required' : 'completed'
        );

        $this->recordAndCompleteToolLoop(
            $conversation,
            $workspace,
            $assistantMessage,
            $aiRun,
            $result,
            $locale,
            $correlationId,
            ['model' => $aiRun->model_key, 'provider' => 'server'],
            [],
            $result['tool_keys']
        );

        return $assistantMessage->fresh('blocks');
    }

    /**
     * Resume the provider conversation after a confirmation has executed.
     * This lets a single user request continue through dependent reads or
     * writes without replaying the original intent or creating a second
     * orchestration pipeline.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $initialResult
     * @return array<string, mixed>
     */
    private function continueToolLoopAfterConfirmation(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        Message $assistantMessage,
        AiRun $aiRun,
        array $context,
        array $initialResult,
        string $locale
    ): array {
        if (!$this->toolCallingProvider instanceof ToolCallingProvider) {
            return $initialResult;
        }

        $pendingOutputs = $this->conversationContinuationLifecycle
            ->pendingProviderToolOutputs($conversation);
        $openAIConversationId = trim((string) ($conversation->openai_conversation_id ?? ''));
        if ($pendingOutputs === [] || $openAIConversationId === '') {
            return $initialResult;
        }

        $context['openai_conversation_id'] = $openAIConversationId;
        // The confirmation itself was already handled by the server. The
        // provider receives the resolved function output, not "confirm" as a
        // new intent.
        $context['message'] = '';
        $context['pending_provider_tool_outputs'] = $pendingOutputs;
        $context['tool_dynamic_context'] = $this->toolLoopDynamicContext($context);
        $context['tool_instructions'] = $this->toolLoopInstructions($context, $this->toolRegistry->allMetadata())
            . "\nContinuation contract: the confirmed write has already executed. Do not repeat it. Continue every remaining instruction from the original user request. Use exact IDs from the confirmed result or a read tool before updating a record. If a tool rejects arguments, repair the arguments once from its safe validation details; do not resend the same arguments. Complete remaining writes before final reads. Return a concise final response when all requested work is complete.";
        $context['prompt_cache_key'] = $this->promptCacheKey('all');

        $metadata = $this->toolRegistry->allMetadata();
        $definitions = $this->toolLoopDefinitions($metadata);
        $definitionMap = collect($metadata)->mapWithKeys(
            fn (array $definition): array => [str_replace('.', '_', (string) $definition['key']) => (string) $definition['key']]
        )->all();
        $maxIterations = max(1, (int) config('ai.max_orchestration_iterations', 8));
        $maxToolCalls = max(1, (int) config('ai.max_tool_calls_per_turn', 12));
        $responseId = null;
        $nextInput = [];
        $toolCount = count((array) ($initialResult['tool_keys'] ?? []));
        $toolKeys = (array) ($initialResult['tool_keys'] ?? []);
        $entityRefs = (array) ($initialResult['entity_refs'] ?? []);
        $lastResult = $initialResult;
        $supportingResults = [];

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $providerResult = $this->toolCallingProvider->toolTurn(
                $context,
                $definitions,
                $responseId,
                $nextInput
            );
            $consumedProviderCallIds = collect($context['pending_provider_tool_outputs'] ?? [])
                ->map(fn (mixed $item): ?string => is_array($item) && isset($item['call_id'])
                    ? (string) $item['call_id']
                    : null)
                ->filter()
                ->values()
                ->all();
            $this->conversationContinuationLifecycle->consumeProviderToolOutputs(
                $conversation,
                $consumedProviderCallIds
            );
            $context['pending_provider_tool_outputs'] = [];
            $responseId = is_string($providerResult['response_id'] ?? null)
                ? $providerResult['response_id']
                : $responseId;
            $nextInput = [];
            $calls = collect($providerResult['output'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'function_call')
                ->values()
                ->all();

            if ($calls === []) {
                $result = $this->toolLoopFinalResult(
                    ToolLoopResultComposer::compose($supportingResults, $lastResult),
                    trim((string) ($providerResult['output_text'] ?? '')),
                    $locale
                );
                $result['entity_refs'] = $entityRefs !== [] ? $entityRefs : ($result['entity_refs'] ?? []);
                $result['tool_keys'] = array_values(array_unique($toolKeys));
                $result['interaction_mode'] = 'tool_loop';
                return $result;
            }

            foreach ($calls as $call) {
                if ($toolCount >= $maxToolCalls) {
                    throw ValidationException::withMessages(['tools' => ['The tool call limit was reached.']]);
                }
                $functionName = (string) ($call['name'] ?? '');
                $actionKey = $definitionMap[$functionName] ?? null;
                $callId = is_string($call['call_id'] ?? null) && $call['call_id'] !== ''
                    ? $call['call_id']
                    : (string) Str::ulid();
                $arguments = json_decode((string) ($call['arguments'] ?? '{}'), true);
                $arguments = is_array($arguments) ? $arguments : null;
                Log::info('ai.continuation.tool_call.requested', [
                    'action_key' => $actionKey,
                    'call_id' => $callId,
                    'correlation_id' => $context['correlation_id'] ?? null,
                    'iteration' => $iteration + 1,
                    'position' => $toolCount,
                    'workspace_id' => $workspace->id,
                ]);
                if ($actionKey === null || $arguments === null) {
                    $toolResult = [
                        'ok' => false,
                        'code' => $actionKey === null ? 'TOOL_NOT_FOUND' : 'INVALID_TOOL_ARGUMENTS',
                        'message_for_model' => 'The requested tool or its arguments are invalid.',
                        'retryable' => true,
                        'allowed_next_actions' => ['ask_user_for_clarification'],
                        'safe_details' => [],
                    ];
                } else {
                    $tool = $this->toolRegistry->resolve($actionKey);
                    $referenceError = $this->toolLoopReferenceError($tool, $arguments);
                    if ($referenceError !== null) {
                        $toolResult = $referenceError;
                        $lastResult = ['status' => 'failed', 'blocks' => [], 'entity_refs' => []];
                        Log::warning('ai.continuation.tool_call.rejected', [
                            'action_key' => $actionKey,
                            'call_id' => $callId,
                            'correlation_id' => $context['correlation_id'] ?? null,
                            'error_code' => $referenceError['code'] ?? 'INVALID_TOOL_REFERENCE',
                            'input_keys' => array_keys($arguments),
                            'workspace_id' => $workspace->id,
                        ]);
                    } else {
                        try {
                            $rawResult = $this->runTool(
                                [...$context, 'provider_call_id' => $callId, 'tool_loop' => true],
                                $assistantMessage,
                                $aiRun,
                                $toolCount,
                                $actionKey,
                                $actionKey === 'recipes.create'
                                    ? ['recipe_draft' => $this->mergePendingRecipeDraft($conversation, $arguments)]
                                    : $arguments,
                                $this->toolLoopEntity($tool, $arguments),
                            );
                            $lastResult = $rawResult;
                            $toolKeys[] = $actionKey;
                            $entityRefs = [...$entityRefs, ...(array) ($rawResult['entity_refs'] ?? [])];
                            if (($tool['mode'] ?? null) === 'read') {
                                $supportingResults[] = [
                                    'blocks' => (array) ($rawResult['blocks'] ?? []),
                                    'entity_refs' => (array) ($rawResult['entity_refs'] ?? []),
                                ];
                            }
                            $toolResult = $this->toolResultForModel($tool, $rawResult);
                            $this->persistOperationalContext($conversation, $workspace, $user, $entityRefs, $actionKey, $rawResult);
                            Log::info('ai.continuation.tool_call.result', [
                                'action_key' => $actionKey,
                                'call_id' => $callId,
                                'correlation_id' => $context['correlation_id'] ?? null,
                                'result_status' => $rawResult['status']
                                    ?? $rawResult['workflow_status']
                                    ?? (is_array($rawResult['confirmation'] ?? null) ? 'confirmation_required' : null),
                                'workspace_id' => $workspace->id,
                            ]);
                            $status = $rawResult['status'] ?? $rawResult['workflow_status'] ?? null;
                            if ($status === 'clarification_required') {
                                $continuationId = data_get($rawResult, 'clarification.clarification_id');
                                $this->conversationContinuationLifecycle->registerPendingProviderToolCall(
                                    $conversation,
                                    $callId,
                                    is_string($continuationId) ? $continuationId : null,
                                    $actionKey
                                );
                            }
                            if ($status === 'confirmation_required') {
                                $this->conversationContinuationLifecycle->registerPendingProviderToolCall(
                                    $conversation,
                                    $callId,
                                    data_get($rawResult, 'confirmation.confirmation_id'),
                                    $actionKey
                                );
                                $lastResult['tool_keys'] = array_values(array_unique($toolKeys));
                                $lastResult['entity_refs'] = $entityRefs;
                                return $lastResult;
                            }
                        } catch (\Throwable $exception) {
                            $lastResult = ['status' => 'failed', 'blocks' => [], 'entity_refs' => []];
                            $toolResult = (new ErrorResponseMapper())->forModel($exception, $locale, (string) ($context['correlation_id'] ?? ''));
                            Log::warning('ai.continuation.tool_call.failed', [
                                'action_key' => $actionKey,
                                'call_id' => $callId,
                                'correlation_id' => $context['correlation_id'] ?? null,
                                'error_code' => $toolResult['code'] ?? 'TOOL_FAILED',
                                'exception_class' => class_basename($exception),
                                'input_keys' => array_keys($arguments),
                                'validation_fields' => method_exists($exception, 'errors')
                                    ? array_keys((array) $exception->errors())
                                    : [],
                                'workspace_id' => $workspace->id,
                            ]);
                        }
                    }
                }
                $toolCount++;
                $nextInput[] = [
                    'type' => 'function_call_output',
                    'call_id' => $callId,
                    'output' => json_encode($toolResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ];
            }
            $context['pending_provider_tool_outputs'] = [];
        }

        return $lastResult;
    }

    /** @param array<string, mixed> $operationalContext */
    private function shouldResetToolLoopContext(string $message, array $operationalContext): bool
    {
        $normalized = Str::lower(Str::ascii(trim($message)));
        if ($normalized === '' || preg_match('/\b(otra cosa|new topic|different topic|start over|empecemos de nuevo)\b/u', $normalized) === 1) {
            return true;
        }

        $activeTypes = collect($operationalContext['active_entity_refs'] ?? [])
            ->filter(fn (mixed $reference): bool => is_array($reference) && ($reference['role'] ?? null) === 'active')
            ->map(fn (array $reference): string => (string) ($reference['type'] ?? ''))
            ->filter()
            ->values()
            ->all();
        if ($activeTypes === []) {
            return false;
        }

        $moduleTerms = [
            'recipe' => ['recipe', 'receta'],
            'menu' => ['menu', 'menus'],
            'event' => ['event', 'evento', 'eventos'],
            'task' => ['task', 'tarea', 'tareas'],
            'prep' => ['prep', 'preparacion', 'preparaciones'],
            'client' => ['client', 'cliente', 'clientes'],
            'contact' => ['contact', 'contacto', 'contactos'],
            'venue' => ['venue', 'lugar', 'lugares'],
            'team' => ['team', 'equipo', 'equipos'],
        ];
        $activeModules = collect($activeTypes)->map(fn (string $type): string => match ($type) {
            'recipe' => 'recipe',
            'menu', 'menu_item' => 'menu',
            'event' => 'event',
            'task' => 'task',
            'prep_list', 'prep_item' => 'prep',
            'client' => 'client',
            'contact' => 'contact',
            'venue' => 'venue',
            'team', 'station', 'shift', 'availability' => 'team',
            default => $type,
        })->unique()->all();

        foreach ($moduleTerms as $module => $terms) {
            if (collect($activeModules)->contains($module)) {
                continue;
            }
            foreach ($terms as $term) {
                if (preg_match('/\b'.preg_quote($term, '/').'\b/u', $normalized) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<int, array<string, mixed>> */
    /** @param array<int, array<string, mixed>> $metadata */
    private function toolLoopDefinitions(array $metadata = []): array
    {
        $factory = new OpenAiFunctionSchemaFactory();
        $registry = isset($this->toolRegistry) ? $this->toolRegistry : new ToolRegistry();
        $metadata = $metadata !== [] ? $metadata : $registry->allMetadata();

        return collect($metadata)
            ->map(fn (array $metadata): array => $factory->make([
                'action_key' => $metadata['key'],
                'description' => trim(sprintf(
                    '%s Permission: %s. Confirmation required: %s. Side effects: %s.',
                    $metadata['description'],
                    $metadata['permission'] ?? 'server authorization',
                    ($metadata['requires_confirmation'] ?? false) ? 'yes' : 'no',
                    ($metadata['mode'] ?? 'read') === 'read' ? 'none' : 'writes workspace data'
                )),
                'input_schema' => $metadata['input_schema'] ?? [],
            ]))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $context */
    /** @param array<int, array<string, mixed>> $metadata */
    private function toolLoopInstructions(array $context, array $metadata): string
    {
        return implode("\n", [
            (string) ($context['system_instructions'] ?? ''),
            'You are the sole conversational decision maker for Humoo. Use only the supplied tools.',
            'Resolve natural-language references with the supplied tools, preserve the active context, and use exact stable IDs returned by the server.',
            'For a write request, call the matching write capability and include all requested changes; do not finish after a preparatory lookup.',
            'When the user requests both information and a change, complete both parts in order and return the read result together with the final write result.',
            'When the user requests multiple independent writes of the same entity, use the grouped capability when one is available and preserve every requested item. Never silently reduce a plural request to the first item.',
            'Use tool results as workspace facts. Never invent records, IDs, permissions, or completed writes.',
            'A tool result is an instruction to continue reasoning, not an automatic final answer. Inspect its safe details and call the next required capability when the user request is not complete.',
            'Do not repeat an identical lookup when its result is already available in the current turn; use the returned records and stable IDs.',
            'If a tool rejects input, read validation_errors and missing_fields, correct the arguments when possible, or ask the user only for information that cannot be derived safely.',
            'If a tool reports a dependency, call the capability that can satisfy that dependency, then resume the original request with the returned stable IDs. Do not abandon a multi-step request after the first validation response.',
            'If the backend asks for clarification, preserve the pending operation and ask one concise natural-language question; after the user answers, continue the operation.',
            'The registered component is authoritative when available. If no component is available or it cannot render, return a concise natural-language text response.',
            'Writes are previews until explicit confirmation; never claim completion from a preview.',
            'Preserve authoritative working state in operational_context unless the user explicitly changes it.',
            'Treat temporal context as authoritative. The model interprets relative dates and times, and must send concrete ISO-8601 values plus the supplied IANA timezone to tools.',
            $this->toolRegistry->modelContract($metadata),
        ]);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function toolLoopDynamicContext(array $context): array
    {
        $dynamic = [
            'operational_context' => $context['operational_context'] ?? [],
            'temporal' => $context['temporal_context'] ?? [],
        ];

        return $dynamic;
    }

    private function promptCacheKey(string $profile): string
    {
        $base = trim((string) config('ai.providers.openai.prompt_cache_key', 'humoo-agent-v1'));

        return $base === '' ? '' : $base.':'.trim($profile, ':');
    }

    /** @param array<string, mixed> $tool @param array<string, mixed> $arguments */
    private function toolLoopReferenceError(array $tool, array $arguments): ?array
    {
        $collectionRead = str_ends_with($tool['key'], '.list')
            || str_ends_with($tool['key'], '.search')
            || in_array($tool['key'], ['menus.search', 'tasks.mine', 'workspace.detail', 'notifications.unread_count', 'notification_preferences.list'], true);
        if ($collectionRead || in_array($tool['key'], ['notifications.read_all', 'notification_preferences.update', 'workspace.update'], true)) {
            return null;
        }

        $pairs = [
            'recipe_search' => 'recipe_id', 'menu_search' => 'menu_id', 'menu_item_search' => 'menu_item_id',
            'item_search' => 'item_id', 'task_search' => 'task_id', 'event_search' => 'event_id',
            'client_search' => 'client_id', 'contact_search' => 'contact_id', 'venue_search' => 'venue_id',
            'document_search' => 'document_id', 'beo_search' => 'beo_id', 'prep_list_search' => 'prep_list_id',
            'prep_item_search' => 'prep_item_id', 'team_search' => 'team_id', 'station_search' => 'station_id',
            'shift_search' => 'shift_id', 'member_search' => 'membership_id', 'assignee_search' => 'assignment_membership_id',
            'target_section_search' => 'target_section_id',
        ];
        $bulkTaskSelector = in_array($tool['key'], ['tasks.update', 'tasks.status.update', 'tasks.complete', 'tasks.delete'], true)
            && blank($arguments['task_id'] ?? null)
            && (filled($arguments['task_ids'] ?? null)
                || filled($arguments['search'] ?? null)
                || filled($arguments['task_search'] ?? null)
                || filled($arguments['due_from'] ?? null)
                || filled($arguments['due_to'] ?? null));
        $assignmentBySearch = $tool['key'] === 'tasks.assign'
            && (filled($arguments['task_search'] ?? null)
                || filled($arguments['search'] ?? null)
                || filled($arguments['task_ids'] ?? null))
            && (filled($arguments['member_search'] ?? null) || filled($arguments['membership_id'] ?? null));
        foreach ($pairs as $searchKey => $idKey) {
            if (filled($arguments[$searchKey] ?? null) && blank($arguments[$idKey] ?? null)) {
                $searchResolvedByCreateContract = ($tool['operation_type'] ?? null) === 'create'
                    && in_array($searchKey, (array) ($tool['reference_fields'] ?? []), true);
                $taskRelationshipSearch = in_array($tool['key'], ['tasks.update', 'tasks.status.update', 'tasks.complete'], true)
                    && in_array($searchKey, ['member_search', 'team_search', 'station_search', 'event_search'], true);
                if (($searchKey === 'task_search' && ($bulkTaskSelector || $assignmentBySearch))
                    || ($searchKey === 'member_search' && $tool['key'] === 'tasks.assign')
                    || $taskRelationshipSearch
                    || $searchResolvedByCreateContract) {
                    continue;
                }
                return [
                    'ok' => false,
                    'code' => 'ENTITY_ID_REQUIRED',
                    'message_for_model' => 'This tool requires an exact stable ID. Use the matching search or list tool first, then retry with the returned ID.',
                    'retryable' => true,
                    'allowed_next_actions' => [$this->searchActionForTool($tool)],
                    'safe_details' => ['required_id' => $idKey],
                ];
            }
        }

        if ($bulkTaskSelector || $assignmentBySearch) {
            return null;
        }

        if (($tool['target_entity_required'] ?? false) && !collect($pairs)->contains(
            fn (string $idKey): bool => filled($arguments[$idKey] ?? null)
        ) && ($tool['operation_type'] ?? null) !== 'create') {
            return [
                'ok' => false,
                'code' => 'ENTITY_ID_REQUIRED',
                'message_for_model' => 'This operation requires an exact stable entity ID from a prior tool result.',
                'retryable' => true,
                'allowed_next_actions' => [$this->searchActionForTool($tool)],
                'safe_details' => [],
            ];
        }

        return null;
    }

    /** @param array<string, mixed> $tool */
    private function searchActionForTool(array $tool): string
    {
        return match ($tool['entity_type'] ?? null) {
            'recipe' => 'recipes.list', 'menu', 'menu_item' => 'menus.search', 'event' => 'events.list',
            'task' => 'tasks.list', 'client' => 'clients.list', 'contact' => 'contacts.list', 'venue' => 'venues.list',
            'document' => 'documents.list', 'beo' => 'beos.list', 'prep_list', 'prep_item' => 'prep.list',
            'team', 'station', 'shift', 'availability' => 'teams.list', 'membership' => 'members.list',
            default => 'ask_user_for_clarification',
        };
    }

    /** @param array<string, mixed> $tool @param array<string, mixed> $arguments */
    private function toolLoopEntity(array $tool, array $arguments): ?array
    {
        if (($tool['operation_type'] ?? null) === 'create') {
            return null;
        }
        $id = collect([
            'recipe_id', 'menu_id', 'menu_item_id', 'item_id', 'task_id', 'event_id', 'client_id', 'contact_id',
            'venue_id', 'document_id', 'beo_id', 'prep_list_id', 'prep_item_id', 'team_id', 'station_id', 'shift_id',
            'membership_id',
        ])->first(fn (string $key): bool => filled($arguments[$key] ?? null));
        if ($id === null) {
            return null;
        }

        return [
            'id' => (string) $arguments[$id],
            'type' => $tool['entity_type'],
            'version' => $arguments['version'] ?? $arguments['expected_revision'] ?? 1,
        ];
    }

    /** @param array<string, mixed> $conversationMetadata */
    private function operationalContextSnapshot(Conversation $conversation, Workspace $workspace, User $user): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = is_array($metadata['ai_operational_context'] ?? null) ? $metadata['ai_operational_context'] : [];

        return [
            'version' => 1,
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspace->id,
            'actor_id' => $user->id,
            'active_entity_refs' => $this->compactEntityRefs(
                is_array($state['active_entity_refs'] ?? null) ? $state['active_entity_refs'] : []
            ),
            'draft' => $state['draft'] ?? null,
            'pending_confirmation' => is_array($state['pending_confirmation'] ?? null)
                ? $state['pending_confirmation']
                : null,
            'last_operation' => $this->compactLastOperation($state['last_operation'] ?? null),
        ];
    }

    /** @param array<int, array<string, mixed>> $entityRefs @param array<string, mixed> $result */
    private function persistOperationalContext(Conversation $conversation, Workspace $workspace, User $user, array $entityRefs, string $actionKey, array $result): void
    {
        $conversation->refresh();
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $draftState = is_array($metadata['active_recipe_draft_state'] ?? null) ? $metadata['active_recipe_draft_state'] : [];
        $confirmation = is_array($result['confirmation'] ?? null) ? $result['confirmation'] : null;
        $metadata['ai_operational_context'] = [
            'version' => 1,
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspace->id,
            'actor_id' => $user->id,
            'active_entity_refs' => $this->compactEntityRefs($entityRefs),
            'draft' => ($draftState['status'] ?? null) === 'needs_clarification' ? ($draftState['payload'] ?? null) : null,
            'pending_confirmation' => $confirmation === null ? null : array_filter([
                'confirmation_id' => $confirmation['confirmation_id'] ?? $confirmation['id'] ?? null,
                'draft_id' => $confirmation['draft_id'] ?? null,
                'status' => $confirmation['status'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'last_operation' => [
                'action_key' => $actionKey,
                'status' => $result['status'] ?? null,
                'result_ref' => $this->compactResultReference($result['result_ref_json'] ?? null),
                'updated_at' => now()->toIso8601String(),
            ],
        ];
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * A confirmation may be reached after one or more read tools. Persist the
     * read presentation with the draft so the confirmation response can
     * render the same context after the write is executed.
     *
     * @param array<string, mixed> $result
     * @param array<int, array<string, mixed>> $supportingResults
     */
    private function persistConfirmationPresentationContext(
        Workspace $workspace,
        array $result,
        array $supportingResults
    ): void {
        $confirmationId = data_get($result, 'confirmation.confirmation_id')
            ?? data_get($result, 'confirmation.id');
        if (!filled($confirmationId) || $supportingResults === []) {
            return;
        }

        $confirmation = ActionConfirmation::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($confirmationId)
            ->first();
        if (!$confirmation) {
            return;
        }

        $presentationResults = collect($supportingResults)
            ->filter(fn (mixed $supportingResult): bool => is_array($supportingResult))
            ->map(fn (array $supportingResult): array => [
                'blocks' => (array) ($supportingResult['blocks'] ?? []),
                'entity_refs' => (array) ($supportingResult['entity_refs'] ?? []),
            ])
            ->values()
            ->all();
        if ($presentationResults === []) {
            return;
        }

        $draft = is_array($confirmation->draft_json) ? $confirmation->draft_json : [];
        $draft['presentation_context'] = [
            'supporting_results' => $presentationResults,
            'version' => 1,
        ];
        $confirmation->forceFill(['draft_json' => $draft])->save();
    }

    /** @param array<int, mixed> $references @return array<int, array<string, mixed>> */
    private function compactEntityRefs(array $references): array
    {
        return collect($references)
            ->filter(fn (mixed $reference): bool => is_array($reference) && filled($reference['id'] ?? null) && filled($reference['type'] ?? null))
            ->map(function (array $reference): array {
                $type = (string) $reference['type'];

                return array_filter([
                    'id' => (string) $reference['id'],
                    'role' => (string) ($reference['role'] ?? 'recent'),
                    'snapshot' => $this->compactEntitySnapshot($reference['snapshot'] ?? [], $type),
                    'type' => $type,
                    'version' => $reference['version'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
            })
            // Later tool results are more authoritative than an earlier list
            // snapshot for the same active entity.
            ->reverse()
            ->unique(fn (array $reference): string => ($reference['type'] ?? '').':'.($reference['id'] ?? '').':'.($reference['role'] ?? 'recent'))
            ->reverse()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function compactEntitySnapshot(array $snapshot, string $type): array
    {
        $compact = collect($snapshot)
            ->only(['id', 'name', 'title', 'status', 'current_version', 'current_version_id', 'revision', 'version', 'recipe_id'])
            ->all();

        if ($type === 'recipe') {
            $ingredients = is_array($snapshot['ingredients'] ?? null)
                ? $snapshot['ingredients']
                : (is_array($snapshot['current_version_record']['ingredients'] ?? null)
                    ? $snapshot['current_version_record']['ingredients']
                    : []);
            $compact['ingredients'] = collect($ingredients)
                ->filter(fn (mixed $ingredient): bool => is_array($ingredient))
                ->map(fn (array $ingredient): array => array_filter([
                    'id' => $ingredient['id'] ?? null,
                    'ingredient_name' => $ingredient['ingredient_name'] ?? $ingredient['name'] ?? null,
                    'quantity' => $ingredient['quantity'] ?? null,
                    'unit_id' => $ingredient['unit_id'] ?? data_get($ingredient, 'unit.id'),
                    'unit_key' => data_get($ingredient, 'unit.key'),
                    'preparation' => $ingredient['preparation'] ?? null,
                    'position' => $ingredient['position'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''))
                ->values()
                ->all();
        }

        return array_filter($compact, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function compactLastOperation(mixed $operation): ?array
    {
        if (!is_array($operation)) {
            return null;
        }

        return array_filter([
            'action_key' => $operation['action_key'] ?? null,
            'status' => $operation['status'] ?? null,
            'result_ref' => $this->compactResultReference($operation['result_ref'] ?? null),
            'updated_at' => $operation['updated_at'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function compactResultReference(mixed $result): mixed
    {
        if (!is_array($result)) {
            return $result;
        }

        if (is_array($result['items'] ?? null)) {
            return array_filter([
                'count' => $result['count'] ?? count($result['items']),
                'items' => collect($result['items'])
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->map(fn (array $item): array => $this->compactEntitySnapshot(
                        $item,
                        isset($item['recipe_id']) || isset($item['ingredients']) ? 'recipe' : ''
                    ))
                    ->values()
                    ->all(),
            ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
        }

        return collect($result)
            ->only(['count', 'status', 'id', 'name', 'title', 'recipe_id', 'version', 'current_version', 'current_version_id'])
            ->filter(static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '')
            ->all();
    }

    /** @param array<string, mixed> $existing */
    private function mergePendingRecipeDraft(Conversation $conversation, array $incoming): array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = is_array($metadata['ai_operational_context'] ?? null) ? $metadata['ai_operational_context'] : [];
        $existing = is_array($state['draft'] ?? null) ? $state['draft'] : [];
        if ($existing === []) {
            $legacyState = is_array($metadata['active_recipe_draft_state'] ?? null) ? $metadata['active_recipe_draft_state'] : [];
            $existing = ($legacyState['status'] ?? null) === 'needs_clarification' && is_array($legacyState['payload'] ?? null)
                ? $legacyState['payload']
                : [];
        }
        if ($existing === []) {
            return $incoming;
        }

        $merged = $existing;
        foreach ($incoming as $key => $value) {
            if ($key === 'yield' && is_array($value) && is_array($merged[$key] ?? null)) {
                foreach ($value as $yieldKey => $yieldValue) {
                    if ($yieldValue !== null && $yieldValue !== '') {
                        $merged[$key][$yieldKey] = $yieldValue;
                    }
                }
                continue;
            }
            if (is_array($value) && array_is_list($value)) {
                if ($value !== []) {
                    $merged[$key] = $value;
                }
                continue;
            }
            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function isOrphanedProviderToolCall(\Throwable $exception): bool
    {
        if (!$exception instanceof \App\AI\Exceptions\AiProviderValidationException) {
            return false;
        }

        $message = strtolower((string) ($exception->metadata()['provider_message'] ?? ''));

        return str_contains($message, 'no tool output found for function call');
    }

    /** @param array<string, mixed> $result */
    private function toolResultForModel(array $tool, array $result): array
    {
        $status = (string) ($result['status']
            ?? $result['workflow_status']
            ?? (is_array($result['confirmation'] ?? null) ? 'confirmation_required' : 'completed'));
        $ok = !in_array($status, ['failed', 'final_not_found', 'cancelled'], true);
        $workflowGuidance = $tool['key'] === 'tasks.search'
            ? 'If the user requested a write, this search is only preparatory: use the exact returned task IDs with the requested write tool before ending the turn.'
            : null;
        $safeDetails = [
            'action_key' => $tool['key'],
            'status' => $status,
            'result' => $result['result_ref_json'] ?? [],
            'entity_refs' => $result['entity_refs'] ?? [],
        ];
        foreach (['missing_fields', 'validation_errors', 'dependency', 'dependencies', 'clarification', 'confirmation'] as $key) {
            if (array_key_exists($key, $result) && $result[$key] !== null && $result[$key] !== []) {
                $safeDetails[$key] = $result[$key];
            }
        }
        if ($workflowGuidance !== null) {
            $safeDetails['workflow_guidance'] = $workflowGuidance;
        }

        return [
            'ok' => $ok,
            'code' => $ok ? null : ($status === 'final_not_found' ? 'ENTITY_NOT_FOUND' : 'TOOL_FAILED'),
            'message_for_model' => match ($status) {
                'clarification_required' => 'The tool needs one missing user value before it can continue.',
                'confirmation_required' => 'The tool produced a confirmation request. Wait for explicit user confirmation before continuing the write.',
                'final_not_found' => 'The requested entity was not found in the authorized workspace.',
                'failed' => 'The tool rejected the request. Inspect safe validation details and repair or clarify it.',
                default => 'Tool completed. Continue the user request if more capabilities are required.',
            },
            'retryable' => !in_array($status, ['final_not_found', 'cancelled'], true),
            'allowed_next_actions' => match ($status) {
                'clarification_required' => ['ask_user_for_clarification', 'resolve_dependency'],
                'confirmation_required' => ['request_user_confirmation'],
                'final_not_found' => [$this->searchActionForTool($tool), 'ask_user_for_clarification'],
                'failed' => ['correct_arguments', 'resolve_dependency', 'ask_user_for_clarification'],
                default => ['continue_with_tool', 'respond_to_user'],
            },
            'safe_details' => $safeDetails,
        ];
    }

    /** @param array<string, mixed> $lastResult */
    private function toolLoopFinalResult(array $lastResult, string $text, string $locale): array
    {
        $blocks = is_array($lastResult['blocks'] ?? null) ? $lastResult['blocks'] : [];
        if ($text !== '') {
            $blocks = array_values(array_filter($blocks, static fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) !== 'text'));
            array_unshift($blocks, ['text' => $text, 'type' => 'text']);
        }
        if ($blocks === []) {
            $blocks = $this->recoveryResult($locale, 'AI_EMPTY_RESPONSE', $this->t($locale, 'recovery.internal_error'))['blocks'];
        }

        return [
            'blocks' => $blocks,
            'entity_refs' => $lastResult['entity_refs'] ?? [],
            'suggestions' => [],
            'workflow_status' => $lastResult['status'] ?? $lastResult['workflow_status'] ?? 'completed',
        ];
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function mergeUsage(array $left, array $right): array
    {
        foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $key) {
            if (isset($right[$key]) && is_numeric($right[$key])) {
                $left[$key] = (int) ($left[$key] ?? 0) + (int) $right[$key];
            }
        }

        foreach (['input_tokens_details', 'output_tokens_details'] as $group) {
            if (!is_array($right[$group] ?? null)) {
                continue;
            }
            $left[$group] = is_array($left[$group] ?? null) ? $left[$group] : [];
            foreach ($right[$group] as $key => $value) {
                if (is_numeric($value)) {
                    $left[$group][$key] = (int) ($left[$group][$key] ?? 0) + (int) $value;
                }
            }
        }

        return $left;
    }

    /** @param array<string, mixed> $result @param array<string, mixed> $providerMetadata */
    private function recordAndCompleteToolLoop(Conversation $conversation, Workspace $workspace, Message $assistantMessage, AiRun $aiRun, array $result, string $locale, string $correlationId, array $providerMetadata, array $usage, array $toolKeys): void
    {
        $this->recordConversationEntityRefs->execute($conversation, $workspace, $result['entity_refs'] ?? []);
        $this->assistantMessageWriter->complete(
            $assistantMessage,
            $workspace,
            [
                'blocks' => $result['blocks'] ?? [],
                'suggestions' => $result['suggestions'] ?? [],
                'tool' => $result['tool'] ?? null,
            ],
            $locale,
            [
                'entity_refs' => $result['entity_refs'] ?? [],
                'orchestration' => [
                    'interaction_mode' => 'tool_loop',
                    'provider' => $providerMetadata['provider'] ?? 'openai',
                    'tool_calls' => $toolKeys,
                    'workflow_status' => $result['workflow_status'] ?? null,
                ],
                'source' => 'assistant-response',
            ]
        );
        $this->completeAiRunSafely($aiRun, [
            'completed_at' => now(),
            'metadata' => [
                ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                'orchestration_version' => 'tool-loop-v1',
                'selected_action_keys' => $toolKeys,
                'interaction_mode' => 'tool_loop',
                'safe_reason_code' => $result['workflow_status'] ?? null,
                'tool_profile' => $providerMetadata['tool_profile'] ?? null,
                'cached_input_tokens' => $providerMetadata['cached_input_tokens'] ?? null,
            ],
            'model_key' => (string) ($providerMetadata['model'] ?? $aiRun->model_key),
            'provider' => (string) ($providerMetadata['provider'] ?? $aiRun->provider),
            'latency_ms' => $this->latencyMilliseconds($aiRun->started_at),
            'status' => 'completed',
            'usage_json' => $usage,
        ], $correlationId);
        Log::info('ai.tool_loop.completed', [
            'correlation_id' => $correlationId,
            'status' => $result['workflow_status'] ?? null,
            'tool_keys' => $toolKeys,
            'workspace_id' => $workspace->id,
        ]);
    }

    private function observeSuccessfulPattern(array $decision, array $context, array $result): ?array
    {
        $routing = is_array($decision['routing'] ?? null) ? $decision['routing'] : [];
        if (in_array($result['workflow_status'] ?? null, ['clarification_required', 'confirmation_required', 'final_not_found', 'failed', 'unsupported_capability'], true)) {
            return null;
        }
        if (($routing['source'] ?? null) !== 'ai' || empty($result['tool_keys'])) {
            return null;
        }

        foreach ((array) $result['tool_keys'] as $toolKey) {
            $tool = $this->toolRegistry->resolve((string) $toolKey);
            if (($tool['requires_confirmation'] ?? false) === true) {
                return null;
            }
        }

        $pattern = $this->intentPatternRegistry->observe(
            (string) $context['workspace']->id,
            $decision,
            true
        );

        if ($pattern === null) {
            return null;
        }

        return [
            'action_key' => $pattern->action_key,
            'confidence' => (float) $pattern->confidence,
            'occurrences' => $pattern->occurrences,
            'pattern_id' => $pattern->id,
            'status' => $pattern->status,
        ];
    }

    private function observeSuccessfulPatternSafely(array $decision, array $context, array $result): ?array
    {
        try {
            return $this->observeSuccessfulPattern($decision, $context, $result);
        } catch (\Throwable $exception) {
            Log::warning('ai.intent_pattern.observe_failed', [
                'action_key' => data_get($decision, 'routing.action_key'),
                'correlation_id' => $context['correlation_id'] ?? null,
                'exception_class' => class_basename($exception),
                'workspace_id' => $context['workspace']->id ?? null,
            ]);

            return null;
        }
    }

    private function completeAiRunSafely(AiRun $aiRun, array $attributes, string $correlationId): void
    {
        try {
            $aiRun->forceFill($attributes)->save();
        } catch (\Throwable $exception) {
            Log::warning('ai.run.persistence_failed', [
                'ai_run_id' => $aiRun->id,
                'correlation_id' => $correlationId,
                'exception_class' => class_basename($exception),
                'workspace_id' => $aiRun->workspace_id,
            ]);
        }
    }

    private function resolvedModel(array $decision, string $fallback): string
    {
        $routing = is_array($decision['routing'] ?? null) ? $decision['routing'] : [];

        return ($routing['source'] ?? null) === 'deterministic' || ($routing['source'] ?? null) === 'learned'
            ? 'humoo-hybrid-router'
            : (string) ($decision['model'] ?? $fallback);
    }

    private function latencyMilliseconds(mixed $startedAt): ?int
    {
        if (!$startedAt) {
            return null;
        }

        return max(0, (int) $startedAt->diffInMilliseconds(now()));
    }

    private function resolvedProvider(array $decision, string $fallback): string
    {
        $routing = is_array($decision['routing'] ?? null) ? $decision['routing'] : [];

        return ($routing['source'] ?? null) === 'deterministic' || ($routing['source'] ?? null) === 'learned'
            ? 'hybrid_router'
            : (string) ($decision['provider'] ?? $fallback);
    }

    private function buildContext(
        Conversation $conversation,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
        Message $userMessage,
        Message $assistantMessage,
        string $locale,
        string $timezone,
        string $correlationId
    ): OrchestrationContext {
        $recentMessages = $conversation->messages()
            ->where('id', '!=', $assistantMessage->id)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->reverse()
            ->values();
        $messageEntityRefs = $recentMessages
            ->reverse()
            ->flatMap(function (Message $message) {
                $metadata = is_array($message->metadata) ? $message->metadata : [];

                return is_array($metadata['entity_refs'] ?? null)
                    ? $metadata['entity_refs']
                    : [];
            })
            ->filter(fn ($ref) => is_array($ref) && isset($ref['type'], $ref['id']))
            ->values()
            ->all();
        $persistedEntityRefs = ConversationEntityRef::query()
            ->where('workspace_id', $workspace->id)
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('last_referenced_at')
            ->limit(24)
            ->get()
            ->map(fn (ConversationEntityRef $reference): array => [
                'id' => $reference->entity_id,
                'role' => $reference->role,
                'snapshot' => $reference->metadata_json ?? [],
                'type' => $reference->entity_type,
            ])
            ->all();
        $recentEntityRefs = collect([...$persistedEntityRefs, ...$messageEntityRefs])
            ->unique(fn (array $reference): string => implode(':', [
                $reference['type'] ?? '',
                $reference['id'] ?? '',
                $reference['role'] ?? 'recent',
            ]))
            ->values()
            ->all();

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $activeEntities = collect($recentEntityRefs)
            ->filter(fn (array $reference): bool => ($reference['role'] ?? null) === 'active')
            ->keyBy(fn (array $reference): string => (string) ($reference['type'] ?? ''))
            ->all();
        $legacyDraft = is_array($metadata['active_recipe_draft'] ?? null) ? $metadata['active_recipe_draft'] : null;
        $pendingContinuations = is_array($metadata['pending_continuations'] ?? null)
            ? $metadata['pending_continuations']
            : [];
        if ($legacyDraft !== null && !collect($pendingContinuations)->contains(fn (mixed $item): bool => is_array($item) && ($item['kind'] ?? null) === 'draft' && ($item['entity_type'] ?? null) === 'recipe')) {
            $pendingContinuations[] = [
                'action_key' => 'recipes.create',
                'actor_id' => $conversation->created_by,
                'continuation_id' => 'legacy-recipe-draft',
                'conversation_id' => $conversation->id,
                'entity_type' => 'recipe',
                'kind' => 'draft',
                'label' => $legacyDraft['name'] ?? data_get($legacyDraft, 'version.name') ?? 'Recipe draft',
                'payload' => $legacyDraft,
                'status' => 'pending',
                'target_type' => 'recipe_draft',
                'workspace_id' => $workspace->id,
            ];
        }

        $lastInteraction = $recentMessages->reverse()->first(function (Message $message): bool {
            $metadata = is_array($message->metadata) ? $message->metadata : [];
            return $message->sender_type === 'assistant' && is_array($metadata['orchestration'] ?? null);
        });

        return new OrchestrationContext(
            workspace: $workspace,
            actor: $user,
            membership: $membership,
            conversation: $conversation,
            currentMessage: $userMessage,
            assistantMessage: $assistantMessage,
            locale: $locale,
            timezone: $timezone,
            entityRefs: $recentEntityRefs,
            recentMessages: $recentMessages->map(fn (Message $message) => [
                'content_text' => $message->content_text,
                'id' => $message->id,
                'sender_type' => $message->sender_type,
            ])->all(),
            availableTools: $this->toolRegistry->allMetadata(),
            systemInstructions: $this->systemInstructions->toText(),
            pendingContinuations: $pendingContinuations,
            activeEntities: $activeEntities,
            lastInteraction: $lastInteraction ? [
                'message_id' => $lastInteraction->id,
                ...(is_array($lastInteraction->metadata['orchestration'] ?? null) ? $lastInteraction->metadata['orchestration'] : []),
            ] : null,
            correlationId: $correlationId,
        );
    }

    private function executeDecision(
        array $decision,
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount
    ): array {
        $maxToolCalls = max(1, (int) config('ai.max_tool_calls_per_turn', 4));
        $context['routing'] = $decision['routing'] ?? null;

        if ($toolCount > $maxToolCalls) {
            return $this->recoveryResult(
                $context['locale'],
                'tool_limit',
                $this->t($context['locale'], 'recovery.tool_limit_detail')
            );
        }

        if (($decision['intent'] ?? null) === 'unsupported_capability') {
            return $this->recordUnsupportedCapability($context, $decision);
        }

        return match ($decision['intent'] ?? 'clarify_scope') {
            'advisory', 'generative' => $this->advisoryOrchestrator->respond($context, $decision, $aiRun),
            'show_events' => $this->showEvents($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'search_menus' => $this->searchMenus($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'show_menu' => $this->showMenu($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'show_event_summary' => $this->showEventSummary($context, $decision['slots'] ?? []),
            'show_selected_event_summary' => $this->showSelectedEventSummary($context, (string) (($decision['slots'] ?? [])['event_id'] ?? '')),
            'show_prep_for_event' => $this->showPrepForEvent($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'show_prep_for_selected_event' => $this->showPrepForSelectedEvent($context, $assistantMessage, $aiRun, $toolCount, (string) (($decision['slots'] ?? [])['event_id'] ?? '')),
            'show_my_tasks' => $this->showMyTasks($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'show_tasks_for_selected_event' => $this->showMyTasks($context, $assistantMessage, $aiRun, $toolCount, [
                'event_id' => (string) (($decision['slots'] ?? [])['event_id'] ?? ''),
            ]),
            'show_pending_for_event' => $this->showPendingForEvent($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'show_pending_for_selected_event' => $this->showPendingForSelectedEvent($context, $assistantMessage, $aiRun, $toolCount, (string) (($decision['slots'] ?? [])['event_id'] ?? '')),
            'update_task' => $this->previewTaskUpdate($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'create_task' => $this->previewTaskCreate($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'tool_action' => $this->executeRegisteredAction($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'create_menu' => $this->previewMenuCreate($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'rename_menu' => $this->renameMenu($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'add_menu_item' => $this->addMenuItem($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            'move_menu_item_section' => $this->moveMenuItemSection($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            default => $this->clarifyScope($context['locale']),
        };
    }

    private function capabilityCallDecision(CapabilityCall $call): array
    {
        if (app()->environment(['local', 'testing'])) {
            Log::info('ai.recipe_draft.mapping_started', [
                'stage' => 'recipe_draft_mapping',
                'validator' => 'RecipeCreateDraftData',
                'action_key' => $call->actionKey,
                'correlation_id' => $call->correlationId,
            ]);
        }
        try {
            $draft = RecipeCreateDraftData::from($call->arguments)->toArray();
        } catch (\Throwable $exception) {
            if (app()->environment(['local', 'testing'])) {
                Log::warning('ai.recipe_draft.mapping_failed', [
                    'stage' => 'recipe_draft_mapping',
                    'validator' => 'RecipeCreateDraftData',
                    'action_key' => $call->actionKey,
                    'error_code' => 'structural_invalid',
                    'field_path' => 'recipe_draft',
                    'reason_code' => 'draft_mapping_failed',
                    'correlation_id' => $call->correlationId,
                    'exception_class' => class_basename($exception),
                ]);
            }
            throw $exception;
        }
        if (app()->environment(['local', 'testing'])) {
            Log::info('ai.recipe_draft.mapping_passed', [
                'stage' => 'recipe_draft_mapping',
                'validator' => 'RecipeCreateDraftData',
                'action_key' => $call->actionKey,
                'correlation_id' => $call->correlationId,
                'ingredient_count' => count($draft['ingredients'] ?? []),
                'step_count' => count($draft['steps'] ?? []),
            ]);
        }

        $routingUsage = is_array($call->usage['routing'] ?? null) ? $call->usage['routing'] : [];
        $extractionUsage = is_array($call->usage['extraction'] ?? null) ? $call->usage['extraction'] : [];

        return [
            'intent' => 'tool_action',
            'interaction_mode' => 'action',
            'usage' => [
                'routing' => $routingUsage,
                'extraction' => $extractionUsage,
                'input_tokens' => (int) ($routingUsage['input_tokens'] ?? 0) + (int) ($extractionUsage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($routingUsage['output_tokens'] ?? 0) + (int) ($extractionUsage['output_tokens'] ?? 0),
                'total_tokens' => (int) ($routingUsage['total_tokens'] ?? 0) + (int) ($extractionUsage['total_tokens'] ?? 0),
            ],
            'slots' => [
                'action_key' => $call->actionKey,
                'input' => ['recipe_draft' => $draft],
            ],
            'routing' => [
                'action_key' => $call->actionKey,
                'action_policy' => $this->toolRegistry->resolve($call->actionKey)['policy'] ?? null,
                'ai_fallback_used' => false,
                'confidence' => $call->confidence,
                'interaction_mode' => 'action',
                'matched_pattern_id' => null,
                'source' => 'ai_v2',
            ],
        ];
    }

    private function recordUnsupportedCapability(array $context, array $decision): array
    {
        $slots = is_array($decision['slots'] ?? null) ? $decision['slots'] : [];
        $requestedAction = trim((string) ($slots['requested_action'] ?? ''));
        $detectedIntent = trim((string) ($slots['detected_intent'] ?? ''));

        if ($requestedAction === '' || $detectedIntent === '') {
            return $this->clarifyScope($context['locale']);
        }

        $observability = null;
        try {
            $request = $this->recordUnsupportedCapability->execute(
                $context['workspace'],
                $context['user'],
                $context['conversation'],
                $context['user_message'],
                [
                    'detected_intent' => $detectedIntent,
                    'metadata' => [
                        'confidence' => $slots['confidence'] ?? null,
                        'model_key' => $decision['model'] ?? null,
                        'provider' => $decision['provider'] ?? null,
                    ],
                    'module' => $slots['module'] ?? null,
                    'normalized_key' => $slots['normalized_key'] ?? null,
                    'requested_action' => $requestedAction,
                ]
            );
            $observability = $this->capabilityRequestObservability($request);
            Log::info('ai.unsupported_capability.recorded', $observability);
        } catch (\Throwable $exception) {
            Log::warning('ai.unsupported_capability.record_failed', [
                'correlation_id' => $context['correlation_id'] ?? null,
                'exception_class' => class_basename($exception),
                'workspace_id' => $context['workspace']->id,
            ]);
        }

        return [
            'blocks' => [[
                'text' => $this->t($context['locale'], 'unsupported_capability.text'),
                'type' => 'text',
            ]],
            'capability_request' => $observability,
            'entity_refs' => [],
            'suggestions' => $this->defaultSuggestions($context['locale']),
            'tool_keys' => [],
        ];
    }

    private function capabilityRequestObservability(CapabilityRequest $request): array
    {
        return [
            'detected_intent' => $request->detected_intent,
            'module' => $request->module,
            'normalized_key' => $request->normalized_key,
            'occurrences' => $request->occurrences,
            'workspace_id' => $request->workspace_id,
        ];
    }

    private function previewMenuCreate(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $draft = is_array($slots['menu_draft'] ?? null) ? $slots['menu_draft'] : [];

        if (trim((string) ($draft['name'] ?? '')) === '' || empty($draft['sections'])) {
            return [
                'blocks' => [[
                    'text' => $context['locale'] === 'es'
                        ? 'Necesito el nombre del menú y sus ítems para preparar un borrador. Puedes escribirlos en el chat o adjuntar un documento cuando el flujo de adjuntos esté habilitado.'
                        : 'I need the menu name and items to prepare a draft. You can write them in chat or attach a document when the attachment flow is enabled.',
                    'type' => 'text',
                ]],
                'entity_refs' => [],
                'suggestions' => [],
                'tool_keys' => [],
            ];
        }

        $result = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'menus.create',
            $draft
        );

        return [
            'blocks' => $result['blocks'] ?? [],
            'entity_refs' => [],
            'suggestions' => [],
            'tool_keys' => ['menus.create'],
        ];
    }

    private function searchMenus(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $result = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'menus.search',
            ['search' => $slots['menu_search'] ?? null]
        );

        return [
            'blocks' => $result['blocks'] ?? [],
            'entity_refs' => $this->buildMenuRefs(Arr::get($result, 'result_ref_json.items', []), 'recent'),
            'suggestions' => [],
            'tool_keys' => ['menus.search'],
        ];
    }

    private function showMenu(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $result = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'menus.show',
            [
                'menu_id' => $slots['menu_id'] ?? null,
                'menu_search' => $slots['menu_search'] ?? null,
            ]
        );

        return [
            'blocks' => $result['blocks'] ?? [],
            'entity_refs' => $this->buildMenuRefs(Arr::get($result, 'result_ref_json.items', []), 'active'),
            'suggestions' => [],
            'tool_keys' => ['menus.show'],
        ];
    }

    private function renameMenu(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        return $this->menuMutationResult($context, $assistantMessage, $aiRun, $toolCount, 'menus.rename', [
            'menu_id' => $slots['menu_id'] ?? null,
            'menu_search' => $slots['menu_search'] ?? null,
            'name' => $slots['menu_name'] ?? null,
        ]);
    }

    private function addMenuItem(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        return $this->menuMutationResult($context, $assistantMessage, $aiRun, $toolCount, 'menus.items.add', [
            'item_name' => $slots['menu_item_search'] ?? null,
            'menu_id' => $slots['menu_id'] ?? null,
            'menu_search' => $slots['menu_search'] ?? null,
            'section_id' => $slots['target_section_id'] ?? null,
            'section_search' => $slots['target_section_search'] ?? null,
        ]);
    }

    private function moveMenuItemSection(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        return $this->menuMutationResult($context, $assistantMessage, $aiRun, $toolCount, 'menus.items.move_section', [
            'item_id' => $slots['menu_item_id'] ?? null,
            'item_search' => $slots['menu_item_search'] ?? null,
            'menu_id' => $slots['menu_id'] ?? null,
            'menu_search' => $slots['menu_search'] ?? null,
            'target_section_id' => $slots['target_section_id'] ?? null,
            'target_section_search' => $slots['target_section_search'] ?? null,
        ]);
    }

    private function menuMutationResult(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        string $actionId,
        array $input
    ): array {
        $result = $this->runTool($context, $assistantMessage, $aiRun, $toolCount, $actionId, $input);

        return [
            'blocks' => $result['blocks'] ?? [],
            'entity_refs' => $result['entity_refs'] ?? [],
            'suggestions' => [],
            'tool_keys' => [$actionId],
        ];
    }

    private function showEvents(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $input = array_filter([
            'date_from' => $slots['time_filter']['date_from'] ?? null,
            'date_to' => $slots['time_filter']['date_to'] ?? null,
            'limit' => 6,
            'search' => $slots['event_search'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        $result = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'events.list',
            $input
        );
        $items = Arr::get($result, 'result_ref_json.items', []);

        return [
            'blocks' => $result['blocks'] ?? [],
            'entity_refs' => $this->buildEventRefs($items),
            'suggestions' => $this->defaultSuggestions($context['locale']),
            'tool_keys' => ['events.list'],
        ];
    }

    private function showEventSummary(array $context, array $slots): array
    {
        $ordinal = max(1, (int) ($slots['ordinal'] ?? 1));
        $refs = collect($context['recent_entity_refs'])
            ->where('type', 'event')
            ->values();
        $ref = $refs->get($ordinal - 1);

        if (!$ref) {
            return $this->clarifyScope($context['locale']);
        }

        return $this->showSelectedEventSummary($context, (string) ($ref['id'] ?? ''));
    }

    private function showSelectedEventSummary(array $context, string $eventId): array
    {
        $ref = collect($context['recent_entity_refs'])
            ->first(fn ($candidate) => ($candidate['type'] ?? null) === 'event' && ($candidate['id'] ?? null) === $eventId);

        if (!$ref || !is_array($ref['snapshot'] ?? null)) {
            return $this->recoveryResult(
                $context['locale'],
                'event_not_available',
                $this->t($context['locale'], 'recovery.event_summary_missing')
            );
        }

        return [
            'blocks' => [
                [
                    'text' => $this->t($context['locale'], 'events.summary_text'),
                    'type' => 'text',
                ],
                [
                    'component' => 'events.summary',
                    'data' => [
                        'event' => $ref['snapshot'],
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => [$ref],
            'suggestions' => $this->defaultSuggestions($context['locale']),
            'tool_keys' => [],
        ];
    }

    private function showPrepForEvent(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $resolved = $this->resolveEventTarget(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            $slots,
            'prep'
        );

        if (isset($resolved['result'])) {
            return $resolved['result'];
        }

        return $this->showPrepForSelectedEvent(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount + ($resolved['used_tool'] ? 1 : 0),
            $resolved['event_id']
        );
    }

    private function showPrepForSelectedEvent(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        string $eventId
    ): array {
        $eventRef = $this->findRecentRef($context['recent_entity_refs'], 'event', $eventId);
        $toolResult = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'prep.list',
            [
                'event_id' => $eventId,
                'limit' => 4,
            ]
        );

        return [
            'blocks' => array_values(array_filter([
                [
                    'text' => $this->t($context['locale'], 'prep.summary_text'),
                    'type' => 'text',
                ],
                $eventRef && is_array($eventRef['snapshot'] ?? null)
                    ? [
                        'component' => 'events.summary',
                        'data' => ['event' => $eventRef['snapshot']],
                        'schema_version' => 1,
                        'type' => 'component',
                    ]
                    : null,
                ...($toolResult['blocks'] ?? []),
            ])),
            'entity_refs' => [
                ...($eventRef ? [$eventRef] : []),
                ...$this->buildPrepRefs(Arr::get($toolResult, 'result_ref_json.items', [])),
            ],
            'suggestions' => $this->defaultSuggestions($context['locale']),
            'tool_keys' => ['prep.list'],
        ];
    }

    private function showMyTasks(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $filters = [
            'limit' => 5,
        ];
        $toolKeys = [];

        if (($slots['event_search'] ?? null) || !empty($slots['time_filter'])) {
            if (!empty($slots['event_id'])) {
                $filters['event_id'] = $slots['event_id'];
            } else {
                $resolved = $this->resolveEventTarget(
                    $context,
                    $assistantMessage,
                    $aiRun,
                    $toolCount,
                    $slots,
                    'tasks'
                );

                if (isset($resolved['result'])) {
                    return $resolved['result'];
                }

                $filters['event_id'] = $resolved['event_id'];
                if ($resolved['used_tool']) {
                    $toolCount++;
                    $toolKeys[] = 'events.list';
                }
            }
        }

        $toolResult = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'tasks.mine',
            $filters
        );

        return [
            'blocks' => $toolResult['blocks'] ?? [],
            'entity_refs' => $this->buildTaskRefs(Arr::get($toolResult, 'result_ref_json.items', [])),
            'suggestions' => $this->defaultSuggestions($context['locale']),
            'tool_keys' => [...$toolKeys, 'tasks.mine'],
        ];
    }

    private function showPendingForEvent(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $resolved = $this->resolveEventTarget(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            $slots,
            'pending'
        );

        if (isset($resolved['result'])) {
            return $resolved['result'];
        }

        return $this->showPendingForSelectedEvent(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount + ($resolved['used_tool'] ? 1 : 0),
            $resolved['event_id']
        );
    }

    private function showPendingForSelectedEvent(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        string $eventId
    ): array {
        $eventRef = $this->findRecentRef($context['recent_entity_refs'], 'event', $eventId);
        $prepResult = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'prep.list',
            [
                'event_id' => $eventId,
                'limit' => 3,
            ]
        );
        $tasksResult = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount + 1,
            'tasks.mine',
            [
                'event_id' => $eventId,
                'limit' => 5,
            ]
        );

        return [
            'blocks' => array_values(array_filter([
                [
                    'text' => $this->t($context['locale'], 'pending.summary_text'),
                    'type' => 'text',
                ],
                $eventRef && is_array($eventRef['snapshot'] ?? null)
                    ? [
                        'component' => 'events.summary',
                        'data' => ['event' => $eventRef['snapshot']],
                        'schema_version' => 1,
                        'type' => 'component',
                    ]
                    : null,
                ...($prepResult['blocks'] ?? []),
                ...($tasksResult['blocks'] ?? []),
            ])),
            'entity_refs' => array_values(array_filter([
                ...($eventRef ? [$eventRef] : []),
                ...$this->buildPrepRefs(Arr::get($prepResult, 'result_ref_json.items', [])),
                ...$this->buildTaskRefs(Arr::get($tasksResult, 'result_ref_json.items', [])),
            ])),
            'suggestions' => $this->defaultSuggestions($context['locale']),
            'tool_keys' => ['prep.list', 'tasks.mine'],
        ];
    }

    private function previewTaskUpdate(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $task = $this->resolveTaskTarget(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            $slots
        );

        if (isset($task['result'])) {
            return $task['result'];
        }

        $input = [];

        if (!empty($slots['status'])) {
            $input['status'] = $slots['status'];
        }

        if (!empty($slots['assignee_name'])) {
            $membership = $this->resolveMembershipByName(
                $context['workspace']->id,
                (string) $slots['assignee_name']
            );

            if (!$membership) {
                return $this->recoveryResult(
                    $context['locale'],
                    'member_not_found',
                    $this->t($context['locale'], 'recovery.member_not_found')
                );
            }

            $input['membership_id'] = $membership->id;
        }

        if ($input === []) {
            return $this->recoveryResult(
                $context['locale'],
                'task_update_missing_change',
                $this->t($context['locale'], 'recovery.task_update_missing_change')
            );
        }

        $toolResult = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount + ($task['used_tool'] ? 1 : 0),
            'update_task',
            $input,
            [
                'id' => $task['task']['id'],
                'type' => 'task',
                'version' => $task['task']['version'] ?? 1,
            ]
        );

        return [
            'blocks' => $toolResult['blocks'] ?? [],
            'entity_refs' => $this->buildTaskRefs([$task['task']]),
            'suggestions' => [],
            'tool_keys' => array_values(array_filter([
                $task['used_tool'] ? 'tasks.mine' : null,
                'tasks.update',
            ])),
        ];
    }

    private function previewTaskCreate(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $title = trim((string) ($slots['task_title'] ?? ''));
        $input = array_filter([
            'description' => trim((string) ($slots['task_description'] ?? '')) ?: null,
            'due_at' => $slots['due_at'] ?? null,
            'priority' => $slots['task_priority'] ?? 'normal',
            'starts_at' => $slots['starts_at'] ?? null,
            'status' => 'todo',
            'title' => $title,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if ($title === '') {
            return $this->missingActionFieldClarification(
                $context,
                $this->toolRegistry->resolve('tasks.create'),
                $input,
                'title'
            );
        }

        $toolResult = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'create_task',
            $input
        );

        return [
            'blocks' => $toolResult['blocks'] ?? [],
            'entity_refs' => [],
            'suggestions' => [],
            'tool_keys' => ['tasks.create'],
        ];
    }

    private function executeRegisteredAction(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $actionKey = $this->toolRegistry->actionKeyForIntent((string) ($slots['action_key'] ?? ''));
        if ($actionKey === null) {
            return $this->clarifyScope($context['locale']);
        }

        $tool = $this->toolRegistry->resolve($actionKey);
        $input = is_array($slots['input'] ?? null) ? $slots['input'] : [];
        $entity = null;
        $entityId = $slots['entity_id'] ?? $slots['task_id'] ?? null;
        $entityInputField = match ($tool['entity_type'] ?? null) {
            'event', 'client', 'contact', 'venue' => 'entity_id',
            'menu' => 'menu_id',
            'recipe' => 'recipe_id',
            'task' => 'task_id',
            'prep_list' => 'prep_list_id',
            'prep_item' => 'prep_item_id',
            'team' => 'team_id',
            'station' => 'station_id',
            'shift' => 'shift_id',
            'membership' => 'membership_id',
            default => null,
        };
        if ($entityInputField !== null && $entityId !== null && !array_key_exists($entityInputField, $input)) {
            $input[$entityInputField] = $entityId;
        }
        if ($tool['operation_type'] !== 'create' && !empty($entityId)) {
            $entity = [
                'id' => $entityId,
                'type' => $tool['entity_type'],
                'version' => $slots['version'] ?? 1,
            ];
        }
        if (!empty($slots['entity_search']) && ($tool['operation_type'] ?? null) !== 'create') {
            $input['entity_search'] = $slots['entity_search'];
            if (($tool['entity_type'] ?? null) === 'menu') {
                $input['menu_search'] = $slots['entity_search'];
            }
            if (($tool['entity_type'] ?? null) === 'recipe') {
                $input['recipe_search'] = $slots['entity_search'];
            }
            if (($tool['entity_type'] ?? null) === 'prep_list') {
                $input[$tool['key'] === 'prep.generate' || $tool['key'] === 'prep.regenerate' ? 'event_search' : 'prep_list_search'] ??= $slots['entity_search'];
            }
            if (($tool['entity_type'] ?? null) === 'prep_item') {
                $input['prep_item_search'] ??= $slots['entity_search'];
            }
            if (($tool['entity_type'] ?? null) === 'task') {
                $input['task_search'] ??= $slots['entity_search'];
            }
        }
        foreach ([
            'entity_id', 'entity_search', 'recipe_id', 'recipe_search', 'recipe_version_id', 'menu_id', 'menu_search', 'menu_item_id', 'menu_item_search', 'task_id', 'task_search',
            'target_section_id', 'target_section_search', 'prep_list_id', 'prep_list_search', 'prep_item_id', 'prep_item_search',
            'event_id', 'event_search', 'guest_count', 'menu_version_id', 'due_at', 'include_assignments',
            'preserve_completed_items', 'preserve_assignments', 'assignment_membership_id', 'assignee_search', 'version',
            'name', 'description', 'title', 'quantity', 'unit_id', 'portions', 'yield_quantity', 'yield_unit_id',
            'actual_quantity', 'actual_unit_id', 'starts_at', 'status', 'blocked_reason', 'notes',
            'team_id', 'team_search', 'station_id', 'station_search', 'shift_id', 'shift_search',
            'membership_id', 'member_search', 'exclude_membership_id', 'exclude_member_search', 'from', 'to', 'timezone', 'break_minutes', 'role',
            'member_ids', 'lead_membership_id', 'records', 'rules', 'membership_id', 'member_search', 'role_id', 'email', 'default_locale', 'currency', 'event_key', 'enabled', 'in_app', 'minimum_priority',
        ] as $slot) {
            if (array_key_exists($slot, $slots) && $slots[$slot] !== null) {
                $input[$slot] = $slots[$slot];
            }
        }
        $missingField = $this->firstMissingRequiredActionField($tool, $input);
        if ($missingField !== null) {
            return [
                ...$this->missingActionFieldClarification($context, $tool, $input, $missingField),
                'tool_keys' => [$actionKey],
            ];
        }
        $result = $this->runTool($context, $assistantMessage, $aiRun, $toolCount, $actionKey, $input, $entity);

        return [
            'blocks' => $result['blocks'] ?? [],
            'entity_refs' => $result['entity_refs'] ?? [],
            'suggestions' => [],
            'tool_keys' => [$actionKey],
            'workflow_status' => $result['status'] ?? null,
        ];
    }

    private function resolveEventTarget(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots,
        string $clarificationAction
    ): array {
        if (!empty($slots['event_search']) || !empty($slots['time_filter'])) {
            $toolResult = $this->runTool(
                $context,
                $assistantMessage,
                $aiRun,
                $toolCount,
                'events.list',
                array_filter([
                    'date_from' => $slots['time_filter']['date_from'] ?? null,
                    'date_to' => $slots['time_filter']['date_to'] ?? null,
                    'limit' => 4,
                    'search' => $slots['event_search'] ?? null,
                ], fn ($value) => $value !== null && $value !== '')
            );
            $items = Arr::get($toolResult, 'result_ref_json.items', []);

            if (count($items) === 0) {
                return [
                    'result' => $this->recoveryResult(
                        $context['locale'],
                        'event_not_found',
                        $this->t($context['locale'], 'recovery.event_not_found')
                    ),
                ];
            }

            if (count($items) > 1) {
                return [
                    'result' => $this->buildEventClarification($context['locale'], $items, $clarificationAction),
                ];
            }

            return [
                'event_id' => (string) ($items[0]['id'] ?? ''),
                'used_tool' => true,
            ];
        }

        $recentEvents = collect($context['recent_entity_refs'])
            ->where('type', 'event')
            ->values();

        if ($recentEvents->count() === 1) {
            return [
                'event_id' => (string) ($recentEvents[0]['id'] ?? ''),
                'used_tool' => false,
            ];
        }

        if ($recentEvents->count() > 1) {
            return [
                'result' => $this->buildEventClarification(
                    $context['locale'],
                    $recentEvents->pluck('snapshot')->filter()->values()->all(),
                    $clarificationAction
                ),
            ];
        }

        return [
            'result' => $this->clarifyScope($context['locale']),
        ];
    }

    private function resolveTaskTarget(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        array $slots
    ): array {
        $recentTasks = collect($context['recent_entity_refs'])
            ->where('type', 'task')
            ->pluck('snapshot')
            ->filter()
            ->values();

        if (!empty($slots['ordinal']) && $recentTasks->count() >= (int) $slots['ordinal']) {
            return [
                'task' => $recentTasks[(int) $slots['ordinal'] - 1],
                'used_tool' => false,
            ];
        }

        $toolResult = $this->runTool(
            $context,
            $assistantMessage,
            $aiRun,
            $toolCount,
            'tasks.mine',
            array_filter([
                'limit' => 6,
                'search' => $slots['search'] ?? null,
            ], fn ($value) => $value !== null && $value !== '')
        );
        $items = Arr::get($toolResult, 'result_ref_json.items', []);

        if (count($items) === 0) {
            return [
                'result' => $this->recoveryResult(
                    $context['locale'],
                    'task_not_found',
                    $this->t($context['locale'], 'recovery.task_not_found')
                ),
            ];
        }

        if (!empty($slots['ordinal']) && count($items) >= (int) $slots['ordinal']) {
            return [
                'task' => $items[(int) $slots['ordinal'] - 1],
                'used_tool' => true,
            ];
        }

        if (count($items) > 1 && empty($slots['search'])) {
            return [
                'result' => $this->recoveryResult(
                    $context['locale'],
                    'task_ambiguous',
                    $this->t($context['locale'], 'recovery.task_ambiguous')
                ),
            ];
        }

        return [
            'task' => $items[0],
            'used_tool' => true,
        ];
    }

    private function runTool(
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount,
        string $actionId,
        array $input = [],
        ?array $entity = null
    ): array {
        $tool = $this->toolRegistry->resolve($actionId);
        $toolCall = $this->createToolCall($aiRun, $context['workspace']->id, $toolCount, $tool['key'], [
            'entity' => $entity,
            'input' => $input,
        ]);

        try {
            $toolExecutionContext = ToolExecutionContext::fromChatContext($context);
            $result = $this->toolExecutor->request(
                $toolExecutionContext->toArray([
                    'ai_tool_call_id' => $toolCall->id,
                    'provider_call_id' => $context['provider_call_id'] ?? null,
                    'source_message' => $assistantMessage,
                    'entity_refs' => $context['entity_refs'] ?? [],
                    'correlation_id' => $context['correlation_id'] ?? null,
                    'routing' => $context['routing'] ?? null,
                ]),
                [
                    'action_id' => $actionId,
                    'entity' => $entity,
                    'idempotency_key' => null,
                    'input' => $input,
                ]
            );

            $this->persistToolCallSafely($toolCall, [
                'completed_at' => now(),
                'result_ref_json' => $result['result_ref_json'] ?? null,
                'status' => 'completed',
            ], $context);

            return $result;
        } catch (\Throwable $exception) {
            if (!($context['tool_loop'] ?? false)) {
                $this->recordPatternFailureSafely((string) $context['workspace']->id, ['routing' => $context['routing'] ?? []], $context);
            }
            $this->persistToolCallSafely($toolCall, [
                'completed_at' => now(),
                'error_code' => $this->errorCodeFor($exception),
                'error_message' => $this->errorCodeFor($exception),
                'status' => 'failed',
            ], $context);

            throw $exception;
        }
    }

    private function persistToolCallSafely(AiToolCall $toolCall, array $attributes, array $context): void
    {
        try {
            $toolCall->forceFill($attributes)->save();
        } catch (\Throwable $exception) {
            Log::warning('ai.tool_call.persistence_failed', [
                'action_key' => $toolCall->tool_key,
                'correlation_id' => $context['correlation_id'] ?? null,
                'exception_class' => class_basename($exception),
                'workspace_id' => $context['workspace']->id ?? null,
            ]);
        }
    }

    private function recordPatternFailureSafely(string $workspaceId, array $decision, array $context): void
    {
        try {
            $this->intentPatternRegistry->recordFailure($workspaceId, $decision);
        } catch (\Throwable $exception) {
            Log::warning('ai.intent_pattern.failure_observation_failed', [
                'action_key' => data_get($decision, 'routing.action_key'),
                'correlation_id' => $context['correlation_id'] ?? null,
                'exception_class' => class_basename($exception),
                'workspace_id' => $workspaceId,
            ]);
        }
    }

    private function buildEventClarification(string $locale, array $events, string $action): array
    {
        return [
            'blocks' => [
                [
                    'text' => $this->t($locale, 'clarification.event_text'),
                    'type' => 'text',
                ],
                [
                    'component' => 'clarification.options',
                    'data' => [
                        'description' => $this->t($locale, 'clarification.event_description'),
                        'options' => collect($events)->map(fn ($event) => [
                            'description' => Arr::get($event, 'starts_at'),
                            'id' => (string) Arr::get($event, 'id'),
                            'label' => (string) Arr::get($event, 'name', Arr::get($event, 'event_number', 'Event')),
                            'value' => sprintf('chat:event:%s:%s', (string) Arr::get($event, 'id'), $action),
                        ])->values()->all(),
                        'selection_mode' => 'immediate',
                        'title' => $this->t($locale, 'clarification.event_title'),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => $this->buildEventRefs($events),
            'suggestions' => [],
            'tool_keys' => [],
        ];
    }

    private function missingActionFieldClarification(
        array $context,
        array $tool,
        array $input,
        string $field
    ): array {
        $conversation = $context['conversation'] ?? null;
        $actionKey = (string) ($tool['key'] ?? '');
        if (!$conversation || $actionKey === '') {
            return [
                'blocks' => [[
                    'text' => $this->t($context['locale'], 'recovery.action_missing_field'),
                    'type' => 'text',
                ]],
                'entity_refs' => [],
                'suggestions' => [],
                'workflow_status' => 'clarification_required',
            ];
        }

        $clarificationId = (string) Str::ulid();
        $continuationId = (string) Str::ulid();
        $locale = (string) ($context['locale'] ?? 'en');
        $fieldLabel = $this->actionFieldLabel($locale, $field, $tool);
        $fieldType = $this->actionFieldType($tool, $field);
        $description = (string) trans('chat.clarification.action_field_description', ['field' => $fieldLabel], $locale);
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['pending_clarifications'] = collect($metadata['pending_clarifications'] ?? [])
            ->reject(fn (mixed $item): bool => is_array($item)
                && ($item['action_key'] ?? $item['workflow'] ?? null) === $actionKey
                && ($item['field_path'] ?? null) === 'input.'.$field
                && ($item['status'] ?? null) === 'pending')
            ->push([
                'action_key' => $actionKey,
                'actor_id' => $context['user']->id,
                'allow_custom' => true,
                'clarification_id' => $clarificationId,
                'continuation_id' => $continuationId,
                'conversation_id' => $conversation->id,
                'draft_reference' => '',
                'entity_type' => $tool['entity_type'] ?? 'record',
                'expected_type' => 'string',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'field_path' => 'input.'.$field,
                'input_control' => 'custom',
                'options' => [],
                'original_payload' => [
                    'action_id' => $actionKey,
                    'input' => $input,
                ],
                'selection_mode' => 'single',
                'status' => 'pending',
                'type' => 'action.field_resolution',
                'workflow' => $actionKey,
                'workspace_id' => $context['workspace']->id,
            ])
            ->values()
            ->all();
        $conversation->forceFill(['metadata' => $metadata])->save();

        Log::info('ai.clarification.created', [
                'action_key' => $actionKey,
                'clarification_type' => 'action.field_resolution',
            'clarification_id' => $clarificationId,
            'continuation_id' => $continuationId,
            'field_path' => 'input.'.$field,
            'conversation_id' => $conversation->id,
            'workspace_id' => $context['workspace']->id,
            'router_bypassed' => true,
            'ai_bypassed' => true,
        ]);

        return [
            'blocks' => [[
                'actions' => [
                    ['id' => 'clarification.resolve'],
                    ['id' => 'clarification.cancel'],
                ],
                'component' => 'clarification.options',
                'data' => [
                    'allow_custom' => true,
                    'clarification_id' => $clarificationId,
                    'custom_input' => [
                        'label' => $fieldLabel,
                        'type' => $fieldType,
                    ],
                    'description' => $description,
                    'expected_type' => $fieldType === 'number' ? 'number' : 'string',
                    'input_control' => 'custom',
                    'options' => [],
                    'selection_mode' => 'single',
                    'title' => (string) trans('chat.clarification.action_field_title', [], $locale),
                ],
                'schema_version' => 2,
                'type' => 'component',
            ]],
            'entity_refs' => [],
            'suggestions' => [],
            'tool_keys' => [$actionKey],
            'workflow_status' => 'clarification_required',
        ];
    }

    /** @param array<string, mixed> $tool @param array<string, mixed> $input */
    private function firstMissingRequiredActionField(array $tool, array $input): ?string
    {
        $schema = $this->toolRegistry->metadata($tool)['input_schema'] ?? [];
        $required = $schema['required'] ?? [];
        if (!is_array($required)) {
            return null;
        }

        foreach ($required as $field) {
            if (!is_string($field) || $field === '' || str_contains($field, '.') || str_contains($field, '*')) {
                continue;
            }
            $value = data_get($input, $field);
            if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
                return $field;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $tool */
    private function actionFieldLabel(string $locale, string $field, array $tool): string
    {
        $fieldName = Str::afterLast($field, '.');
        $module = (string) ($tool['module'] ?? '');
        $moduleKey = $module !== '' ? 'chat.'.$module.'.'.$fieldName.'_label' : '';
        if ($moduleKey !== '') {
            $moduleLabel = trans($moduleKey, [], $locale);
            if ($moduleLabel !== $moduleKey) {
                return (string) $moduleLabel;
            }
        }
        $translated = trans('chat.directory.fields.'.$fieldName, [], $locale);

        return $translated !== 'chat.directory.fields.'.$fieldName
            ? (string) $translated
            : Str::headline($fieldName);
    }

    /** @param array<string, mixed> $tool */
    private function actionFieldType(array $tool, string $field): string
    {
        $schema = $this->toolRegistry->metadata($tool)['input_schema'] ?? [];
        $property = $schema['properties'][$field] ?? null;
        $types = is_array($property) && is_array($property['type'] ?? null)
            ? $property['type']
            : [$property['type'] ?? null];

        return in_array('number', $types, true) ? 'number' : 'text';
    }

    private function clarifyScope(string $locale): array
    {
        // Guided scope options exist only on the initial onboarding message.
        // A runtime miss must not replace a meaningful request with unrelated
        // Events, Prep, or Tasks choices.
        return $this->recoveryResult(
            $locale,
            'UNSUPPORTED_CAPABILITY',
            $this->t($locale, 'recovery.unrecognized_request')
        );
    }

    private function recoveryResult(string $locale, string $errorCode, string $detail): array
    {
        return [
            'blocks' => [
                [
                    'component' => 'error.recovery',
                    'data' => [
                        'description' => $this->t($locale, 'recovery.description'),
                        'error_code' => $errorCode,
                        'safe_detail' => $detail,
                        'title' => $this->t($locale, 'recovery.title'),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => [],
            'suggestions' => $this->defaultSuggestions($locale),
            'tool_keys' => [],
        ];
    }

    private function errorPayload(array $publicError): array
    {
        return [
            'blocks' => [
                [
                    'component' => 'error.recovery',
                    'data' => [
                        'correlation_id' => $publicError['correlation_id'],
                        'description' => $publicError['message'],
                        'error_code' => $publicError['error_code'],
                        'retryable' => $publicError['retryable'],
                        'title' => $publicError['title'],
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'suggestions' => [],
        ];
    }

    private function defaultSuggestions(string $locale): array
    {
        // Guided shortcuts are emitted only by the initial bootstrap message.
        // Runtime outcomes must expose their own contextual contract.
        return [];
    }

    private function buildMenuRefs(array $menus, string $role): array
    {
        return collect($menus)->map(fn ($menu, $index) => [
            'id' => $menu['id'] ?? null,
            'ordinal' => $index + 1,
            'role' => $role,
            'snapshot' => $menu,
            'type' => 'menu',
            'version' => $menu['current_version'] ?? null,
        ])->filter(fn ($ref) => $ref['id'] !== null)->values()->all();
    }

    private function buildEventRefs(array $events): array
    {
        return collect($events)->map(fn ($event, $index) => [
            'id' => $event['id'] ?? null,
            'ordinal' => $index + 1,
            'snapshot' => $event,
            'type' => 'event',
            'version' => $event['version'] ?? null,
        ])->filter(fn ($ref) => $ref['id'] !== null)->values()->all();
    }

    private function buildPrepRefs(array $items): array
    {
        return collect($items)->map(function ($item, $index) {
            $prepList = $item['prep_list'] ?? null;

            if (!is_array($prepList) || !isset($prepList['id'])) {
                return null;
            }

            return [
                'id' => $prepList['id'],
                'ordinal' => $index + 1,
                'snapshot' => $prepList,
                'type' => 'prep_list',
                'version' => $prepList['current_version'] ?? null,
            ];
        })->filter()->values()->all();
    }

    private function buildTaskRefs(array $tasks): array
    {
        return collect($tasks)->map(fn ($task, $index) => [
            'id' => $task['id'] ?? null,
            'ordinal' => $index + 1,
            'snapshot' => $task,
            'type' => 'task',
            'version' => $task['version'] ?? null,
        ])->filter(fn ($ref) => $ref['id'] !== null)->values()->all();
    }

    private function findRecentRef(array $refs, string $type, string $id): ?array
    {
        return collect($refs)
            ->first(fn ($ref) => ($ref['type'] ?? null) === $type && ($ref['id'] ?? null) === $id);
    }

    private function resolveMembershipByName(string $workspaceId, string $name): ?WorkspaceMembership
    {
        $normalized = Str::lower(trim($name));

        if ($normalized === '') {
            return null;
        }

        return WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->whereHas('user', function ($query) use ($normalized): void {
                $query->whereRaw('LOWER(name) like ?', ["%{$normalized}%"]);
            })
            ->with('user')
            ->orderBy('created_at')
            ->first();
    }

    private function continuationActionDecision(string $actionKey, array $input, string $source): array
    {
        $tool = $this->toolRegistry->resolve($actionKey);

        return [
            'intent' => 'tool_action',
            'interaction_mode' => 'continuation',
            'slots' => ['action_key' => $actionKey, 'input' => $input],
            'routing' => [
                'action_key' => $actionKey,
                'action_policy' => $tool['policy'] ?? null,
                'ai_fallback_used' => false,
                'confidence' => 1.0,
                'interaction_mode' => 'continuation',
                'matched_pattern_id' => null,
                'source' => 'continuation_'.$source,
            ],
        ];
    }

    private function resumeConfirmation(ContinuationResolution $continuation, array $context): array
    {
        $confirmationId = $continuation->continuationId;
        if (!$confirmationId) {
            throw new \RuntimeException('The pending confirmation is unavailable.');
        }

        return DB::transaction(function () use ($confirmationId, $context): array {
            $confirmation = ActionConfirmation::query()
                ->where('workspace_id', $context['workspace']->id)
                ->whereKey($confirmationId)
                ->where('status', 'pending')
                ->with('message.conversation')
                ->lockForUpdate()
                ->firstOrFail();

            if ($confirmation->expires_at?->isPast()) {
                $confirmation->forceFill(['status' => 'expired'])->save();
                throw new \RuntimeException('The pending confirmation has expired.');
            }
            $conversation = $confirmation->message?->conversation;
            if (!$conversation || $conversation->id !== $context['conversation']->id
                || ($conversation->created_by !== $context['user']->id
                    && !$conversation->participants()->where('user_id', $context['user']->id)->exists())) {
                throw new \RuntimeException('The pending confirmation is unavailable.');
            }

            $confirmation->forceFill([
                'confirmed_at' => now(),
                'confirmed_by' => $context['user']->id,
                'status' => 'confirmed',
            ])->save();

            try {
                $result = $this->toolExecutor->confirm(
                    $confirmation,
                    ToolExecutionContext::fromChatContext($context)->toArray()
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
                    'correlation_id' => $context['correlation_id'] ?? null,
                    'workspace_id' => $context['workspace']->id,
                ]);
                $confirmation->forceFill([
                    'executed_at' => now(),
                    'result_ref_json' => $result['result_ref_json'] ?? null,
                    'status' => 'executed',
                ])->save();
                $this->conversationContinuationLifecycle->resolvePendingProviderToolCallForConfirmation(
                    $confirmation,
                    $result
                );
                $this->conversationContinuationLifecycle->completeAfterConfirmation($confirmation);

                return $result;
            } catch (\Throwable $exception) {
                $confirmation->forceFill([
                    'error_code' => $exception->getCode() ? (string) $exception->getCode() : 'CONFIRMATION_EXECUTION_FAILED',
                    'error_message' => $exception->getMessage(),
                    'status' => 'failed',
                ])->save();

                throw $exception;
            }
        });
    }

    private function continuationFeedback(array $context, ContinuationResolution $continuation, string $status): array
    {
        $key = match ($status) {
            'ambiguous' => 'ambiguous',
            'expired' => 'expired',
            default => 'invalid',
        };

        return [
            'blocks' => [[
                'component' => 'action.result',
                'data' => [
                    'description' => trans('chat.continuation.'.$key.'_description', [], $context['locale']),
                    'status' => 'partial',
                    'title' => trans('chat.continuation.'.$key.'_title', [], $context['locale']),
                ],
                'schema_version' => 1,
                'type' => 'component',
            ]],
            'entity_refs' => [],
            'interaction_mode' => 'continuation',
            'tool_keys' => [],
            'workflow_status' => 'clarification_required',
        ];
    }

    private function cancelToolLoopContinuation(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        ContinuationResolution $continuation,
        array $context
    ): array {
        $kind = (string) ($continuation->data['kind'] ?? $continuation->source);
        if ($kind === 'clarification') {
            $this->pendingClarificationResolver->cancel(
                $conversation,
                $workspace->id,
                $continuation->continuationId ?? ''
            );
        } elseif ($kind === 'confirmation') {
            DB::transaction(function () use ($continuation, $workspace, $user, $conversation): void {
                $confirmation = ActionConfirmation::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereKey($continuation->continuationId)
                    ->where('status', 'pending')
                    ->with('message.conversation')
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($confirmation->message?->conversation_id !== $conversation->id) {
                    throw new \RuntimeException('The pending confirmation is unavailable.');
                }
                $confirmation->forceFill([
                    'cancelled_at' => now(),
                    'cancelled_by' => $user->id,
                    'status' => 'cancelled',
                ])->save();
                $this->conversationContinuationLifecycle->resolvePendingProviderToolCallForConfirmation(
                    $confirmation,
                    ['status' => 'cancelled']
                );
                $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                $state = is_array($metadata['ai_operational_context'] ?? null) ? $metadata['ai_operational_context'] : [];
                $metadata['ai_operational_context'] = [
                    ...$state,
                    'pending_confirmation' => null,
                    'draft' => null,
                    'last_operation' => [
                        'action_key' => $confirmation->action_key,
                        'status' => 'cancelled',
                        'result_ref' => null,
                        'updated_at' => now()->toIso8601String(),
                    ],
                ];
                $conversation->forceFill(['metadata' => $metadata])->save();
            });
        } elseif ($kind === 'draft') {
            $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
            $metadata['pending_continuations'] = collect($metadata['pending_continuations'] ?? [])
                ->map(function (mixed $item) use ($continuation): mixed {
                    if (is_array($item) && ($item['continuation_id'] ?? null) === $continuation->continuationId) {
                        $item['status'] = 'cancelled';
                    }
                    return $item;
                })->values()->all();
            unset($metadata['active_recipe_draft'], $metadata['active_recipe_draft_state'], $metadata['active_recipe_ingestion_issues']);
            $conversation->forceFill(['metadata' => $metadata])->save();
        }

        $locale = (string) ($context['locale'] ?? 'en');
        return [
            'blocks' => [[
                'component' => 'action.result',
                'data' => [
                    'description' => trans('chat.continuation.cancelled_description', [], $locale),
                    'status' => 'partial',
                    'title' => trans('chat.continuation.cancelled_title', [], $locale),
                ],
                'schema_version' => 1,
                'type' => 'component',
            ]],
            'entity_refs' => [],
            'interaction_mode' => 'continuation',
            'tool_keys' => [],
            'workflow_status' => 'cancelled',
        ];
    }

    private function logContinuation(string $event, OrchestrationContext $context, ContinuationResolution $continuation): void
    {
        if ($continuation->status === 'not_applicable') {
            return;
        }

        Log::info($event, array_filter([
            'action_key' => $continuation->actionKey,
            'continuation_id' => $continuation->continuationId,
            'conversation_id' => $context->conversation->id,
            'entity_type' => $continuation->entityType,
            'kind' => $continuation->targetType ?? $continuation->source,
            'resolution_source' => $continuation->source,
            'workspace_id' => $context->workspace->id,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function updateContinuationStatus(Conversation $conversation, string $continuationId, string $status): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['pending_continuations'] = collect($metadata['pending_continuations'] ?? [])
            ->map(function (mixed $item) use ($continuationId, $status): mixed {
                if (is_array($item) && ($item['continuation_id'] ?? null) === $continuationId) {
                    $item['status'] = $status;
                }

                return $item;
            })->values()->all();
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    private function conversationDraftDecision(Conversation $conversation, string $message): ?array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $draft = is_array($metadata['active_recipe_draft'] ?? null) ? $metadata['active_recipe_draft'] : null;
        $normalized = Str::lower(trim($message));
        if ($normalized === '') {
            return null;
        }

        $recommendationDecision = $this->recommendationActionDecision($metadata['active_recommendation_draft'] ?? null, $normalized);
        if ($recommendationDecision !== null) {
            return $recommendationDecision;
        }

        if ($draft === null) {
            return null;
        }

        $issues = is_array($metadata['active_recipe_ingestion_issues'] ?? null) ? $metadata['active_recipe_ingestion_issues'] : [];
        $range = collect($issues)->firstWhere('code', 'quantity_range');
        if (is_array($range) && ($quantity = $this->recipeClarificationQuantity($message)) !== null) {
            foreach ($draft['ingredients'] ?? [] as $index => $ingredient) {
                if (($ingredient['ingredient_name'] ?? $ingredient['name'] ?? null) === ($range['ingredient'] ?? null)
                    && isset($ingredient['quantity_min'], $ingredient['quantity_max'])) {
                    $draft['ingredients'][$index]['quantity'] = $quantity;
                    unset($draft['ingredients'][$index]['quantity_min'], $draft['ingredients'][$index]['quantity_max']);
                    $metadata['active_recipe_draft'] = $draft;
                    $metadata['active_recipe_ingestion_issues'] = [];
                    $metadata['pending_clarifications'] = collect($metadata['pending_clarifications'] ?? [])
                        ->map(function (mixed $clarification) use ($index, $quantity): mixed {
                            if (!is_array($clarification)
                                || ($clarification['workflow'] ?? null) !== 'recipes.create'
                                || ($clarification['ingredient_index'] ?? null) !== $index
                                || ($clarification['status'] ?? null) !== 'pending') {
                                return $clarification;
                            }

                            $clarification['status'] = 'resolved';
                            $clarification['resolved_value'] = $quantity;

                            return $clarification;
                        })
                        ->values()
                        ->all();
                    $conversation->forceFill(['metadata' => $metadata])->save();
                    return [
                        'intent' => 'tool_action',
                        'interaction_mode' => 'action',
                        'slots' => ['action_key' => 'recipes.create', 'input' => ['recipe_draft' => $draft]],
                        'routing' => [
                            'action_key' => 'recipes.create', 'action_policy' => $this->toolRegistry->resolve('recipes.create')['policy'],
                            'ai_fallback_used' => false, 'confidence' => 0.99, 'interaction_mode' => 'action',
                            'matched_pattern_id' => null, 'source' => 'recipe_ingestion_clarification',
                        ],
                    ];
                }
            }
        }

        if (preg_match('/\b(save|guardar|guarda|crea esta receta|create this recipe)\b/iu', $normalized) === 1) {
            $input = $this->recipeDraftPayloadMapper->toCreateInput($draft);
            if ($input !== null) {
                return [
                    'intent' => 'tool_action',
                    'interaction_mode' => 'action',
                    'slots' => ['action_key' => 'recipes.create', 'input' => ['recipe_draft' => $input]],
                    'routing' => [
                        'action_key' => 'recipes.create',
                        'action_policy' => $this->toolRegistry->resolve('recipes.create')['policy'],
                        'ai_fallback_used' => false,
                        'confidence' => 0.99,
                        'interaction_mode' => 'action',
                        'matched_pattern_id' => null,
                        'source' => 'conversation_draft',
                    ],
                ];
            }
        }

        if (preg_match('/\b(more|less|mas|más|sin|without|use|usa|hazla|make it|acidic|acida|ácida|dill|buttermilk|gallons?|galones?)\b/iu', $normalized) === 1) {
            return [
                'intent' => 'generative',
                'interaction_mode' => 'generative',
                'slots' => ['analysis_type' => 'recipe_generation', 'confidence' => 0.99],
                'routing' => [
                    'action_key' => null,
                    'ai_fallback_used' => false,
                    'confidence' => 0.99,
                    'interaction_mode' => 'generative',
                    'matched_pattern_id' => null,
                    'source' => 'conversation_draft',
                ],
            ];
        }

        return null;
    }

    private function recipeClarificationQuantity(string $message): ?float
    {
        $value = trim(strtr($message, ['½' => '1/2', '¼' => '1/4', '¾' => '3/4']));
        $value = preg_replace('/(?<=\d)(?=\d\/\d)/', ' ', $value) ?? $value;
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s+(\d+)\/(\d+)$/', $value, $matches)) {
            return (float) str_replace(',', '.', $matches[1]) + ((float) $matches[2] / (float) $matches[3]);
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $value, $matches)) {
            return (float) $matches[1] / (float) $matches[2];
        }
        return is_numeric(str_replace(',', '.', $value)) ? (float) str_replace(',', '.', $value) : null;
    }

    private function recommendationActionDecision(mixed $draft, string $normalizedMessage): ?array
    {
        if (!is_array($draft) || preg_match('/\b(apply|aplica|aplicar|hazlo|do it)\b/iu', $normalizedMessage) !== 1) {
            return null;
        }
        $recommendation = collect($draft['recommendations'] ?? [])->first(fn (mixed $item): bool => is_array($item)
            && filled($item['action_key'] ?? null) && filled($item['action_input_json'] ?? null));
        if (!is_array($recommendation)) {
            return null;
        }
        $actionKey = $this->toolRegistry->actionKeyForIntent((string) $recommendation['action_key']);
        $input = json_decode((string) $recommendation['action_input_json'], true);
        if ($actionKey === null || !is_array($input) || (($this->toolRegistry->resolve($actionKey)['policy']['risk'] ?? 'read') === 'read')) {
            return null;
        }

        return [
            'intent' => 'tool_action',
            'interaction_mode' => 'action',
            'slots' => ['action_key' => $actionKey, 'input' => $input],
            'routing' => [
                'action_key' => $actionKey,
                'action_policy' => $this->toolRegistry->resolve($actionKey)['policy'],
                'ai_fallback_used' => false,
                'confidence' => 0.99,
                'interaction_mode' => 'action',
                'matched_pattern_id' => null,
                'source' => 'recommendation_draft',
            ],
        ];
    }

    private function startRun(
        Message $assistantMessage,
        Message $userMessage,
        Workspace $workspace,
        string $locale,
        string $timezone,
        string $correlationId
    ): AiRun {
        return AiRun::query()->create([
            'workspace_id' => $workspace->id,
            'message_id' => $assistantMessage->id,
            'input_message_id' => $userMessage->id,
            'provider' => (string) config('ai.default', 'openai'),
            'model_key' => (string) config('ai.providers.'.config('ai.default', 'openai').'.model', 'openai'),
            'status' => 'running',
            'prompt_version' => (string) config('ai.prompt_version', 'humoo-chat-v1'),
            'orchestrator_version' => 'v1',
            'started_at' => now(),
            'metadata' => [
                'correlation_id' => $correlationId,
                'registry_hash' => method_exists($this->toolRegistry, 'registryHash')
                    ? $this->toolRegistry->registryHash()
                    : hash('sha256', (string) (json_encode($this->toolRegistry->allMetadata()) ?: '')),
                'registry_version' => method_exists($this->toolRegistry, 'registryVersion')
                    ? $this->toolRegistry->registryVersion()
                    : 'tools-v1',
            ],
        ]);
    }

    private function createToolCall(
        AiRun $aiRun,
        string $workspaceId,
        int $position,
        string $toolKey,
        array $arguments
    ): AiToolCall {
        return AiToolCall::query()->create([
            'workspace_id' => $workspaceId,
            'ai_run_id' => $aiRun->id,
            'tool_key' => $toolKey,
            'position' => $position,
            'arguments_json' => $arguments,
            'requires_confirmation' => (bool) ($this->toolRegistry->resolve($toolKey)['requires_confirmation'] ?? false),
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    private function errorCodeFor(\Throwable $exception): string
    {
        if ($exception instanceof AiProviderException) {
            return $exception->internalCode();
        }

        $code = is_scalar($exception->getCode()) ? (string) $exception->getCode() : '';

        return $code !== '' && $code !== '0' ? $code : class_basename($exception);
    }

    private function safeErrorDetail(string $locale, \Throwable $exception): string
    {
        if (!$exception instanceof AiProviderException) {
            return $exception->getMessage();
        }

        if ($exception instanceof \App\AI\Exceptions\AiProviderValidationException) {
            return $this->t($locale, 'recovery.provider_validation');
        }

        return match ($exception->internalCode()) {
            'AI_AUTH_ERROR' => $this->t($locale, 'recovery.provider_authentication'),
            'AI_BAD_REQUEST' => $this->t($locale, 'recovery.provider_bad_request'),
            'AI_INVALID_RESPONSE' => $this->t($locale, 'recovery.provider_invalid_response'),
            'AI_NETWORK_ERROR' => $this->t($locale, 'recovery.provider_network_error'),
            'AI_RATE_LIMITED' => $this->t($locale, 'recovery.provider_rate_limit'),
            'AI_TIMEOUT' => $this->t($locale, 'recovery.provider_timeout'),
            default => $this->t($locale, 'recovery.provider_unavailable'),
        };
    }

    private function providerDiagnosticMetadata(\Throwable $exception): array
    {
        if (!$exception instanceof AiProviderException) {
            return [];
        }

        return array_merge([
            'http_status' => null,
            'latency_ms' => null,
            'model' => null,
            'provider' => null,
            'provider_error_code' => null,
            'provider_error_type' => null,
            'request_id' => null,
        ], $exception->metadata());
    }

    private function t(string $locale, string $key): string
    {
        return (string) trans("chat.{$key}", [], $locale);
    }
}
