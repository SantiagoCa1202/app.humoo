<?php

namespace App\AI\Orchestration;

use App\AI\Contracts\AIProvider;
use App\AI\Exceptions\AiProviderException;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\Application\Actions\Chat\AssistantMessageWriter;
use App\Application\Actions\Chat\RecordUnsupportedCapability;
use App\Models\AiRun;
use App\Models\AiToolCall;
use App\Models\CapabilityRequest;
use App\Models\Conversation;
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
        private AIProvider $provider,
        private HumooSystemInstructions $systemInstructions,
        private AssistantMessageWriter $assistantMessageWriter,
        private RecordUnsupportedCapability $recordUnsupportedCapability,
        private ToolExecutor $toolExecutor,
        private ToolRegistry $toolRegistry
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
        $locale = $this->resolveLocale($payload['locale'] ?? null, $workspace, $user);
        $timezone = $this->resolveTimezone($workspace, $membership, $user);
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
            $timezone
        );

        try {
            $context = $this->buildContext(
                $conversation,
                $workspace,
                $membership,
                $user,
                $userMessage,
                $assistantMessage,
                $locale,
                $timezone
            );
            $decision = $this->provider->generate([
                'available_tools' => $context['available_tools'],
                'locale' => $locale,
                'message' => $userMessage->content_text ?? '',
                'message_id' => $userMessage->id,
                'recent_entity_refs' => $context['recent_entity_refs'],
                'recent_messages' => $context['recent_messages'],
                'system_instructions' => $this->systemInstructions->toText(),
                'timezone' => $timezone,
            ]);
            $result = $this->executeDecision(
                $decision,
                $context,
                $assistantMessage,
                $aiRun,
                0
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
                        'capability_request' => $result['capability_request'] ?? null,
                        'intent' => $decision['intent'] ?? null,
                        'provider' => $decision['provider'] ?? 'rule_based',
                        'tool_calls' => $result['tool_keys'] ?? [],
                    ],
                    'source' => 'assistant-response',
                ]
            );

            $aiRun->forceFill([
                'completed_at' => now(),
                'metadata' => [
                    ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                    'capability_request' => $result['capability_request'] ?? null,
                    'intent' => $decision['intent'] ?? null,
                ],
                'model_key' => (string) ($decision['model'] ?? $aiRun->model_key),
                'provider' => (string) ($decision['provider'] ?? $aiRun->provider),
                'status' => 'completed',
                'usage_json' => $decision['usage'] ?? null,
            ])->save();

            return $assistantMessage;
        } catch (\Throwable $exception) {
            $errorPayload = $this->errorPayload($locale, $exception);
            $errorCode = $this->errorCodeFor($exception);

            Log::warning('ai.orchestrator.failed', [
                ...$this->providerDiagnosticMetadata($exception),
                'exception_class' => class_basename($exception),
                'internal_code' => $errorCode,
            ]);

            $assistantMessage = $this->assistantMessageWriter->fail(
                $assistantMessage,
                $workspace,
                $errorCode,
                $exception->getMessage(),
                $errorPayload,
                $locale,
                [
                    'source' => 'assistant-response',
                ]
            );

            $aiRun->forceFill([
                'completed_at' => now(),
                'error_code' => $errorCode,
                'error_message' => $exception->getMessage(),
                'metadata' => [
                    ...(is_array($aiRun->metadata) ? $aiRun->metadata : []),
                    'diagnostic' => $this->providerDiagnosticMetadata($exception),
                    'exception' => class_basename($exception),
                ],
                'status' => 'failed',
            ])->save();

            return $assistantMessage;
        }
    }

    private function buildContext(
        Conversation $conversation,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
        Message $userMessage,
        Message $assistantMessage,
        string $locale,
        string $timezone
    ): array {
        $recentMessages = $conversation->messages()
            ->where('id', '!=', $assistantMessage->id)
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->reverse()
            ->values();
        $recentEntityRefs = $recentMessages
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

        return [
            'assistant_message' => $assistantMessage,
            'available_tools' => $this->toolRegistry->allMetadata(),
            'conversation' => $conversation,
            'locale' => $locale,
            'membership' => $membership,
            'recent_entity_refs' => $recentEntityRefs,
            'recent_messages' => $recentMessages->map(fn (Message $message) => [
                'content_text' => $message->content_text,
                'id' => $message->id,
                'sender_type' => $message->sender_type,
            ])->all(),
            'timezone' => $timezone,
            'user' => $user,
            'user_message' => $userMessage,
            'workspace' => $workspace,
        ];
    }

    private function executeDecision(
        array $decision,
        array $context,
        Message $assistantMessage,
        AiRun $aiRun,
        int $toolCount
    ): array {
        $maxToolCalls = max(1, (int) config('ai.max_tool_calls_per_turn', 4));

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
            'show_events' => $this->showEvents($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
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
            'create_menu' => $this->previewMenuCreate($context, $assistantMessage, $aiRun, $toolCount, $decision['slots'] ?? []),
            default => $this->clarifyScope($context['locale']),
        };
    }

    private function recordUnsupportedCapability(array $context, array $decision): array
    {
        $slots = is_array($decision['slots'] ?? null) ? $decision['slots'] : [];
        $requestedAction = trim((string) ($slots['requested_action'] ?? ''));
        $detectedIntent = trim((string) ($slots['detected_intent'] ?? ''));

        if ($requestedAction === '' || $detectedIntent === '') {
            return $this->clarifyScope($context['locale']);
        }

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
            $result = $this->toolExecutor->request(
                [
                    'ai_tool_call_id' => $toolCall->id,
                    'locale' => $context['locale'],
                    'membership' => $context['membership'],
                    'source_message' => $assistantMessage,
                    'user' => $context['user'],
                    'workspace' => $context['workspace'],
                ],
                [
                    'action_id' => $actionId,
                    'entity' => $entity,
                    'idempotency_key' => null,
                    'input' => $input,
                ]
            );

            $toolCall->forceFill([
                'completed_at' => now(),
                'result_ref_json' => $result['result_ref_json'] ?? null,
                'status' => 'completed',
            ])->save();

            return $result;
        } catch (\Throwable $exception) {
            $toolCall->forceFill([
                'completed_at' => now(),
                'error_code' => $this->errorCodeFor($exception),
                'error_message' => $exception->getMessage(),
                'status' => 'failed',
            ])->save();

            throw $exception;
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
        return [
            'blocks' => [
                [
                    'text' => $this->t($locale, 'clarification.scope_text'),
                    'type' => 'text',
                ],
                [
                    'component' => 'clarification.options',
                    'data' => [
                        'description' => $this->t($locale, 'clarification.scope_description'),
                        'options' => [
                            [
                                'id' => 'events',
                                'label' => $this->t($locale, 'clarification.scope_events'),
                                'value' => $locale === 'es' ? 'Muestrame los eventos de manana' : 'Show me tomorrow events',
                            ],
                            [
                                'id' => 'prep',
                                'label' => $this->t($locale, 'clarification.scope_prep'),
                                'value' => $locale === 'es' ? 'Muestrame el prep activo' : 'Show me active prep',
                            ],
                            [
                                'id' => 'tasks',
                                'label' => $this->t($locale, 'clarification.scope_tasks'),
                                'value' => $locale === 'es' ? 'Muestrame mis tareas abiertas' : 'Show my open tasks',
                            ],
                        ],
                        'selection_mode' => 'immediate',
                        'title' => $this->t($locale, 'clarification.scope_title'),
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

    private function errorPayload(string $locale, \Throwable $exception): array
    {
        return [
            'blocks' => [
                [
                    'component' => 'error.recovery',
                    'data' => [
                        'description' => $this->t($locale, 'recovery.description'),
                        'error_code' => $this->errorCodeFor($exception),
                        'safe_detail' => $this->safeErrorDetail($locale, $exception),
                        'title' => $this->t($locale, 'recovery.title'),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'suggestions' => $this->defaultSuggestions($locale),
        ];
    }

    private function defaultSuggestions(string $locale): array
    {
        return $locale === 'es'
            ? [
                'Muestrame los eventos de manana',
                'Muestrame el prep activo',
                'Muestrame mis tareas abiertas',
            ]
            : [
                'Show me tomorrow events',
                'Show me active prep',
                'Show my open tasks',
            ];
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

    private function startRun(
        Message $assistantMessage,
        Message $userMessage,
        Workspace $workspace,
        string $locale,
        string $timezone
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
                'available_tools' => $this->toolRegistry->allMetadata(),
                'locale' => $locale,
                'system_instructions' => $this->systemInstructions->toArray(),
                'timezone' => $timezone,
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

    private function resolveLocale(?string $requestedLocale, Workspace $workspace, User $user): string
    {
        $locale = Str::lower(substr(
            (string) ($requestedLocale ?: $workspace->locale ?? $user->locale ?? config('app.locale', 'en')),
            0,
            2
        ));

        return in_array($locale, ['en', 'es'], true) ? $locale : 'en';
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
