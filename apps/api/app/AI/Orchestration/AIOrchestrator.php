<?php
namespace App\AI\Orchestration;

use App\AI\Capabilities\OpenAiFunctionSchemaFactory;
use App\AI\Contracts\StreamingToolCallingProvider;
use App\AI\Contracts\ToolCallingProvider;
use App\AI\Conversations\OpenAIConversationService;
use App\AI\Errors\ErrorResponseMapper;
use App\AI\Exceptions\AiProviderException;
use App\AI\Exceptions\AiProviderUnavailableException;
use App\AI\Exceptions\AiProviderValidationException;
use App\AI\Exceptions\AiRuntimeException;
use App\AI\Objectives\AiObjectiveLifecycle;
use App\AI\Runtime\AiRunLifecycle;
use App\AI\Streaming\ChatStreamPublisher;
use App\AI\Temporal\TemporalContextResolver;
use App\AI\Tools\ToolExecutionContext;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolObservation;
use App\AI\Tools\ToolProfileSelector;
use App\AI\Tools\ToolRegistry;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\Models\ActionConfirmation;
use App\Models\AiExecutionPlan;
use App\Models\AiObjective;
use App\Models\AiRun;
use App\Models\AiToolCall;
use App\Models\Conversation;
use App\Models\ConversationEntityRef;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AIOrchestrator
{
    public function __construct(
        private HumooSystemInstructions $systemInstructions,
        private AssistantMessageWriter $assistantMessageWriter,
        private RecordConversationEntityRefs $recordConversationEntityRefs,
        private ToolExecutor $toolExecutor,
        private ToolRegistry $toolRegistry,
        private ConversationContinuationLifecycle $conversationContinuationLifecycle,
        private MessageLocaleResolver $messageLocaleResolver,
        private ?ToolCallingProvider $toolCallingProvider = null,
        private ?ToolProfileSelector $toolProfileSelector = null,
        private ?OpenAIConversationService $openAIConversationService = null,
        private ?TemporalContextResolver $temporalContextResolver = null,
        private ?ChatStreamPublisher $chatStreamPublisher = null,
    ) {}

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
        if (! $this->toolCallingProvider instanceof ToolCallingProvider) {
            return null;
        }

        $conversation = $confirmation->message?->conversation;
        if (! $conversation || ($this->conversationContinuationLifecycle->pendingProviderToolOutputs($conversation) === []
            && ! data_get($conversation->metadata, 'provider_recovery.pending'))) {
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
        $this->chatStreamPublisher()->activity(
            $conversation,
            $assistantMessage,
            'analysis',
            $locale === 'es' ? 'Analizando tu solicitud.' : 'Reviewing your request.',
        );
        $aiRun = $this->startRun(
            $assistantMessage,
            $confirmation->message,
            $workspace,
            $locale,
            $timezone,
            $correlationId
        );
        $objective = null;
        if ($confirmation->objective_id) {
            $objective = AiObjective::query()
                ->where('workspace_id', $workspace->id)
                ->where('conversation_id', $conversation->id)
                ->find($confirmation->objective_id);
            if ($objective) {
                app(AiObjectiveLifecycle::class)->attachRun($objective, $aiRun);
            }
        }

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
            'objective_id' => $aiRun->objective_id,
            'operational_context' => $this->operationalContextSnapshot($conversation, $workspace, $user),
            'confirmed_execution' => [
                'action_key' => (string) $confirmation->action_key,
                'confirmation_id' => (string) $confirmation->id,
                'executed' => true,
                'result' => (array) ($result['result_ref_json'] ?? []),
                'status' => (string) ($result['status'] ?? $result['workflow_status'] ?? 'completed'),
            ],
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
            $terminationReason = $this->toolLoopFailureTerminationReason($exception);
            if ($objective && in_array($terminationReason, ['paused', 'retrying', 'needs_review'], true)) {
                $this->deferUncertainProviderTurn($conversation, $exception, $aiRun);
                $objective->forceFill([
                    'status' => $terminationReason,
                    'paused_at' => now(),
                    'last_heartbeat_at' => now(),
                    'error_code' => $this->errorCodeFor($exception),
                    'error_message_safe' => $this->safeErrorDetail($locale, $exception),
                ])->save();
            }
            $publicError = (new ErrorResponseMapper)->map($exception, $locale, $correlationId, [
                'ai_run_id' => $aiRun->id,
                'objective_id' => $objective?->id,
                'preserved_progress' => $objective !== null,
                'completed_count' => (int) ($objective?->completed_count ?? 0),
                'pending_count' => (int) ($objective?->pending_count ?? 0),
            ]);
            Log::warning('ai.confirmation.continuation_failed', [
                'action_key' => $confirmation->action_key,
                'confirmation_id' => $confirmation->id,
                'exception_class' => class_basename($exception),
                'termination_reason' => $terminationReason,
                'workspace_id' => $workspace->id,
            ]);
            $continuedResult = [
                ...$this->errorPayload($publicError),
                'status' => $terminationReason,
                'workflow_status' => $terminationReason,
                'entity_refs' => [],
            ];
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
            [
                'model' => $aiRun->model_key,
                'provider' => 'openai',
                'tool_profile' => $continuedResult['tool_profile'] ?? 'all',
                'efficiency' => $continuedResult['efficiency'] ?? [],
            ],
            (array) ($continuedResult['usage'] ?? []),
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
        array $payload,
        ?Message $runtimeAssistantMessage = null,
        ?AiRun $runtimeAiRun = null,
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
        $correlationId = (string) (
            $runtimeAiRun?->correlation_id
            ?? data_get($runtimeAiRun?->metadata, 'correlation_id')
            ?? OrchestrationContext::correlationId()
        );

        if (! $this->toolCallingProvider instanceof ToolCallingProvider) {
            return $this->failUnavailableAiFirstRuntime(
                $conversation,
                $workspace,
                $userMessage,
                $locale,
                $timezone,
                $correlationId,
            );
        }

        return $this->respondWithToolLoop(
            $conversation,
            $workspace,
            $membership,
            $user,
            $userMessage,
            $locale,
            $timezone,
            $correlationId,
            $runtimeAssistantMessage,
            $runtimeAiRun,
        );
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
        string $correlationId,
        ?Message $runtimeAssistantMessage = null,
        ?AiRun $runtimeAiRun = null,
    ): Message {
        Log::info('ai.chat.message_received', [
            'conversation_id' => $conversation->id,
            'correlation_id' => $correlationId,
            'message_id' => $userMessage->id,
            'workspace_id' => $workspace->id,
        ]);
        $assistantMessage = $runtimeAssistantMessage ?? $this->assistantMessageWriter->createPending(
            $conversation,
            $workspace,
            $locale,
            $userMessage,
            ['source' => 'assistant-response', 'orchestration_version' => 'tool-loop-v1']
        );
        $this->chatStreamPublisher()->activity(
            $conversation,
            $assistantMessage,
            'analysis',
            $locale === 'es' ? 'Analizando tu solicitud.' : 'Reviewing your request.',
        );
        $aiRun = $runtimeAiRun ?? $this->startRun(
            $assistantMessage,
            $userMessage,
            $workspace,
            $locale,
            $timezone,
            $correlationId
        );
        $objectiveLifecycle = app(AiObjectiveLifecycle::class);
        $objective = $aiRun->objective_id
            ? AiObjective::query()
                ->where('workspace_id', $workspace->id)
                ->where('conversation_id', $conversation->id)
                ->find($aiRun->objective_id)
            : null;
        $objective ??= $objectiveLifecycle->activeFor($conversation, $workspace, $user);
        if ($objective) {
            $objectiveLifecycle->attachRun($objective, $aiRun);
        }
        $aiRun->forceFill([
            'orchestrator_version' => 'tool-loop-v1',
            'started_at' => $aiRun->started_at ?? now(),
            'status' => 'running',
        ])->save();
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
            $this->beginToolLoopTurn($conversation, $workspace);
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
                'ai_run_id' => (string) $aiRun->id,
                'objective_id' => $objective ? (string) $objective->id : null,
                'objective' => $objective ? $objectiveLifecycle->snapshot($objective) : null,
                'message' => (string) ($userMessage->content_text ?? ''),
                'message_id' => $userMessage->id,
                'operational_context' => $this->operationalContextSnapshot($conversation, $workspace, $user),
                'tool_choice' => 'auto',
            ];
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

            // The tool loop does not classify free-form messages locally.
            // When a confirmation is pending, resolve the provider's waiting
            // function call with a structured handoff and let the model decide
            // whether this message changes the plan, asks a question, or starts
            // an unrelated read. The explicit confirmation endpoint remains
            // the only execution path for the pending write.
            $revisionCandidate = $this->prepareToolLoopPendingConfirmation(
                $conversation,
                $workspace,
                $user,
                $userMessage,
            );
            if ($revisionCandidate !== null) {
                $context['pending_confirmation_revision_id'] = $revisionCandidate->id;
                $context['operational_context'] = $this->operationalContextSnapshot($conversation, $workspace, $user);
            } elseif (($pendingClarification = $this->prepareToolLoopPendingClarification($conversation, $workspace, $user, $userMessage)) !== null) {
                $context['pending_clarification_id'] = $pendingClarification['clarification_id'];
                $context['operational_context'] = $this->operationalContextSnapshot($conversation, $workspace, $user);
            }

            $openAIConversationService = $this->openAIConversationService ?? app(OpenAIConversationService::class);
            $openAIConversationService->recoverUncertainTurn($conversation);
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
            $activeMetadata = $profile['metadata'];
            $discoveryEnabled = (bool) $profile['discovery_enabled'];
            $providerRetryCount = 0;
            $definitions = $this->toolLoopDefinitions($activeMetadata, $discoveryEnabled);
            $definitionMap = collect($profile['metadata'])->mapWithKeys(
                fn (array $definition): array => [str_replace('.', '_', (string) $definition['key']) => (string) $definition['key']]
            )->all();
            $maxIterations = max(1, (int) config('ai.max_orchestration_iterations', 5));
            $maxToolCalls = max(1, (int) config('ai.max_tool_calls_per_turn', 4));
            $retryBudget = new ToolLoopRetryBudget(
                (int) config('ai.retry_budgets.structural_plan_repairs', 1),
                (int) config('ai.retry_budgets.tool_argument_repairs', 1),
                $correlationId,
            );

            Log::info('ai.tool_loop.started', [
                'correlation_id' => $correlationId,
                'conversation_id' => $conversation->id,
                'profile' => $profile['profile'],
                'declared_tool_count' => count($activeMetadata),
                'tool_count' => count($activeMetadata),
                'tools_initial_count' => (int) $profile['initial_tool_count'],
                'tools_deferred_count' => (int) $profile['deferred_count'],
                'visible_namespaces' => collect($definitions)
                    ->where('type', 'namespace')
                    ->pluck('name')
                    ->values()
                    ->all(),
                'workspace_id' => $workspace->id,
                ...$this->toolLoopTraceContext((array) ($context['operational_context'] ?? [])),
            ]);
            if ($discoveryEnabled) {
                Log::info('ai.tool_discovery.enabled', [
                    'correlation_id' => $correlationId,
                    'declared_tool_count' => count($activeMetadata),
                    'tools_initial_count' => (int) $profile['initial_tool_count'],
                    'tools_deferred_count' => (int) $profile['deferred_count'],
                    'visible_namespaces' => collect($definitions)
                        ->where('type', 'namespace')
                        ->pluck('name')
                        ->values()
                        ->all(),
                    'workspace_id' => $workspace->id,
                ]);
            }

            $completionRepairs = 0;
            for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
                $this->chatStreamPublisher()->activity(
                    $conversation,
                    $assistantMessage,
                    'response',
                    $locale === 'es' ? 'Preparando la respuesta.' : 'Preparing the response.',
                );
                try {
                    $providerResult = $this->toolLoopProviderTurn(
                        $conversation,
                        $assistantMessage,
                        [
                            ...$context,
                            'tool_instructions' => $this->toolLoopInstructions($context, $discoveryEnabled),
                            'tool_dynamic_context' => $this->toolLoopDynamicContext($context),
                            'prompt_cache_key' => $this->promptCacheKey($profile['profile']),
                        ],
                        $definitions,
                        $responseId,
                        $nextInput,
                    );
                } catch (\Throwable $exception) {
                    if (
                        $this->deferUncertainProviderTurn($conversation, $exception, $aiRun)) {
                        throw $exception;
                    }
                    if (
                        $this->isTransientProviderFailure($exception)
                        && $providerRetryCount < max(0, (int) config('ai.retry_budgets.provider_transient_retries', 1))) {
                        $providerRetryCount++;
                        Log::warning('ai.provider.transient_retry', [
                            'attempt' => $providerRetryCount,
                            'correlation_id' => $correlationId,
                            'exception_class' => class_basename($exception),
                            'workspace_id' => $workspace->id,
                        ]);
                        Log::warning('objective.retry_scheduled', [
                            'attempt' => $providerRetryCount,
                            'correlation_id' => $correlationId,
                            'objective_id' => $aiRun?->objective_id,
                            'workspace_id' => $workspace->id,
                        ]);
                        $this->providerRetryBackoff($exception, $providerRetryCount, $aiRun);
                        $providerResult = $this->toolLoopProviderTurn(
                            $conversation,
                            $assistantMessage,
                            [
                                ...$context,
                                'tool_instructions' => $this->toolLoopInstructions($context, $discoveryEnabled),
                                'tool_dynamic_context' => $this->toolLoopDynamicContext($context),
                                'prompt_cache_key' => $this->promptCacheKey($profile['profile']),
                            ],
                            $definitions,
                            $responseId,
                            $nextInput,
                        );
                    } elseif ($providerProtocolRecoveryAttempted || ! $this->isOrphanedProviderToolCall($exception)) {
                        throw $exception;
                    } else {
                        $providerProtocolRecoveryAttempted = true;
                        $openAIConversationService->resetAfterProviderProtocolError($conversation);
                        $this->conversationContinuationLifecycle->clearProviderToolOutputs($conversation);
                        Log::warning('provider.protocol_recovered', [
                            'correlation_id' => $correlationId,
                            'conversation_id' => $conversation->id,
                            'objective_id' => $aiRun?->objective_id,
                            'workspace_id' => $workspace->id,
                        ]);
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

                        $providerResult = $this->toolLoopProviderTurn(
                            $conversation,
                            $assistantMessage,
                            [
                                ...$context,
                                'tool_instructions' => $this->toolLoopInstructions($context, $discoveryEnabled),
                                'tool_dynamic_context' => $this->toolLoopDynamicContext($context),
                                'prompt_cache_key' => $this->promptCacheKey($profile['profile']),
                            ],
                            $definitions,
                            $responseId,
                            $nextInput,
                        );
                    }
                }
                $this->logToolDiscoveryResult($providerResult, $definitionMap, $activeMetadata, $correlationId, $workspace->id);
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
                    $feedback = $this->objectiveCompletionFeedback($aiRun, $conversation);
                    if ($feedback !== null && $completionRepairs++ < 2 && $iteration + 1 < $maxIterations) {
                        $nextInput = [['role' => 'developer', 'content' => $feedback]];
                        $context['operational_context'] = $this->operationalContextSnapshot($conversation->fresh(), $workspace, $user);
                        continue;
                    }
                    $text = trim((string) ($providerResult['output_text'] ?? ''));
                    if ($text === '') {
                        throw new \RuntimeException('The provider returned neither a tool call nor a final response.');
                    }
                    $composedResult = ToolLoopResultComposer::compose($supportingResults, $lastToolResult);
                    $result = $this->toolLoopFinalResult($composedResult, $text, $locale);
                    $result['entity_refs'] = $entityRefs !== [] ? $entityRefs : ($result['entity_refs'] ?? []);
                    $result['tool_keys'] = $toolKeys;
                    $result['interaction_mode'] = 'tool_loop';
                    $result['usage'] = $usage;
                    $providerMetadata['efficiency'] = [
                        ...$retryBudget->metrics(),
                        'discovery_fallback_used' => false,
                        'iterations' => $iteration + 1,
                        'provider_calls' => $iteration + 1,
                        'provider_retry_count' => $providerRetryCount,
                        'serialized_context_size' => strlen((string) json_encode($context['operational_context'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        'tool_calls' => $toolCount,
                        'tools_deferred_count' => (int) $profile['deferred_count'],
                        'tools_initial_count' => (int) $profile['initial_tool_count'],
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
                        $toolKeys,
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
                    $rawResult = null;

                    if ($actionKey === null || $arguments === null) {
                        $toolResult = ToolObservation::make(
                            false,
                            $actionKey === null ? 'TOOL_NOT_FOUND' : 'INVALID_TOOL_ARGUMENTS',
                            'The requested tool or its arguments are invalid.',
                            [],
                            ['recoverable' => true],
                            ['ask_user_for_clarification'],
                        );
                    } elseif (($budgetGuard = $retryBudget->guard($actionKey, $arguments)) !== null) {
                        $toolResult = $budgetGuard;
                        $rawResult = null;
                        Log::warning('ai.tool_call.retry_blocked', [
                            'action_key' => $actionKey,
                            'call_id' => $callId,
                            'correlation_id' => $correlationId,
                            'error_code' => data_get($budgetGuard, 'error.code', 'RETRY_BUDGET_EXHAUSTED'),
                            'workspace_id' => $workspace->id,
                        ]);
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
                                'error_code' => data_get($referenceError, 'error.code', 'INVALID_TOOL_REFERENCE'),
                                'input_keys' => array_keys($arguments),
                                'workspace_id' => $workspace->id,
                            ]);
                            $toolResult = $referenceError;
                        } else {
                            $toolInput = $actionKey === 'recipes.create'
                                ? ['recipe_draft' => $this->mergePendingRecipeDraft($conversation, $arguments)]
                                : $arguments;
                            try {
                                if (($tool['mode'] ?? null) === 'write'
                                    && $actionKey !== 'objectives.cancel'
                                    && ! $objective) {
                                    $objective = $objectiveLifecycle->startOrResume(
                                        $conversation,
                                        $workspace,
                                        $user,
                                        $userMessage,
                                        (string) ($userMessage->content_text ?? ''),
                                        $correlationId,
                                    );
                                    $objectiveLifecycle->attachRun($objective, $aiRun);
                                    $context['objective_id'] = (string) $objective->id;
                                    $context['objective'] = $objectiveLifecycle->snapshot($objective);
                                    $context['operational_context'] = $this->operationalContextSnapshot($conversation->fresh(), $workspace, $user);
                                }
                                $this->chatStreamPublisher()->activity(
                                    $conversation,
                                    $assistantMessage,
                                    'workspace',
                                    $locale === 'es' ? 'Consultando información del workspace.' : 'Checking workspace information.',
                                );
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
                                        'tool_key' => $actionKey,
                                        'visible' => $this->includeSupportingResult($tool),
                                    ];
                                }
                                $toolResult = $this->toolResultForModel($tool, $rawResult);
                                $this->persistOperationalContext($conversation, $workspace, $user, $entityRefs, $actionKey, $rawResult, $callId);
                                $context['operational_context'] = $this->operationalContextSnapshot($conversation, $workspace, $user);
                                Log::info('ai.tool_call.result', [
                                    'action_key' => $actionKey,
                                    'call_id' => $callId,
                                    'correlation_id' => $correlationId,
                                    'result_status' => $rawResult['status']
                                        ?? $rawResult['workflow_status']
                                        ?? (is_array($rawResult['confirmation'] ?? null) ? 'confirmation_required' : null),
                                    'workspace_id' => $workspace->id,
                                    ...$this->toolLoopTraceContext((array) ($context['operational_context'] ?? [])),
                                ]);
                            } catch (\Throwable $exception) {
                                $toolKeys[] = $actionKey;
                                $lastToolResult = [
                                    'status' => 'failed',
                                    'blocks' => [],
                                    'entity_refs' => [],
                                    'result_ref_json' => [],
                                ];
                                $mappedError = (new ErrorResponseMapper)->map($exception, $locale, $correlationId);
                                $this->logToolDatabaseFailure($exception, $actionKey, $callId, $correlationId, $workspace->id);
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
                                $toolResult = (new ErrorResponseMapper)->forModel($exception, $locale, $correlationId);
                            }
                        }
                    }
                    $toolResult = $retryBudget->apply($actionKey, $arguments, $toolResult);
                    $toolCount++;
                    if (data_get($toolResult, 'error.code') === 'RETRY_BUDGET_EXHAUSTED') {
                        $pendingActionKey = (string) ($actionKey ?? $functionName);
                        $this->conversationContinuationLifecycle->registerPendingProviderToolCall(
                            $conversation,
                            $callId,
                            null,
                            $pendingActionKey,
                        );
                        $this->conversationContinuationLifecycle->resolvePendingProviderToolCall(
                            $conversation,
                            null,
                            $pendingActionKey,
                            $toolResult,
                            $callId,
                        );

                        throw new AiRuntimeException(
                            'WORKFLOW_RETRY_EXHAUSTED',
                            'workflow_retry_exhausted',
                            true,
                            'The workflow retry budget was exhausted.',
                        );
                    }
                    $currentToolResult = isset($rawResult) && is_array($rawResult) ? $rawResult : [];
                    $status = $currentToolResult['status']
                        ?? $currentToolResult['workflow_status']
                        ?? (
                            is_array($currentToolResult['confirmation'] ?? null)
                                ? 'confirmation_required'
                                : null
                        );
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
                        $providerMetadata['efficiency'] = [
                            ...$retryBudget->metrics(),
                            'discovery_fallback_used' => false,
                            'iterations' => $iteration + 1,
                            'provider_retry_count' => $providerRetryCount,
                            'tools_deferred_count' => (int) $profile['deferred_count'],
                            'tools_initial_count' => (int) $profile['initial_tool_count'],
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
            $this->deferUncertainProviderTurn($conversation, $exception, $aiRun);
            $terminationReason = $this->toolLoopFailureTerminationReason($exception);
            $objective = $objective?->fresh();
            if ($objective && in_array($terminationReason, ['paused', 'retrying', 'needs_review'], true)) {
                $objective->forceFill([
                    'status' => $terminationReason,
                    'paused_at' => now(),
                    'last_heartbeat_at' => now(),
                    'error_code' => $this->errorCodeFor($exception),
                    'error_message_safe' => $this->safeErrorDetail($locale, $exception),
                ])->save();
            }
            $publicError = (new ErrorResponseMapper)->map($exception, $locale, $correlationId, array_filter([
                'ai_run_id' => $aiRun->id,
                'objective_id' => $objective?->id,
                'preserved_progress' => $objective !== null,
                'completed_count' => $objective?->completed_count,
                'pending_count' => $objective?->pending_count,
            ], static fn (mixed $value): bool => $value !== null));
            Log::warning('ai.tool_loop.failed', [
                'correlation_id' => $correlationId,
                'exception_class' => class_basename($exception),
                'error_code' => $publicError['error_code'],
                'termination_reason' => $terminationReason,
                'tool_count' => count($toolKeys),
                'workspace_id' => $workspace->id,
                ...$this->toolLoopTraceContext((array) ($context['operational_context'] ?? [])),
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
            $this->chatStreamPublisher()->failed($conversation, $assistantMessage);
            $this->completeAiRunSafely($aiRun, [
                'completed_at' => now(),
                'error_code' => $publicError['error_code'],
                'error_message' => $publicError['error_code'],
                'metadata' => [
                    ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                    'termination_reason' => $terminationReason,
                    'tool_count' => count($toolKeys),
                ],
                'status' => in_array($terminationReason, ['paused', 'retrying', 'needs_review'], true)
                    ? $terminationReason
                    : 'failed',
                'usage_json' => $usage,
            ], $correlationId);

            return $assistantMessage->fresh('blocks');
        }
    }

    private function failUnavailableAiFirstRuntime(
        Conversation $conversation,
        Workspace $workspace,
        Message $userMessage,
        string $locale,
        string $timezone,
        string $correlationId,
    ): Message {
        $assistantMessage = $this->assistantMessageWriter->createPending(
            $conversation,
            $workspace,
            $locale,
            $userMessage,
            ['source' => 'assistant-response', 'orchestration_version' => 'tool-loop-v1'],
        );
        $aiRun = $this->startRun(
            $assistantMessage,
            $userMessage,
            $workspace,
            $locale,
            $timezone,
            $correlationId,
        );
        $publicError = (new ErrorResponseMapper)->map(
            new AiProviderUnavailableException(
                'AI-first requires a tool-calling provider.',
                ['provider' => 'unavailable'],
            ),
            $locale,
            $correlationId,
        );

        Log::warning('ai.tool_loop.unavailable', [
            'conversation_id' => $conversation->id,
            'correlation_id' => $correlationId,
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
            ['source' => 'assistant-response', 'orchestration_version' => 'tool-loop-v1'],
        );
        $this->chatStreamPublisher()->failed($conversation, $assistantMessage);
        $this->completeAiRunSafely($aiRun, [
            'error_code' => $publicError['error_code'],
            'error_message' => $publicError['error_code'],
            'metadata' => [
                ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                'correlation_id' => $correlationId,
                'failure_reason' => 'tool_calling_provider_unavailable',
            ],
            'status' => 'failed',
        ], $correlationId);

        return $assistantMessage->fresh('blocks');
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $definitions
     * @param  array<int, array<string, mixed>>  $input
     * @return array<string, mixed>
     */
    private function toolLoopProviderTurn(
        Conversation $conversation,
        Message $assistantMessage,
        array $context,
        array $definitions,
        ?string $responseId,
        array $input,
    ): array {
        if (! $this->toolCallingProvider instanceof ToolCallingProvider) {
            throw new \RuntimeException('The configured provider does not support the tool loop.');
        }

        if (
            (bool) config('ai.chat_streaming_enabled', true)
            && $this->toolCallingProvider instanceof StreamingToolCallingProvider
        ) {
            return $this->toolCallingProvider->streamToolTurn(
                $context,
                $definitions,
                $responseId,
                $input,
                function (array $event) use ($conversation, $assistantMessage): void {
                    if (($event['type'] ?? null) === 'output_text.delta' && is_string($event['delta'] ?? null)) {
                        $this->chatStreamPublisher()->textDelta(
                            $conversation,
                            $assistantMessage,
                            $event['delta'],
                        );
                    }
                },
            );
        }

        return $this->toolCallingProvider->toolTurn($context, $definitions, $responseId, $input);
    }

    private function chatStreamPublisher(): ChatStreamPublisher
    {
        return $this->chatStreamPublisher ??= app(ChatStreamPublisher::class);
    }

    /**
     * Resume the provider conversation after a confirmation has executed.
     * This lets a single user request continue through dependent reads or
     * writes without replaying the original intent or creating a second
     * orchestration pipeline.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $initialResult
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
        if (! $this->toolCallingProvider instanceof ToolCallingProvider) {
            return $initialResult;
        }

        $recovery = $this->openAIConversationService ?? app(OpenAIConversationService::class);
        $rehydrated = $recovery->recoverUncertainTurn($conversation);
        if ($rehydrated) {
            $recovery->ensure($conversation, $workspace, $user, $assistantMessage->id, [
                'operational_context' => $this->operationalContextSnapshot($conversation, $workspace, $user),
                'confirmed_execution' => $context['confirmed_execution'] ?? [],
            ]);
        }
        $pendingOutputs = $this->conversationContinuationLifecycle
            ->pendingProviderToolOutputs($conversation);
        $openAIConversationId = trim((string) ($conversation->openai_conversation_id ?? ''));
        if ((! $rehydrated && $pendingOutputs === []) || $openAIConversationId === '') {
            return $initialResult;
        }

        $context['openai_conversation_id'] = $openAIConversationId;
        // The confirmation itself was already handled by the server. The
        // provider receives the resolved function output, not "confirm" as a
        // new intent.
        $context['message'] = '';
        $context['tool_choice'] = 'auto';
        $context['pending_provider_tool_outputs'] = $pendingOutputs;
        $toolProfileSelector = $this->toolProfileSelector ?? app(ToolProfileSelector::class);
        $profile = $toolProfileSelector->select($context, $this->toolRegistry->allMetadata());
        $metadata = $profile['metadata'];
        $discoveryEnabled = (bool) $profile['discovery_enabled'];
        $providerRetryCount = 0;
        $context['tool_dynamic_context'] = $this->toolLoopDynamicContext($context);
        $context['tool_instructions'] = $this->toolLoopInstructions($context, $discoveryEnabled)
            ."\nContinuation contract: the confirmed write has already executed. Do not repeat it. Continue every remaining instruction from the original user request. Use exact IDs from the confirmed result or a read tool before updating a record. If a tool rejects arguments, repair the arguments once from its safe validation details; do not resend the same arguments. Complete remaining writes before final reads. Return a concise final response when all requested work is complete.";
        $context['prompt_cache_key'] = $this->promptCacheKey($profile['profile']);

        $definitions = $this->toolLoopDefinitions($metadata, $discoveryEnabled);
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
        $usage = [];
        $retryBudget = new ToolLoopRetryBudget(
            (int) config('ai.retry_budgets.structural_plan_repairs', 1),
            (int) config('ai.retry_budgets.tool_argument_repairs', 1),
            (string) ($context['correlation_id'] ?? ''),
        );

        if ($discoveryEnabled) {
            Log::info('ai.tool_discovery.enabled', [
                'continuation' => true,
                'correlation_id' => $context['correlation_id'] ?? null,
                'tools_deferred_count' => (int) $profile['deferred_count'],
                'tools_initial_count' => (int) $profile['initial_tool_count'],
                'workspace_id' => $workspace->id,
            ]);
        }

        $completionRepairs = 0;
        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            try {
                $providerResult = $this->toolCallingProvider->toolTurn(
                    $context,
                    $definitions,
                    $responseId,
                    $nextInput
                );
            } catch (\Throwable $exception) {
                if ($this->deferUncertainProviderTurn($conversation, $exception, $aiRun)) {
                    throw $exception;
                }
                if ($this->isTransientProviderFailure($exception)
                    && $providerRetryCount < max(0, (int) config('ai.retry_budgets.provider_transient_retries', 1))) {
                    $providerRetryCount++;
                    Log::warning('ai.provider.transient_retry', [
                        'attempt' => $providerRetryCount,
                        'continuation' => true,
                        'correlation_id' => $context['correlation_id'] ?? null,
                        'exception_class' => class_basename($exception),
                        'workspace_id' => $workspace->id,
                    ]);
                    Log::warning('objective.retry_scheduled', [
                        'attempt' => $providerRetryCount,
                        'continuation' => true,
                        'correlation_id' => $context['correlation_id'] ?? null,
                        'objective_id' => $aiRun?->objective_id,
                        'workspace_id' => $workspace->id,
                    ]);
                    $this->providerRetryBackoff($exception, $providerRetryCount, $aiRun);
                    $providerResult = $this->toolCallingProvider->toolTurn($context, $definitions, $responseId, $nextInput);
                } else {
                    throw $exception;
                }
            }
            $this->logToolDiscoveryResult(
                $providerResult,
                $definitionMap,
                $metadata,
                (string) ($context['correlation_id'] ?? ''),
                $workspace->id,
            );
            $usage = $this->mergeUsage($usage, (array) ($providerResult['usage'] ?? []));
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
                $feedback = $this->objectiveCompletionFeedback($aiRun, $conversation);
                if ($feedback !== null && $completionRepairs++ < 2 && $iteration + 1 < $maxIterations) {
                    $nextInput = [['role' => 'developer', 'content' => $feedback]];
                    $context['operational_context'] = $this->operationalContextSnapshot($conversation->fresh(), $workspace, $user);
                    $context['tool_dynamic_context'] = $this->toolLoopDynamicContext($context);
                    continue;
                }
                $text = trim((string) ($providerResult['output_text'] ?? ''));
                if ($text === '') {
                    throw new \RuntimeException('The provider returned neither a tool call nor a final response.');
                }
                $composedResult = ToolLoopResultComposer::compose($supportingResults, $lastResult);
                $terminalResult = $this->toolLoopFinalResult($composedResult, $text, $locale);
                $terminalResult['entity_refs'] = $entityRefs !== []
                    ? $entityRefs
                    : (array) ($terminalResult['entity_refs'] ?? []);
                $terminalResult['tool_keys'] = array_values(array_unique($toolKeys));
                $terminalResult['interaction_mode'] = 'tool_loop';
                $terminalResult['usage'] = $usage;
                $terminalResult['tool_profile'] = $profile['profile'];
                $terminalResult['efficiency'] = [
                    ...$retryBudget->metrics(),
                    'discovery_fallback_used' => false,
                    'iterations' => $iteration + 1,
                    'provider_calls' => $iteration + 1,
                    'provider_retry_count' => $providerRetryCount,
                    'serialized_context_size' => strlen((string) json_encode($context['operational_context'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'tool_calls' => $toolCount,
                    'tools_deferred_count' => (int) $profile['deferred_count'],
                    'tools_initial_count' => (int) $profile['initial_tool_count'],
                ];

                return $terminalResult;
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
                    $toolResult = ToolObservation::make(
                        false,
                        $actionKey === null ? 'TOOL_NOT_FOUND' : 'INVALID_TOOL_ARGUMENTS',
                        'The requested tool or its arguments are invalid.',
                        [],
                        ['recoverable' => true],
                        ['ask_user_for_clarification'],
                    );
                } elseif (($budgetGuard = $retryBudget->guard($actionKey, $arguments)) !== null) {
                    $toolResult = $budgetGuard;
                    Log::warning('ai.continuation.tool_call.retry_blocked', [
                        'action_key' => $actionKey,
                        'call_id' => $callId,
                        'correlation_id' => $context['correlation_id'] ?? null,
                        'error_code' => data_get($budgetGuard, 'error.code', 'RETRY_BUDGET_EXHAUSTED'),
                        'workspace_id' => $workspace->id,
                    ]);
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
                            'error_code' => data_get($referenceError, 'error.code', 'INVALID_TOOL_REFERENCE'),
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
                                    'tool_key' => $actionKey,
                                    'visible' => $this->includeSupportingResult($tool),
                                ];
                            }
                            $toolResult = $this->toolResultForModel($tool, $rawResult);
                            $this->persistOperationalContext($conversation, $workspace, $user, $entityRefs, $actionKey, $rawResult, $callId);
                            $context['operational_context'] = $this->operationalContextSnapshot($conversation, $workspace, $user);
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
                            if ($status === 'confirmation_required') {
                                $this->conversationContinuationLifecycle->registerPendingProviderToolCall(
                                    $conversation,
                                    $callId,
                                    data_get($rawResult, 'confirmation.confirmation_id'),
                                    $actionKey
                                );
                                $lastResult['tool_keys'] = array_values(array_unique($toolKeys));
                                $lastResult['entity_refs'] = $entityRefs;
                                $lastResult['usage'] = $usage;
                                $lastResult['tool_profile'] = $profile['profile'];
                                $lastResult['efficiency'] = [
                                    ...$retryBudget->metrics(),
                                    'discovery_fallback_used' => false,
                                    'iterations' => $iteration + 1,
                                    'provider_retry_count' => $providerRetryCount,
                                    'tools_deferred_count' => (int) $profile['deferred_count'],
                                    'tools_initial_count' => (int) $profile['initial_tool_count'],
                                ];

                                return $lastResult;
                            }
                        } catch (\Throwable $exception) {
                            $toolResult = (new ErrorResponseMapper)->forModel($exception, $locale, (string) ($context['correlation_id'] ?? ''));
                            $lastResult = ['status' => 'failed', 'blocks' => [], 'entity_refs' => []];
                            Log::warning('ai.continuation.tool_call.failed', [
                                'action_key' => $actionKey,
                                'call_id' => $callId,
                                'correlation_id' => $context['correlation_id'] ?? null,
                                'error_code' => data_get($toolResult, 'error.code', 'TOOL_FAILED'),
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
                $toolResult = $retryBudget->apply($actionKey, $arguments, $toolResult);
                $toolCount++;
                $nextInput[] = [
                    'type' => 'function_call_output',
                    'call_id' => $callId,
                    'output' => json_encode($toolResult, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ];
            }
            $context['pending_provider_tool_outputs'] = [];
        }

        throw ValidationException::withMessages(['tools' => ['The tool loop did not reach a final response.']]);
    }

    private function toolLoopFailureTerminationReason(\Throwable $exception): string
    {
        if ($exception instanceof AiRuntimeException) {
            return $exception->internalCode() === 'WORKFLOW_RETRY_EXHAUSTED'
                ? 'paused'
                : 'needs_review';
        }
        if ($exception instanceof AiProviderException) {
            return match ($exception->internalCode()) {
                'AI_PROTOCOL_STATE_CORRUPTED' => 'needs_review',
                'AI_RATE_LIMITED', 'AI_TIMEOUT', 'AI_NETWORK_ERROR',
                'AI_PROVIDER_UNAVAILABLE', 'AI_CONVERSATION_LOCKED',
                'AI_QUOTA_EXHAUSTED' => 'paused',
                default => 'nonrecoverable_error',
            };
        }
        if ($exception instanceof ValidationException) {
            $toolErrors = collect((array) ($exception->errors()['tools'] ?? []))
                ->filter(fn (mixed $message): bool => is_string($message))
                ->map(fn (string $message): string => Str::lower($message));

            if ($toolErrors->contains(
                fn (string $message): bool => Str::contains($message, ['tool call limit', 'tool loop did not reach'])
            )) {
                return 'tool_iteration_limit';
            }
        }

        return 'nonrecoverable_error';
    }

    /** @return array<int, array<string, mixed>> */
    /** @param array<int, array<string, mixed>> $metadata */
    private function toolLoopDefinitions(array $metadata = [], bool $discoveryEnabled = false): array
    {
        $factory = new OpenAiFunctionSchemaFactory($metadata);
        $registry = isset($this->toolRegistry) ? $this->toolRegistry : new ToolRegistry;
        $metadata = $metadata !== [] ? $metadata : $registry->allMetadata();

        $entries = collect($metadata)
            ->map(fn (array $metadata): array => [
                'metadata' => $metadata,
                'definition' => $factory->make([
                    'action_key' => $metadata['key'],
                    'description' => trim(sprintf(
                        '%s Permission: %s. Confirmation required: %s. Side effects: %s.',
                        $metadata['description'],
                        $metadata['permission'] ?? 'server authorization',
                        ($metadata['requires_confirmation'] ?? false) ? 'yes' : 'no',
                        ($metadata['mode'] ?? 'read') === 'read' ? 'none' : 'writes workspace data'
                    )),
                    'input_schema' => $metadata['input_schema'] ?? [],
                    'defer_loading' => (bool) ($metadata['defer_loading'] ?? false),
                ]),
            ])
            ->values()
            ->all();

        if (! $discoveryEnabled) {
            return collect($entries)->pluck('definition')->all();
        }

        $definitions = collect($entries)
            ->groupBy(function (array $entry): string {
                $key = (string) ($entry['metadata']['key'] ?? '');
                $module = (string) ($entry['metadata']['module'] ?? 'workspace');
                $mode = (string) ($entry['metadata']['mode'] ?? 'read');

                return match (true) {
                    str_starts_with($key, 'menus.items.') => 'menu_items',
                    $module === 'tasks' && $mode === 'read' => 'tasks_query',
                    $module === 'tasks' => 'tasks_mutation',
                    in_array($module, ['workspace', 'members', 'availability'], true) => 'workspace_members',
                    in_array($key, ['objectives.define', 'objectives.cancel'], true)
                        || str_starts_with($key, 'execution_plans.') => 'ai_workflow',
                    default => $module,
                };
            })
            ->flatMap(function ($entries, string $group): array {
                return collect($entries)
                    ->chunk(9)
                    ->values()
                    ->map(function ($chunk, int $index) use ($group): array {
                        $name = $index === 0 ? $group : $group.'_'.($index + 1);

                        return [
                            'type' => 'namespace',
                            'name' => $name,
                            'description' => ucfirst(str_replace('_', ' ', $group)).' workspace operations.',
                            'tools' => $chunk->pluck('definition')->values()->all(),
                        ];
                    })
                    ->all();
            })
            ->values()
            ->all();

        return [...$definitions, ['type' => 'tool_search']];
    }

    /** @param array<string, mixed> $context */
    private function toolLoopInstructions(array $context, bool $discoveryEnabled = false): string
    {
        return implode("\n", [
            (string) ($context['system_instructions'] ?? ''),
            $discoveryEnabled
                ? 'Domain tools are grouped in deferred namespaces. Use hosted Tool Search to load only the namespace functions needed for the current request; discovery itself does not complete the work.'
                : 'The complete authorized tool catalog is available for this turn.',
            'Use the active operational state as authoritative. If a clarification is pending, apply the user reply to that exact field and call the same domain tool with corrected structured arguments; never locally parse or guess the reply.',
            'If a scoped objective exists, use execution_plans.create (or execution_plans.latest then execution_plans.revise for recovery). Direct domain writes are rejected until the scoped workflow is planned.',
            'Tool schemas and descriptions are authoritative. Correct recoverable tool errors with the allowed next action, preserve unresolved work, and never repeat completed writes.',
        ]);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function toolLoopDynamicContext(array $context): array
    {
        $operationalContext = is_array($context['operational_context'] ?? null)
            ? $context['operational_context']
            : [];
        if (is_array($operationalContext['objective'] ?? null)) {
            $operationalContext['objective'] = $this->compactOversizedObjectiveSnapshot(
                $operationalContext['objective'],
            );
        }
        if (is_array($operationalContext['pending_clarification'] ?? null)) {
            $clarification = $operationalContext['pending_clarification'];
            $operationalContext = array_filter([
                'version' => $operationalContext['version'] ?? 1,
                'conversation_id' => $operationalContext['conversation_id'] ?? null,
                'workspace_id' => $operationalContext['workspace_id'] ?? null,
                'actor_id' => $operationalContext['actor_id'] ?? null,
                'objective' => $this->compactOversizedObjectiveSnapshot($operationalContext['objective'] ?? null, true),
                'focus' => $operationalContext['focus'] ?? null,
                'draft' => array_filter([
                    'draft_id' => $clarification['draft_id'] ?? null,
                    'revision' => $clarification['draft_revision'] ?? null,
                    'action_key' => $clarification['action_key'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                'pending_clarification' => $clarification,
            ], static fn (mixed $value): bool => $value !== null && $value !== []);
        }

        $dynamic = [
            'operational_context' => $operationalContext,
            'temporal' => $context['temporal_context'] ?? [],
        ];
        if (is_array($context['confirmed_execution'] ?? null)) {
            $dynamic['confirmed_execution'] = $context['confirmed_execution'];
            // Workflow/objective snapshots are already present in operational_context.
            // Keep domain evidence (including relationships) for normal confirmations.
            $dynamic['confirmed_execution']['result'] = collect((array) ($context['confirmed_execution']['result'] ?? []))
                ->except(['execution_plan', 'objective', 'completion_steps', 'recovery_items'])->all();
            if (strlen((string) json_encode($dynamic['confirmed_execution']['result'])) > 12000) {
                $dynamic['confirmed_execution']['result'] = collect($dynamic['confirmed_execution']['result'])
                    ->only(['id', 'name', 'type', 'current_version_id', 'execution_plan_id', 'objective_id', 'status', 'completed_count', 'failed_count', 'relationships'])->all();
            }
        }

        $maximum = max(8000, (int) config('ai.context.max_serialized_characters', 60000));
        $encoded = json_encode($dynamic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded) && strlen($encoded) > $maximum) {
            $beforeSize = strlen($encoded);
            unset(
                $dynamic['operational_context']['historical_candidate_sets'],
                $dynamic['operational_context']['historical_entity_refs']
            );
            $dynamic['operational_context']['active_entity_refs'] = array_slice(
                (array) data_get($dynamic, 'operational_context.active_entity_refs', []),
                0,
                12,
            );
            $dynamic['operational_context']['objective'] = $this->compactOversizedObjectiveSnapshot(
                data_get($dynamic, 'operational_context.objective'),
            );
            $dynamic['operational_context']['execution_plan'] = $this->compactOversizedExecutionPlanSnapshot(
                data_get($dynamic, 'operational_context.execution_plan'),
            );
            $compacted = json_encode($dynamic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($compacted) && strlen($compacted) > $maximum) {
                $operational = (array) ($dynamic['operational_context'] ?? []);
                $dynamic['operational_context'] = array_filter([
                    'workspace_id' => $operational['workspace_id'] ?? null,
                    'objective' => $this->compactOversizedObjectiveSnapshot($operational['objective'] ?? null, true),
                    'focus' => $operational['focus'] ?? null,
                    'pending_confirmation' => $operational['pending_confirmation'] ?? null,
                    'pending_clarification' => $operational['pending_clarification'] ?? null,
                    'execution_plan' => $this->compactOversizedExecutionPlanSnapshot($operational['execution_plan'] ?? null, true),
                ], static fn (mixed $value): bool => $value !== null && $value !== []);
            }
            Log::info('ai.objective.context_compacted', [
                'objective_id' => data_get($dynamic, 'operational_context.objective.id'),
                'serialized_context_size_before' => $beforeSize,
                'serialized_context_size_after' => strlen((string) json_encode($dynamic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'workspace_id' => data_get($dynamic, 'operational_context.workspace_id'),
            ]);
        }

        return $dynamic;
    }

    private function compactOversizedObjectiveSnapshot(mixed $objective, bool $countsOnly = false): mixed
    {
        if (! is_array($objective)) {
            return $objective;
        }

        $compact = collect($objective)->only([
            'id', 'revision', 'status', 'description', 'operation_count', 'completed_count',
            'pending_count', 'blocked_count', 'failed_count', 'needs_review_count', 'blockers',
            'expected_results', 'approval_digest', 'confirmation_id', 'error_code',
            'scope_defined', 'required_facts', 'resolved_facts', 'unplanned_results', 'operations',
            'preserved_progress', 'updated_at',
        ])->all();
        $compact['description'] = Str::limit((string) ($compact['description'] ?? ''), $countsOnly ? 300 : 1200, '…');
        if ($countsOnly) {
            // Obligations and corrected facts must survive even the smallest snapshot.
            $compact['operations'] = collect($compact['operations'] ?? [])->map(fn (array $operation): array =>
                collect($operation)->only(['operation_key', 'action_key', 'status', 'result_ref'])->all())->all();
        } else {
            $compact['blockers'] = array_slice((array) ($compact['blockers'] ?? []), 0, 20);
            $compact['expected_results'] = collect((array) ($compact['expected_results'] ?? []))
                ->take(100)
                ->map(fn (mixed $result): mixed => is_array($result)
                    ? collect($result)->only(['result_key', 'required'])->all()
                    : $result)
                ->values()
                ->all();
        }

        return array_filter($compact, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function compactOversizedExecutionPlanSnapshot(mixed $plan, bool $countsOnly = false): mixed
    {
        if (! is_array($plan)) {
            return $plan;
        }

        $compact = collect($plan)->only([
            'id', 'objective_id', 'revision', 'status', 'operation_count', 'completed_count',
            'pending_count', 'blocked_count', 'failed_count', 'needs_review_count', 'steps',
        ])->all();
        if ($countsOnly) {
            unset($compact['steps']);
        } else {
            $compact['steps'] = collect((array) ($compact['steps'] ?? []))
                ->take(100)
                ->map(fn (mixed $step): mixed => is_array($step)
                    ? collect($step)->only(['id', 'step_key', 'action_key', 'status', 'is_required'])->all()
                    : $step)
                ->values()
                ->all();
        }

        return array_filter($compact, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    /** @param array<string, mixed> $operationalContext @return array<string, mixed> */
    private function toolLoopTraceContext(array $operationalContext): array
    {
        $executionPlan = is_array($operationalContext['execution_plan'] ?? null)
            ? $operationalContext['execution_plan']
            : [];
        $steps = collect($executionPlan['steps'] ?? [])
            ->filter(fn (mixed $step): bool => is_array($step));

        return [
            'workflow_id' => $executionPlan['id'] ?? data_get($operationalContext, 'focus.workflow_id'),
            'active_operation' => data_get($operationalContext, 'focus.operation_id'),
            'pending_operation_count' => $steps
                ->reject(fn (array $step): bool => in_array($step['status'] ?? null, ['completed', 'cancelled'], true))
                ->count(),
            'pending_confirmation' => data_get($operationalContext, 'pending_confirmation.confirmation_id'),
            'active_candidate_set' => data_get($operationalContext, 'active_candidate_set.id'),
        ];
    }

    /**
     * Read tools may be needed to resolve an entity without being part of the
     * user-facing answer. The tool remains available to the model and its
     * result remains in the continuation context; this only controls whether
     * it is composed alongside a later visible result.
     *
     * @param  array<string, mixed>  $tool
     */
    private function includeSupportingResult(array $tool): bool
    {
        return ($tool['include_in_supporting_results'] ?? true) !== false;
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
        $safeDetailSearch = ($tool['mode'] ?? null) === 'read'
            && in_array($tool['key'], [
                'menus.show', 'recipes.detail', 'recipes.versions', 'recipes.scale',
                'events.detail', 'clients.detail', 'contacts.detail', 'venues.detail',
                'tasks.detail', 'tasks.read', 'documents.detail', 'beos.detail', 'beos.versions',
                'prep.detail', 'prep.items.detail', 'teams.detail', 'stations.detail', 'shifts.detail',
            ], true);
        foreach ($pairs as $searchKey => $idKey) {
            if (filled($arguments[$searchKey] ?? null) && blank($arguments[$idKey] ?? null)) {
                $searchResolvedByCreateContract = ($tool['operation_type'] ?? null) === 'create'
                    && in_array($searchKey, (array) ($tool['reference_fields'] ?? []), true);
                $taskRelationshipSearch = in_array($tool['key'], ['tasks.update', 'tasks.status.update', 'tasks.complete'], true)
                    && in_array($searchKey, ['member_search', 'team_search', 'station_search', 'event_search'], true);
                if (($searchKey === 'task_search' && ($bulkTaskSelector || $assignmentBySearch))
                    || ($searchKey === 'member_search' && $tool['key'] === 'tasks.assign')
                    || $taskRelationshipSearch
                    || $safeDetailSearch
                    || $searchResolvedByCreateContract) {
                    continue;
                }

                return ToolObservation::make(
                    false,
                    'ENTITY_ID_REQUIRED',
                    'This tool requires an exact stable ID. Use the matching search or list tool first, then retry with the returned ID.',
                    ['required_id' => $idKey],
                    ['recoverable' => true],
                    [$this->searchActionForTool($tool)],
                );
            }
        }

        if ($bulkTaskSelector || $assignmentBySearch) {
            return null;
        }

        if (($tool['target_entity_required'] ?? false) && ! collect($pairs)->contains(
            fn (string $idKey): bool => filled($arguments[$idKey] ?? null)
        ) && ($tool['operation_type'] ?? null) !== 'create' && ! $safeDetailSearch) {
            return ToolObservation::make(
                false,
                'ENTITY_ID_REQUIRED',
                'This operation requires an exact stable entity ID from a prior tool result.',
                [],
                ['recoverable' => true],
                [$this->searchActionForTool($tool)],
            );
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
        $recipeDraftState = is_array($metadata['active_recipe_draft_state'] ?? null)
            ? $metadata['active_recipe_draft_state']
            : [];
        $pendingConfirmation = $this->pendingConfirmationSnapshot($conversation, $workspace);
        $executionPlan = $this->activeExecutionPlanSnapshot($conversation, $workspace);
        $objective = filled($metadata['active_ai_objective_id'] ?? null)
            ? AiObjective::query()
                ->where('workspace_id', $workspace->id)
                ->where('conversation_id', $conversation->id)
                ->find((string) $metadata['active_ai_objective_id'])
            : null;
        $candidateSets = collect($state['candidate_sets'] ?? [])
            ->filter(fn (mixed $set): bool => is_array($set))
            ->values();
        $activeCandidateSet = $candidateSets->firstWhere('status', 'active');
        $historicalCandidateSets = $candidateSets
            ->reject(fn (array $set): bool => ($set['status'] ?? null) === 'active')
            ->take(-5)
            ->values()
            ->all();
        $pendingClarification = $this->pendingClarificationSnapshot($conversation, $workspace, $user);
        $activeOperation = is_array($executionPlan)
            ? ($executionPlan['current_operation'] ?? null)
            : ($state['last_operation']['action_key'] ?? null);

        return [
            'version' => 1,
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspace->id,
            'actor_id' => $user->id,
            'objective' => $objective ? app(AiObjectiveLifecycle::class)->snapshot($objective) : null,
            'focus' => array_filter([
                'workflow_id' => $executionPlan['id'] ?? null,
                'objective_id' => $objective?->id,
                'operation_id' => $activeOperation,
                'active_candidate_set_id' => is_array($activeCandidateSet) ? ($activeCandidateSet['id'] ?? null) : null,
                'pending_confirmation_id' => $pendingConfirmation['confirmation_id'] ?? null,
                'last_actionable_result_id' => $state['last_actionable_result']['id'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'active_entity_refs' => $this->compactEntityRefs(
                is_array($state['active_entity_refs'] ?? null) ? $state['active_entity_refs'] : []
            ),
            'active_candidate_set' => $activeCandidateSet,
            'historical_candidate_sets' => $historicalCandidateSets,
            'historical_entity_refs' => $this->compactEntityRefs(
                is_array($state['historical_entity_refs'] ?? null) ? $state['historical_entity_refs'] : []
            ),
            'draft' => ($recipeDraftState['status'] ?? null) === 'needs_clarification'
                ? ($recipeDraftState['payload'] ?? null)
                : null,
            'pending_confirmation' => $pendingConfirmation,
            'execution_plan' => $executionPlan,
            'pending_clarification' => $pendingClarification,
            'last_operation' => $this->compactLastOperation($state['last_operation'] ?? null),
            'last_actionable_result' => $state['last_actionable_result'] ?? null,
        ];
    }

    private function beginToolLoopTurn(
        Conversation $conversation,
        Workspace $workspace,
    ): void {
        app(AiRunLifecycle::class)->reconcileConversation($conversation, (string) $workspace->id);
        $conversation->refresh();
    }

    private function prepareToolLoopPendingConfirmation(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        Message $message
    ): ?ActionConfirmation {
        $confirmations = ActionConfirmation::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_execution_plan_item', false)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id))
            ->with('message.conversation')
            ->latest('created_at')
            ->limit(2)
            ->get();

        if ($confirmations->count() !== 1) {
            return null;
        }

        $confirmation = $confirmations->first();
        if (! $confirmation instanceof ActionConfirmation
            || ($conversation->created_by !== $user->id
                && ! $conversation->participants()->where('user_id', $user->id)->exists())) {
            return null;
        }

        $this->conversationContinuationLifecycle
            ->acknowledgeUserMessageBeforeConfirmation($confirmation, $message);
        $conversation->refresh();

        return $confirmation;
    }

    /** @return array<string, mixed>|null */
    private function pendingConfirmationSnapshot(Conversation $conversation, Workspace $workspace): ?array
    {
        $confirmations = ActionConfirmation::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_execution_plan_item', false)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->id))
            ->latest('created_at')
            ->limit(2)
            ->get();

        if ($confirmations->isEmpty()) {
            return null;
        }

        if ($confirmations->count() > 1) {
            return [
                'status' => 'ambiguous',
                'count' => $confirmations->count(),
            ];
        }

        $confirmation = $confirmations->first();
        if (! $confirmation instanceof ActionConfirmation) {
            return null;
        }

        $draft = is_array($confirmation->draft_json) ? $confirmation->draft_json : [];
        $preview = is_array($draft['preview'] ?? null) ? $draft['preview'] : [];
        $summary = array_filter([
            'action' => $preview['action'] ?? null,
            'change_count' => is_array($preview['changes'] ?? null) ? count($preview['changes']) : null,
            'item_count' => $preview['item_count'] ?? null,
            'title' => $preview['title'] ?? null,
            'type' => $preview['type'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return array_filter([
            'action_key' => $confirmation->action_key,
            'confirmation_id' => $confirmation->id,
            'draft_id' => data_get($draft, 'draft_state.draft_id') ?? $preview['draft_id'] ?? null,
            'entity_type' => $preview['entity_type'] ?? null,
            'expires_at' => $confirmation->expires_at?->toIso8601String(),
            'revision' => data_get($draft, 'draft_state.revision') ?? $preview['revision'] ?? null,
            'status' => 'pending',
            'summary' => $summary,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /** @return array<string, mixed>|null */
    private function activeExecutionPlanSnapshot(Conversation $conversation, Workspace $workspace): ?array
    {
        $plan = AiExecutionPlan::query()
            ->where('workspace_id', $workspace->id)
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', ['pending_confirmation', 'queued', 'running', 'partial'])
            ->with('items')
            ->latest('created_at')
            ->first();
        if (! $plan) {
            return null;
        }

        return $this->toolExecutor->executionPlanSnapshot($plan);
    }

    /** @return array<string, mixed>|null */
    private function prepareToolLoopPendingClarification(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        Message $message
    ): ?array {
        $snapshot = $this->pendingClarificationSnapshot($conversation, $workspace, $user);
        if (! is_array($snapshot)
            || ($snapshot['status'] ?? null) !== 'pending'
            || ! filled($snapshot['clarification_id'] ?? null)
            || ! filled($snapshot['action_key'] ?? null)) {
            return null;
        }

        $acknowledged = $this->conversationContinuationLifecycle
            ->acknowledgeUserMessageBeforeClarification(
                $conversation,
                (string) $snapshot['clarification_id'],
                (string) $snapshot['action_key'],
                $message,
            );
        if ($acknowledged) {
            $conversation->refresh();
        }

        return $acknowledged ? $snapshot : null;
    }

    /** @return array<string, mixed>|null */
    private function pendingClarificationSnapshot(
        Conversation $conversation,
        Workspace $workspace,
        User $user
    ): ?array {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $clarifications = collect($metadata['pending_clarifications'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item)
                && ($item['status'] ?? null) === 'pending'
                && ($item['workspace_id'] ?? $workspace->id) === $workspace->id
                && ($item['conversation_id'] ?? $conversation->id) === $conversation->id
                && (empty($item['actor_id']) || $item['actor_id'] === $user->id))
            ->values();

        if ($clarifications->isEmpty()) {
            return null;
        }

        $clarification = $clarifications->last();
        if (! is_array($clarification)) {
            return null;
        }

        return [
            'action_key' => $clarification['action_key'] ?? $clarification['workflow'] ?? null,
            'clarification_id' => $clarification['clarification_id'] ?? $clarification['continuation_id'] ?? null,
            'constraints' => $clarification['constraints'] ?? [],
            'draft_id' => $clarification['draft_id'] ?? null,
            'draft_revision' => $clarification['draft_revision'] ?? null,
            'entity_type' => $clarification['entity_type'] ?? null,
            'expected_type' => $clarification['expected_type'] ?? null,
            'field_path' => $clarification['field_path'] ?? null,
            'ingredient' => $clarification['ingredient'] ?? '',
            'input_control' => $clarification['input_control'] ?? null,
            'message' => $clarification['message'] ?? null,
            'missing_fields' => $clarification['missing_fields'] ?? null,
            'options' => is_array($clarification['options'] ?? null) ? $clarification['options'] : [],
            'pending_count' => $clarifications->count(),
            'question' => $clarification['message'] ?? null,
            'reason' => $clarification['reason'] ?? null,
            'remaining_operations' => $clarification['remaining_operations'] ?? null,
            'selection_mode' => $clarification['selection_mode'] ?? null,
            'status' => 'pending',
        ];
    }

    /** @param array<int, array<string, mixed>> $entityRefs @param array<string, mixed> $result */
    private function persistOperationalContext(
        Conversation $conversation,
        Workspace $workspace,
        User $user,
        array $entityRefs,
        string $actionKey,
        array $result,
        ?string $resultId = null,
    ): void {
        $conversation->refresh();
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = is_array($metadata['ai_operational_context'] ?? null) ? $metadata['ai_operational_context'] : [];
        $confirmation = is_array($result['confirmation'] ?? null) ? $result['confirmation'] : null;
        $currentEntityRefs = $this->compactEntityRefs(
            is_array($result['entity_refs'] ?? null) ? $result['entity_refs'] : $entityRefs
        );
        [$candidateSets, $activeEntityRefs, $historicalEntityRefs] = $this->nextCandidateScope(
            $state,
            $actionKey,
            $result,
            $currentEntityRefs,
        );
        $status = $result['status']
            ?? $result['workflow_status']
            ?? ($confirmation !== null ? 'confirmation_required' : null);
        if (! in_array($status, ['failed', 'nonrecoverable_error'], true)) {
            $metadata['pending_clarifications'] = collect($metadata['pending_clarifications'] ?? [])
                ->map(function (mixed $item): mixed {
                    if (is_array($item)
                        && ($item['type'] ?? null) === 'orchestration.field_resolution'
                        && ($item['status'] ?? null) === 'pending') {
                        $item['status'] = 'resolved';
                        $item['resolved_at'] = now()->toIso8601String();
                    }

                    return $item;
                })
                ->values()
                ->all();
        }
        $metadata['ai_operational_context'] = [
            ...$state,
            'version' => 1,
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspace->id,
            'actor_id' => $user->id,
            'active_entity_refs' => $activeEntityRefs,
            'candidate_sets' => $candidateSets,
            'historical_entity_refs' => $historicalEntityRefs,
            'pending_confirmation' => $confirmation === null ? null : array_filter([
                'confirmation_id' => $confirmation['confirmation_id'] ?? $confirmation['id'] ?? null,
                'draft_id' => $confirmation['draft_id'] ?? null,
                'status' => $confirmation['status'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'last_operation' => [
                'action_key' => $actionKey,
                'status' => $status,
                'result_ref' => $this->compactResultReference($result['result_ref_json'] ?? null),
                'updated_at' => now()->toIso8601String(),
            ],
            'last_actionable_result' => array_filter([
                'action_key' => $actionKey,
                'id' => $resultId,
                'status' => $status,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'last_termination' => $state['last_termination'] ?? null,
        ];
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * Candidate identity and lifecycle are structural. The backend never
     * interprets an ordinal or chooses an item; it only scopes model-visible
     * records to the tool result that produced them.
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $result
     * @param  array<int, array<string, mixed>>  $currentEntityRefs
     * @return array{array<int, array<string, mixed>>, array<int, array<string, mixed>>, array<int, array<string, mixed>>}
     */
    private function nextCandidateScope(array $state, string $actionKey, array $result, array $currentEntityRefs): array
    {
        $sets = collect($state['candidate_sets'] ?? [])
            ->filter(fn (mixed $set): bool => is_array($set))
            ->map(fn (array $set): array => $set)
            ->values();
        $historicalRefs = $this->compactEntityRefs(
            is_array($state['historical_entity_refs'] ?? null) ? $state['historical_entity_refs'] : []
        );
        $items = data_get($result, 'result_ref_json.items');
        if (! is_array($items)) {
            $items = data_get($result, 'result_ref_json.candidates');
        }
        $items = is_array($items) ? array_values(array_filter($items, fn (mixed $item): bool => is_array($item))) : [];
        $isCandidateRead = count($items) > 1
            && (str_ends_with($actionKey, '.list') || str_ends_with($actionKey, '.search'));

        if ($isCandidateRead) {
            $sets = $sets->map(function (array $set): array {
                return ($set['status'] ?? null) === 'active'
                    ? [...$set, 'status' => 'historical']
                    : $set;
            });
            $sets->push([
                'id' => (string) Str::ulid(),
                'entity_type' => $this->toolRegistry->resolve($actionKey)['entity_type'] ?? null,
                'items' => collect($items)->take(20)->map(fn (array $item): array => array_filter([
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? data_get($item, 'user.name') ?? $item['title'] ?? null,
                    'version' => $item['current_version'] ?? $item['version'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all(),
                'source_action' => $actionKey,
                'status' => 'active',
            ]);
            $historicalRefs = $this->compactEntityRefs([
                ...$historicalRefs,
                ...(is_array($state['active_entity_refs'] ?? null) ? $state['active_entity_refs'] : []),
            ]);

            return [$sets->take(-6)->values()->all(), [], $historicalRefs];
        }

        if ($currentEntityRefs !== [] || is_array($result['confirmation'] ?? null)) {
            $resolvedId = $currentEntityRefs[0]['id'] ?? null;
            $sets = $sets->map(function (array $set) use ($resolvedId): array {
                if (($set['status'] ?? null) !== 'active') {
                    return $set;
                }

                return array_filter([
                    ...$set,
                    'resolved_entity_id' => $resolvedId,
                    'status' => $resolvedId !== null ? 'resolved' : 'historical',
                ], static fn (mixed $value): bool => $value !== null && $value !== '');
            });
            $historicalRefs = $this->compactEntityRefs([
                ...$historicalRefs,
                ...(is_array($state['active_entity_refs'] ?? null) ? $state['active_entity_refs'] : []),
            ]);
        }

        return [$sets->take(-6)->values()->all(), $currentEntityRefs, $historicalRefs];
    }

    /**
     * A confirmation may be reached after one or more read tools. Persist the
     * read presentation with the draft so the confirmation response can
     * render the same context after the write is executed.
     *
     * @param  array<string, mixed>  $result
     * @param  array<int, array<string, mixed>>  $supportingResults
     */
    private function persistConfirmationPresentationContext(
        Workspace $workspace,
        array $result,
        array $supportingResults
    ): void {
        $confirmationId = data_get($result, 'confirmation.confirmation_id')
            ?? data_get($result, 'confirmation.id');
        if (! filled($confirmationId) || $supportingResults === []) {
            return;
        }

        $confirmation = ActionConfirmation::query()
            ->where('workspace_id', $workspace->id)
            ->whereKey($confirmationId)
            ->first();
        if (! $confirmation) {
            return;
        }

        $presentationResults = collect($supportingResults)
            ->filter(fn (mixed $supportingResult): bool => is_array($supportingResult))
            ->map(fn (array $supportingResult): array => [
                'blocks' => (array) ($supportingResult['blocks'] ?? []),
                'entity_refs' => (array) ($supportingResult['entity_refs'] ?? []),
                'visible' => ($supportingResult['visible'] ?? true) !== false,
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
                    'entity_id' => $reference['entity_id'] ?? $reference['id'],
                    'entity_type' => $reference['entity_type'] ?? $type,
                    'name' => $reference['name'] ?? data_get($reference, 'snapshot.name'),
                    'title' => $reference['title'] ?? data_get($reference, 'snapshot.title'),
                    'current_version_id' => $reference['current_version_id'] ?? data_get($reference, 'snapshot.current_version_id'),
                    'originating_action' => $reference['originating_action'] ?? null,
                    'confirmation_id' => $reference['confirmation_id'] ?? null,
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
                    'component_recipe_id' => $ingredient['component_recipe_id'] ?? null,
                    'component_recipe_version_id' => $ingredient['component_recipe_version_id'] ?? null,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''))
                ->values()
                ->all();
        }

        return array_filter($compact, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }

    private function compactLastOperation(mixed $operation): ?array
    {
        if (! is_array($operation)) {
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
        if (! is_array($result)) {
            return $result;
        }

        if (isset($result['units'], $result['allergens'], $result['recipes'])) {
            return [
                'units' => collect($result['units'])->map(fn (array $item): array => array_intersect_key($item, array_flip(['id', 'key', 'name', 'symbol', 'dimension'])))->values()->all(),
                'allergens' => collect($result['allergens'])->map(fn (array $item): array => array_intersect_key($item, array_flip(['id', 'key', 'name'])))->values()->all(),
                'recipes' => collect($result['recipes'])->map(fn (array $item): array => array_intersect_key($item, array_flip(['id', 'name', 'current_version_id', 'revision', 'status'])))->values()->all(),
            ];
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
        $draftState = is_array($metadata['active_recipe_draft_state'] ?? null) ? $metadata['active_recipe_draft_state'] : [];
        $existing = ($draftState['status'] ?? null) === 'needs_clarification' && is_array($draftState['payload'] ?? null)
            ? $draftState['payload']
            : [];
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
        if (! $exception instanceof AiProviderValidationException) {
            return false;
        }

        $message = strtolower((string) ($exception->metadata()['provider_message'] ?? ''));

        return str_contains($message, 'no tool output found for function call');
    }

    private function isTransientProviderFailure(\Throwable $exception): bool
    {
        return $exception instanceof AiProviderException
            && in_array($exception->internalCode(), [
                'AI_INVALID_RESPONSE',
                'AI_RATE_LIMITED',
                'AI_TIMEOUT',
                'AI_PROVIDER_UNAVAILABLE',
                'AI_CONVERSATION_LOCKED',
            ], true);
    }

    private function deferUncertainProviderTurn(Conversation $conversation, \Throwable $exception, AiRun $run): bool
    {
        if (! $exception instanceof AiProviderException || ! in_array($exception->internalCode(), [
            'AI_TIMEOUT', 'AI_CONVERSATION_LOCKED', 'AI_PROTOCOL_STATE_CORRUPTED',
        ], true)) {
            return false;
        }
        if (! data_get($conversation->fresh()->metadata, 'provider_recovery.pending')) {
            ($this->openAIConversationService ?? app(OpenAIConversationService::class))->deferUncertainTurn($conversation, (string) $run->id);
        }

        return true;
    }

    private function providerRetryBackoff(\Throwable $exception, int $attempt, ?AiRun $aiRun = null): void
    {
        $base = max(0, (int) config('ai.retry_budgets.provider_transient_backoff_ms', 1500));
        $maximum = max($base, (int) config('ai.retry_budgets.provider_transient_max_backoff_ms', 5000));
        $exponential = min($maximum, $base * (2 ** max(0, $attempt - 1)));
        $retryAfter = $exception instanceof AiProviderException
            ? (int) ($exception->metadata()['retry_after_seconds'] ?? 0)
            : 0;
        $retryAfterMaximum = max(1, (int) config('ai.retry_budgets.provider_retry_after_max_seconds', 60));
        if ($retryAfter > $retryAfterMaximum) {
            if ($aiRun) {
                $aiRun->forceFill(['next_retry_at' => now()->addSeconds($retryAfter), 'last_heartbeat_at' => now()])->save();
            }
            throw $exception;
        }
        $jitter = $exponential > 0 ? random_int(0, max(1, (int) floor($exponential * 0.25))) : 0;
        $milliseconds = max($retryAfter * 1000, min($maximum, $exponential + $jitter));
        if ($aiRun?->deadline_at) {
            $remainingMs = now()->diffInMilliseconds($aiRun->deadline_at, false);
            $requestBudgetMs = max(5000, (int) config('ai.providers.openai.timeout_seconds', 30) * 1000);
            if ($remainingMs <= $milliseconds + $requestBudgetMs) {
                $aiRun->forceFill([
                    'next_retry_at' => now()->addMilliseconds(max(0, $milliseconds)),
                    'last_heartbeat_at' => now(),
                ])->save();
                throw $exception;
            }
        }
        if ($milliseconds <= 0) {
            return;
        }

        Log::info('ai.provider.transient_backoff', [
            'attempt' => $attempt,
            'delay_ms' => $milliseconds,
            'exception_class' => class_basename($exception),
            'retry_after_seconds' => $retryAfter ?: null,
        ]);
        usleep($milliseconds * 1000);
    }

    /**
     * Hosted Tool Search is provider-owned. Humoo records only its observable
     * query/result metadata and the registered functions made callable; it
     * never stores model reasoning.
     *
     * @param  array<string, mixed>  $providerResult
     * @param  array<string, string>  $definitionMap
     * @param  array<int, array<string, mixed>>  $metadata
     */
    private function logToolDiscoveryResult(
        array $providerResult,
        array $definitionMap,
        array $metadata,
        string $correlationId,
        string $workspaceId,
    ): void {
        $output = collect($providerResult['output'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item));
        $searchCalls = $output
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'tool_search_call')
            ->values();
        if ($searchCalls->isEmpty()) {
            return;
        }

        $deferredKeys = collect($metadata)
            ->filter(fn (array $tool): bool => (bool) ($tool['defer_loading'] ?? false))
            ->pluck('key')
            ->map(fn (mixed $key): string => (string) $key)
            ->all();
        $discoveredTools = $output
            ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'function_call')
            ->map(fn (array $item): ?string => $definitionMap[(string) ($item['name'] ?? '')] ?? null)
            ->filter(fn (?string $key): bool => $key !== null && in_array($key, $deferredKeys, true))
            ->unique()
            ->values()
            ->all();

        foreach ($searchCalls as $searchCall) {
            $arguments = $searchCall['arguments'] ?? [];
            if (is_string($arguments)) {
                $decoded = json_decode($arguments, true);
                $arguments = is_array($decoded) ? $decoded : [];
            }
            $query = is_array($arguments)
                ? ($arguments['query'] ?? $arguments['goal'] ?? $arguments['search'] ?? null)
                : null;
            Log::info('ai.tool_discovery.query', [
                'correlation_id' => $correlationId,
                'tool_discovery_query' => is_string($query) ? Str::limit($query, 500, '') : null,
                'workspace_id' => $workspaceId,
            ]);
        }

        Log::info('ai.tool_discovery.result', [
            'correlation_id' => $correlationId,
            'discovered_tools' => $discoveredTools,
            'latency_ms' => $providerResult['latency_ms'] ?? null,
            'result_count' => count($discoveredTools),
            'tool_search_count' => $searchCalls->count(),
            'workspace_id' => $workspaceId,
        ]);
    }

    /** @param array<string, mixed> $result */
    private function toolResultForModel(array $tool, array $result): array
    {
        $status = (string) ($result['status']
            ?? $result['workflow_status']
            ?? (is_array($result['confirmation'] ?? null) ? 'confirmation_required' : 'completed'));
        $ok = ($tool['key'] === 'objectives.cancel' && $status === 'cancelled')
            || ! in_array($status, ['failed', 'final_not_found', 'cancelled'], true);
        $workflowGuidance = $tool['key'] === 'tasks.search'
            ? 'If the user requested a write, this search is only preparatory: use the exact returned task IDs with the requested write tool before ending the turn.'
            : null;
        $modelResult = is_array($result['result_ref_json'] ?? null) ? $result['result_ref_json'] : [];
        if ($tool['key'] === 'objectives.define') {
            $modelResult = collect($modelResult)->only([
                'id', 'status', 'scope_defined', 'blockers', 'required_facts', 'resolved_facts', 'unplanned_results',
            ])->all();
            if (is_array($modelResult['unplanned_results'] ?? null)) {
                $modelResult['unplanned_results'] = collect($modelResult['unplanned_results'])
                    ->map(fn (mixed $item): mixed => is_array($item) ? ($item['result_key'] ?? $item) : $item)
                    ->values()
                    ->all();
            }
        }
        $safeDetails = [
            'action_key' => $tool['key'],
            'status' => $status,
            'result' => $modelResult,
            'entity_refs' => $this->compactEntityRefs((array) ($result['entity_refs'] ?? [])),
        ];
        foreach (['missing_fields', 'validation_errors', 'dependency', 'dependencies', 'clarification', 'confirmation'] as $key) {
            if (array_key_exists($key, $result) && $result[$key] !== null && $result[$key] !== []) {
                $safeDetails[$key] = $result[$key];
            }
        }
        if ($workflowGuidance !== null) {
            $safeDetails['workflow_guidance'] = $workflowGuidance;
        }

        $message = match ($status) {
            'clarification_required' => 'The tool needs one missing user value before it can continue.',
            'confirmation_required' => 'The tool produced a confirmation request. Wait for explicit user confirmation before continuing the write.',
            'partial' => 'The workflow remains partially unresolved. No corrected confirmation was created. Do not say that a preview is ready or that work is queued; use the safe details to explain the remaining blocker or ask the user only for the missing value.',
            'final_not_found' => 'The requested entity was not found in the authorized workspace.',
            'failed' => 'The tool rejected the request. Inspect safe validation details and repair or clarify it.',
            'cancelled' => 'The active pending objective was cancelled. Tell the user directly; do not claim that completed writes were reversed.',
            default => 'Tool completed. Continue the user request if more capabilities are required.',
        };
        $allowedNextActions = is_array($result['allowed_next_actions'] ?? null)
            ? array_values($result['allowed_next_actions'])
            : match ($status) {
            'clarification_required' => ['ask_user_for_clarification', 'resolve_dependency'],
            'confirmation_required' => ['request_user_confirmation'],
            'partial' => ['correct_arguments', 'ask_user_for_clarification'],
            'final_not_found' => [$this->searchActionForTool($tool), 'ask_user_for_clarification'],
            'failed' => ['correct_arguments', 'resolve_dependency', 'ask_user_for_clarification'],
            'cancelled' => ['respond_to_user'],
            default => ['continue_with_tool', 'respond_to_user'],
            };

        return ToolObservation::make(
            $ok,
            $ok ? null : (string) ($result['error_code'] ?? ($status === 'final_not_found' ? 'ENTITY_NOT_FOUND' : 'TOOL_FAILED')),
            $message,
            $safeDetails,
            [
                'requires_confirmation' => $status === 'confirmation_required',
                'ambiguous' => $status === 'clarification_required',
                'partial' => $status === 'partial',
                'conflict' => $status === 'conflict',
                'has_more' => (bool) (data_get($result, 'pagination.has_more')
                    ?? data_get($result, 'result_ref_json.has_more')
                    ?? false),
                'not_found' => $status === 'final_not_found',
                'validation_failed' => $status === 'failed',
                'recoverable' => ! in_array($status, ['final_not_found', 'cancelled', 'confirmation_required', 'clarification_required'], true),
            ],
            $allowedNextActions,
        );
    }

    /** @param array<string, mixed> $lastResult */
    private function objectiveCompletionFeedback(AiRun $run, Conversation $conversation): ?string
    {
        $objective = $run->objective_id ? AiObjective::query()->where('workspace_id', $run->workspace_id)
            ->where('conversation_id', $conversation->id)->find($run->objective_id) : null;
        if (! $objective || ! data_get($objective->metadata_json, 'scope_defined')
            || in_array($objective->status, ['cancelled', 'failed'], true)
            || ($objective->blockers_json ?? []) !== []
            || ActionConfirmation::query()->where('workspace_id', $run->workspace_id)
                ->where('objective_id', $objective->id)->where('status', 'pending')->where('is_execution_plan_item', false)->exists()) {
            return null;
        }
        $verification = app(\App\AI\Objectives\ObjectiveValidator::class)->validate($objective);
        if ($verification['valid']) {
            return null;
        }

        return 'The backend has not verified the complete objective. Continue the remaining work through registered tools; do not repeat completed writes or claim success. Canonical state: '
            .json_encode(['objective' => app(AiObjectiveLifecycle::class)->snapshot($objective), 'verification' => $verification], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

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
            if (! is_array($right[$group] ?? null)) {
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
        $objective = $aiRun->objective_id ? AiObjective::query()->where('workspace_id', $workspace->id)
            ->where('conversation_id', $conversation->id)->find($aiRun->objective_id) : null;
        $actor = $aiRun->actor;
        $pendingClarification = $actor instanceof User
            ? $this->pendingClarificationSnapshot($conversation->fresh(), $workspace, $actor)
            : null;
        $hasObjectiveBlockers = $objective && (array) ($objective->blockers_json ?? []) !== [];
        if (($result['workflow_status'] ?? 'completed') === 'completed'
            && ($pendingClarification !== null || $hasObjectiveBlockers)) {
            $result['workflow_status'] = 'clarification_required';
        }
        if ($objective && ! in_array($objective->status, ['cancelled', 'failed'], true)
            && ($objective->operation_count > 0 || data_get($objective->metadata_json, 'scope_defined'))
            && ($result['workflow_status'] ?? 'completed') === 'completed') {
            $verification = app(\App\AI\Objectives\ObjectiveValidator::class)->finalize($objective);
            if (! $verification['valid']) {
                $result['workflow_status'] = $verification['canonical_status'] === 'blocked' ? 'clarification_required' : $verification['canonical_status'];
                if ($verification['canonical_status'] !== 'blocked') {
                    $result['blocks'] = array_values(array_filter($result['blocks'] ?? [], fn (array $block): bool => ($block['type'] ?? '') !== 'text'));
                    array_unshift($result['blocks'], ['type' => 'text', 'text' => trans('chat.recovery.objective_incomplete', [], $locale)]);
                }
            }
        }
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
        $this->chatStreamPublisher()->completed($conversation, $assistantMessage);
        $this->completeAiRunSafely($aiRun, [
            'completed_at' => now(),
            'metadata' => [
                ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                'orchestration_version' => 'tool-loop-v1',
                'selected_action_keys' => $toolKeys,
                'interaction_mode' => 'tool_loop',
                'safe_reason_code' => $result['workflow_status'] ?? null,
                'termination_reason' => $result['workflow_status'] ?? 'model_final_response',
                'tool_count' => count($toolKeys),
                'tool_profile' => $providerMetadata['tool_profile'] ?? null,
                'cached_input_tokens' => $providerMetadata['cached_input_tokens'] ?? null,
                'efficiency' => $providerMetadata['efficiency'] ?? [],
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
            'termination_reason' => $result['workflow_status'] ?? 'model_final_response',
            'tool_count' => count($toolKeys),
            'tool_keys' => $toolKeys,
            'workspace_id' => $workspace->id,
            ...$this->toolLoopTraceContext((array) data_get($conversation->fresh()->metadata, 'ai_operational_context', [])),
        ]);
        Log::info('ai.tool_loop.efficiency', [
            'cached_input_tokens' => $providerMetadata['cached_input_tokens'] ?? null,
            'correlation_id' => $correlationId,
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'tool_count' => count($toolKeys),
            'tool_profile' => $providerMetadata['tool_profile'] ?? null,
            'workspace_id' => $workspace->id,
            ...(array) ($providerMetadata['efficiency'] ?? []),
        ]);
    }

    private function completeAiRunSafely(AiRun $aiRun, array $attributes, string $correlationId): void
    {
        try {
            $workflowStatus = (string) data_get($attributes, 'metadata.termination_reason', '');
            $runtimeStatus = match ($workflowStatus) {
                'confirmation_required', 'waiting_confirmation' => 'waiting_confirmation',
                'clarification_required', 'waiting_user' => 'waiting_user',
                'retrying' => 'retrying',
                'paused' => 'paused',
                'partial', 'needs_review' => 'needs_review',
                'cancelled' => 'cancelled',
                'failed', 'nonrecoverable_error', 'provider_error' => 'failed',
                default => (string) ($attributes['status'] ?? 'completed'),
            };
            $stage = match ($runtimeStatus) {
                'waiting_confirmation' => 'waiting_confirmation',
                'waiting_user' => 'waiting_user',
                'retrying' => 'retrying',
                'paused' => 'paused',
                'needs_review' => 'needs_review',
                'failed' => 'failed',
                'cancelled' => 'cancelled',
                default => 'completed',
            };
            unset($attributes['status'], $attributes['completed_at']);
            app(AiRunLifecycle::class)->transition(
                $aiRun,
                $runtimeStatus,
                $stage,
                attributes: $attributes,
            );
        } catch (\Throwable $exception) {
            Log::warning('ai.run.persistence_failed', [
                'ai_run_id' => $aiRun->id,
                'correlation_id' => $correlationId,
                'exception_class' => class_basename($exception),
                'workspace_id' => $aiRun->workspace_id,
            ]);
        }
    }

    private function latencyMilliseconds(mixed $startedAt): ?int
    {
        if (! $startedAt) {
            return null;
        }

        return max(0, (int) $startedAt->diffInMilliseconds(now()));
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
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $operationalState = is_array($metadata['ai_operational_context'] ?? null)
            ? $metadata['ai_operational_context']
            : [];
        $operationalEntityRefs = is_array($operationalState['active_entity_refs'] ?? null)
            ? $operationalState['active_entity_refs']
            : [];
        $recentEntityRefs = collect([...$operationalEntityRefs, ...$persistedEntityRefs, ...$messageEntityRefs])
            ->unique(fn (array $reference): string => implode(':', [
                $reference['type'] ?? '',
                $reference['id'] ?? '',
                $reference['role'] ?? 'recent',
            ]))
            ->values()
            ->all();

        $activeEntities = collect($recentEntityRefs)
            ->filter(fn (array $reference): bool => ($reference['role'] ?? null) === 'active')
            ->keyBy(fn (array $reference): string => (string) ($reference['type'] ?? ''))
            ->all();
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
            activeEntities: $activeEntities,
            lastInteraction: $lastInteraction ? [
                'message_id' => $lastInteraction->id,
                ...(is_array($lastInteraction->metadata['orchestration'] ?? null) ? $lastInteraction->metadata['orchestration'] : []),
            ] : null,
            correlationId: $correlationId,
        );
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
                    'objective_id' => $context['objective_id'] ?? $aiRun->objective_id,
                    'provider_call_id' => $context['provider_call_id'] ?? null,
                    'pending_clarification_id' => $context['pending_clarification_id'] ?? null,
                    'pending_confirmation_revision_id' => $context['pending_confirmation_revision_id'] ?? null,
                    'source_message' => $assistantMessage,
                    'entity_refs' => $context['entity_refs'] ?? [],
                    'correlation_id' => $context['correlation_id'] ?? null,
                    'routing' => $context['routing'] ?? null,
                    // ToolExecutionContext intentionally carries only the
                    // trusted chat scope. Preserve this runtime marker so the
                    // executor can enforce the canonical tool-loop boundary.
                    'tool_loop' => (bool) ($context['tool_loop'] ?? false),
                ]),
                [
                    'action_id' => $actionId,
                    'entity' => $entity,
                    'idempotency_key' => $toolCall->idempotency_key,
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

    private function logToolDatabaseFailure(
        \Throwable $exception,
        string $actionKey,
        string $callId,
        string $correlationId,
        string $workspaceId,
    ): void {
        if (! $exception instanceof QueryException) {
            return;
        }

        Log::warning('ai.tool_call.database_failure', [
            'action_key' => $actionKey,
            'call_id' => $callId,
            'correlation_id' => $correlationId,
            'driver_code' => $exception->errorInfo[1] ?? null,
            // Keep SQL and bindings out of chat/UI. The driver message is
            // retained only in secure application logs to identify the exact
            // constrained column on a persistence failure.
            'driver_message' => $exception->errorInfo[2] ?? $exception->getMessage(),
            'query' => $exception->getSql(),
            'sql_state' => $exception->errorInfo[0] ?? $exception->getCode(),
            'workspace_id' => $workspaceId,
        ]);
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
                        'retry_after_seconds' => $publicError['retry_after_seconds'] ?? null,
                        'objective_id' => $publicError['objective_id'] ?? null,
                        'ai_run_id' => $publicError['ai_run_id'] ?? null,
                        'preserved_progress' => (bool) ($publicError['preserved_progress'] ?? false),
                        'completed_count' => (int) ($publicError['completed_count'] ?? 0),
                        'pending_count' => (int) ($publicError['pending_count'] ?? 0),
                        'next_actions' => array_values((array) ($publicError['next_actions'] ?? [])),
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
            'conversation_id' => $assistantMessage->conversation_id,
            'actor_id' => $userMessage->sender_id,
            'message_id' => $assistantMessage->id,
            'input_message_id' => $userMessage->id,
            'provider' => 'openai',
            'model_key' => (string) config('ai.providers.openai.model', 'openai'),
            'status' => 'running',
            'current_stage' => 'analyzing',
            'queued_at' => now(),
            'sequence' => 1,
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
        $idempotencyKey = "{$aiRun->id}:tool:{$position}";
        app(AiRunLifecycle::class)->progressByAssistantMessage(
            (string) $aiRun->message_id,
            'executing_tool',
            $position,
            null,
            ['tool_key' => $toolKey],
        );
        $aiRun->forceFill(['tool_loop_iteration' => max((int) $aiRun->tool_loop_iteration, $position)])->save();

        return AiToolCall::query()->firstOrCreate([
            'ai_run_id' => $aiRun->id,
            'idempotency_key' => $idempotencyKey,
        ], [
            'workspace_id' => $workspaceId,
            'tool_key' => $toolKey,
            'position' => $position,
            'arguments_json' => $arguments,
            'idempotency_key' => $idempotencyKey,
            'requires_confirmation' => (bool) ($this->toolRegistry->resolve($toolKey)['requires_confirmation'] ?? false),
            'started_at' => now(),
            'status' => 'running',
        ]);
    }

    private function errorCodeFor(\Throwable $exception): string
    {
        if ($exception instanceof AiRuntimeException) {
            return $exception->internalCode();
        }
        if ($exception instanceof AiProviderException) {
            return $exception->internalCode();
        }

        $code = is_scalar($exception->getCode()) ? (string) $exception->getCode() : '';

        return $code !== '' && $code !== '0' ? $code : class_basename($exception);
    }

    private function safeErrorDetail(string $locale, \Throwable $exception): string
    {
        if ($exception instanceof AiRuntimeException) {
            return $this->t($locale, 'recovery.'.$exception->publicMessageKey());
        }
        if (! $exception instanceof AiProviderException) {
            return $exception->getMessage();
        }

        if ($exception instanceof AiProviderValidationException) {
            return $this->t($locale, 'recovery.provider_validation');
        }

        return match ($exception->internalCode()) {
            'AI_AUTH_ERROR', 'AI_AUTHENTICATION_FAILED' => $this->t($locale, 'recovery.provider_authentication'),
            'AI_AUTHORIZATION_FAILED' => $this->t($locale, 'recovery.provider_authorization'),
            'AI_BAD_REQUEST' => $this->t($locale, 'recovery.provider_bad_request'),
            'AI_INVALID_RESPONSE' => $this->t($locale, 'recovery.provider_invalid_response'),
            'AI_NETWORK_ERROR' => $this->t($locale, 'recovery.provider_network_error'),
            'AI_CONVERSATION_LOCKED' => $this->t($locale, 'recovery.provider_unavailable'),
            'AI_QUOTA_EXHAUSTED' => $this->t($locale, 'recovery.provider_quota'),
            'AI_PROTOCOL_STATE_CORRUPTED' => $this->t($locale, 'recovery.provider_protocol_state'),
            'AI_RATE_LIMITED' => $this->t($locale, 'recovery.provider_rate_limit'),
            'AI_TIMEOUT' => $this->t($locale, 'recovery.provider_timeout'),
            default => $this->t($locale, 'recovery.provider_unavailable'),
        };
    }

    private function t(string $locale, string $key): string
    {
        return (string) trans("chat.{$key}", [], $locale);
    }
}
