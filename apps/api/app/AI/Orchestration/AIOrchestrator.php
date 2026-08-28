<?php

namespace App\AI\Orchestration;

use App\AI\Advisory\AdvisoryOrchestrator;
use App\AI\Advisory\RecipeDraftPayloadMapper;
use App\AI\Capabilities\CapabilityCall;
use App\AI\Capabilities\CapabilityFunctionRouter;
use App\AI\Capabilities\Drafts\RecipeCreateDraftData;
use App\AI\Clarifications\PendingClarificationResolver;
use App\AI\Errors\ErrorResponseMapper;
use App\AI\Exceptions\AiProviderException;
use App\AI\Intent\HybridIntentRouter;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Intent\RoutingDecisionValidator;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolExecutionContext;
use App\AI\Tools\ToolRegistry;
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
        private PendingClarificationResolver $pendingClarificationResolver,
        private RoutingDecisionValidator $routingDecisionValidator,
        private MessageLocaleResolver $messageLocaleResolver,
        private CapabilityFunctionRouter $capabilityFunctionRouter,
    ) {
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
        $timezone = $this->resolveTimezone($workspace, $membership, $user);
        $correlationId = OrchestrationContext::correlationId();

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
            if ($continuation->status === 'resolved') {
                if ($continuation->source === 'clarification') {
                    $resolved = $this->pendingClarificationResolver->resolve(
                        $conversation,
                        $workspace->id,
                        $continuation->continuationId ?? '',
                        (array) ($continuation->data['input'] ?? []),
                        $user->id
                    );
                    $decision = $this->continuationActionDecision(
                        $continuation->actionKey
                            ?? ($resolved['clarification']['action_key'] ?? null)
                            ?? ($resolved['clarification']['workflow'] ?? ''),
                        ['recipe_draft' => $resolved['draft']],
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

        if ($title === '') {
            return $this->recoveryResult(
                $context['locale'],
                'task_create_missing_title',
                $this->t($context['locale'], 'recovery.task_create_missing_title')
            );
        }

        $input = array_filter([
            'description' => trim((string) ($slots['task_description'] ?? '')) ?: null,
            'due_at' => $slots['due_at'] ?? null,
            'priority' => $slots['task_priority'] ?? 'normal',
            'starts_at' => $slots['starts_at'] ?? null,
            'status' => 'todo',
            'title' => $title,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

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
            'recipe_id', 'recipe_search', 'recipe_version_id', 'menu_id', 'menu_search', 'menu_item_id', 'menu_item_search', 'task_id', 'task_search',
            'target_section_id', 'target_section_search', 'prep_list_id', 'prep_list_search', 'prep_item_id', 'prep_item_search',
            'event_id', 'event_search', 'guest_count', 'menu_version_id', 'due_at', 'include_assignments',
            'preserve_completed_items', 'preserve_assignments', 'assignment_membership_id', 'assignee_search', 'version',
            'name', 'description', 'title', 'quantity', 'unit_id', 'portions', 'yield_quantity', 'yield_unit_id',
            'actual_quantity', 'actual_unit_id', 'starts_at', 'status', 'blocked_reason', 'notes',
            'team_id', 'team_search', 'station_id', 'station_search', 'shift_id', 'shift_search',
            'membership_id', 'member_search', 'from', 'to', 'timezone', 'break_minutes', 'role',
            'member_ids', 'lead_membership_id', 'records', 'rules', 'membership_id', 'member_search', 'role_id', 'email', 'default_locale', 'currency', 'event_key', 'enabled', 'in_app', 'minimum_priority',
        ] as $slot) {
            if (array_key_exists($slot, $slots) && $slots[$slot] !== null) {
                $input[$slot] = $slots[$slot];
            }
        }
        if ($actionKey === 'tasks.create' && !filled($input['title'] ?? null)) {
            return [
                'blocks' => [[
                    'text' => $this->t($context['locale'], 'recovery.task_create_missing_title'),
                    'type' => 'text',
                ]],
                'entity_refs' => [],
                'suggestions' => [],
                'tool_keys' => ['tasks.create'],
                'workflow_status' => 'clarification_required',
            ];
        }
        $recipeDraft = is_array($input['recipe_draft'] ?? null) ? $input['recipe_draft'] : null;
        if ($actionKey === 'recipes.update' && !is_array($recipeDraft['version'] ?? null)) {
            $input['raw_recipe_update'] = (string) ($context['user_message']->content_text ?? '');
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
            $this->recordPatternFailureSafely((string) $context['workspace']->id, ['routing' => $context['routing'] ?? []], $context);
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

    private function resolveTimezone(Workspace $workspace, WorkspaceMembership $membership, User $user): string
    {
        $timezone = (string) ($workspace->timezone ?? $membership->timezone ?? $user->timezone ?? 'UTC');

        return $timezone !== '' ? $timezone : 'UTC';
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
