<?php

namespace App\AI\Tools;

use App\Application\Actions\ChatTools\ListEventsForTool;
use App\Application\Actions\ChatTools\ListMyTasksForTool;
use App\Application\Actions\ChatTools\ListPrepListsForTool;
use App\Application\Actions\Prep\UpdatePrepItem;
use App\Application\Actions\Tasks\UpdateTask;
use App\Http\Resources\PrepItemResource;
use App\Http\Resources\TaskResource;
use App\Models\ActionConfirmation;
use App\Models\Message;
use App\Models\MessageBlock;
use App\Models\PrepItem;
use App\Models\Task;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToolExecutor
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private ListEventsForTool $listEventsForTool,
        private ListPrepListsForTool $listPrepListsForTool,
        private ListMyTasksForTool $listMyTasksForTool,
        private UpdatePrepItem $updatePrepItem,
        private UpdateTask $updateTask
    ) {
    }

    public function request(array $context, array $payload): array
    {
        $tool = $this->toolRegistry->resolve(
            (string) ($payload['action_id'] ?? '')
        );

        return $tool['mode'] === 'read'
            ? $this->executeReadTool($tool, $context, $payload)
            : $this->previewWriteTool($tool, $context, $payload);
    }

    public function confirm(
        ActionConfirmation $confirmation,
        array $context
    ): array {
        $draft = is_array($confirmation->draft_json)
            ? $confirmation->draft_json
            : [];
        $tool = $this->toolRegistry->resolve(
            (string) ($draft['tool_key'] ?? '')
        );

        return match ($tool['key']) {
            'prep_items.update' => $this->executePrepItemUpdate($tool, $context, $draft),
            'tasks.update' => $this->executeTaskUpdate($tool, $context, $draft),
            default => throw ValidationException::withMessages([
                'confirmation' => ['The confirmation tool is not executable.'],
            ]),
        };
    }

    private function executeReadTool(
        array $tool,
        array $context,
        array $payload
    ): array {
        $workspaceId = $context['workspace']->id;
        $membershipId = $context['membership']->id;
        $filters = is_array($payload['input'] ?? null)
            ? $payload['input']
            : [];

        $result = match ($tool['key']) {
            'events.list' => $this->listEventsForTool->execute($workspaceId, $filters),
            'prep.list' => $this->listPrepListsForTool->execute($workspaceId, $filters),
            'tasks.mine' => $this->listMyTasksForTool->execute($workspaceId, $membershipId, $filters),
            default => throw ValidationException::withMessages([
                'action_id' => ['The selected action is not a readable tool.'],
            ]),
        };

        return [
            'blocks' => [
                [
                    'text' => $this->readSummaryText($tool['key'], (int) ($result['count'] ?? 0)),
                    'type' => 'text',
                ],
                [
                    'component' => $tool['component'],
                    'data' => $this->readComponentPayload($tool['key'], $result),
                    'schema_version' => $tool['schema_version'],
                    'type' => 'component',
                ],
            ],
            'result_ref_json' => [
                'count' => $result['count'] ?? 0,
                'items' => $result['items'] ?? [],
            ],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function previewWriteTool(
        array $tool,
        array $context,
        array $payload
    ): array {
        $source = $this->resolveConfirmationSource($context);

        return match ($tool['key']) {
            'prep_items.update' => $this->previewPrepItemUpdate($tool, $context, $payload, $source),
            'tasks.update' => $this->previewTaskUpdate($tool, $context, $payload, $source),
            default => throw ValidationException::withMessages([
                'action_id' => ['The selected action is not a writable tool.'],
            ]),
        };
    }

    private function previewTaskUpdate(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $workspaceId = $context['workspace']->id;
        $entity = $this->validateEntityPayload(
            is_array($payload['entity'] ?? null) ? $payload['entity'] : [],
            'task'
        );
        $input = $this->validateTaskInput(
            is_array($payload['input'] ?? null) ? $payload['input'] : [],
            $workspaceId
        );
        $task = $this->loadTaskForTool($workspaceId, $entity['id']);

        Gate::forUser($context['user'])->authorize('update', $task);

        $changes = $this->buildTaskChanges($task, $input, $workspaceId);
        $this->assertHasChanges($changes);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $task->title,
                'changes' => $changes,
                'description' => 'Revisa la actualización propuesta para la tarea antes de ejecutarla.',
                'metadata' => [
                    [
                        'label' => 'Tarea',
                        'value' => $task->title,
                    ],
                ],
                'title' => 'Actualización propuesta de tarea',
                'type' => 'Task update',
            ],
            [
                [
                    'label' => 'Tarea',
                    'value' => $task->title,
                ],
                [
                    'label' => 'Acción',
                    'value' => 'Actualizar tarea',
                ],
            ],
            [
                'entity' => $entity,
                'input' => $input,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function previewPrepItemUpdate(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $workspaceId = $context['workspace']->id;
        $entity = $this->validateEntityPayload(
            is_array($payload['entity'] ?? null) ? $payload['entity'] : [],
            'prep_item'
        );
        $input = $this->validatePrepItemInput(
            is_array($payload['input'] ?? null) ? $payload['input'] : [],
            $workspaceId
        );
        $prepItem = $this->loadPrepItemForTool($workspaceId, $entity['id']);

        Gate::forUser($context['user'])->authorize('update', $prepItem);

        $changes = $this->buildPrepItemChanges($prepItem, $input, $workspaceId);
        $this->assertHasChanges($changes);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $prepItem->title,
                'changes' => $changes,
                'description' => 'Confirma los cambios propuestos para este ítem de producción.',
                'metadata' => [
                    [
                        'label' => 'Prep item',
                        'value' => $prepItem->title,
                    ],
                ],
                'title' => 'Actualización propuesta de prep item',
                'type' => 'Prep update',
            ],
            [
                [
                    'label' => 'Prep item',
                    'value' => $prepItem->title,
                ],
                [
                    'label' => 'Acción',
                    'value' => 'Actualizar ítem',
                ],
            ],
            [
                'entity' => $entity,
                'input' => $input,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function executeTaskUpdate(
        array $tool,
        array $context,
        array $draft
    ): array {
        $workspaceId = $context['workspace']->id;
        $entity = $this->validateEntityPayload(
            is_array($draft['entity'] ?? null) ? $draft['entity'] : [],
            'task'
        );
        $input = $this->validateTaskInput(
            is_array($draft['input'] ?? null) ? $draft['input'] : [],
            $workspaceId
        );
        $task = $this->loadTaskForTool($workspaceId, $entity['id']);

        Gate::forUser($context['user'])->authorize('update', $task);

        $updated = $this->updateTask->execute(
            $task,
            (int) $entity['version'],
            $this->mapTaskUpdateAttributes($input),
            $context['user']->id
        );

        if (!$updated) {
            throw ValidationException::withMessages([
                'version' => ['The task changed before this confirmation was executed.'],
            ]);
        }

        $updated = $this->loadTaskForTool($workspaceId, $updated->id);

        return [
            'blocks' => [
                [
                    'text' => 'La tarea se actualizó correctamente.',
                    'type' => 'text',
                ],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'description' => 'La actualización confirmada ya se aplicó en el workspace.',
                        'details' => [
                            [
                                'label' => 'Tarea',
                                'value' => $updated->title,
                            ],
                            [
                                'label' => 'Estado',
                                'value' => $updated->status,
                            ],
                        ],
                        'status' => 'success',
                        'title' => 'Tarea actualizada',
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'result_ref_json' => (new TaskResource($updated))->resolve(),
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executePrepItemUpdate(
        array $tool,
        array $context,
        array $draft
    ): array {
        $workspaceId = $context['workspace']->id;
        $entity = $this->validateEntityPayload(
            is_array($draft['entity'] ?? null) ? $draft['entity'] : [],
            'prep_item'
        );
        $input = $this->validatePrepItemInput(
            is_array($draft['input'] ?? null) ? $draft['input'] : [],
            $workspaceId
        );
        $prepItem = $this->loadPrepItemForTool($workspaceId, $entity['id']);

        Gate::forUser($context['user'])->authorize('update', $prepItem);

        $updated = $this->updatePrepItem->execute(
            $prepItem,
            (int) $entity['version'],
            $this->mapPrepItemUpdateAttributes($input),
            $context['user']->id
        );

        if (!$updated) {
            throw ValidationException::withMessages([
                'version' => ['The prep item changed before this confirmation was executed.'],
            ]);
        }

        return [
            'blocks' => [
                [
                    'text' => 'El ítem de producción se actualizó correctamente.',
                    'type' => 'text',
                ],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'description' => 'La actualización confirmada ya fue aplicada.',
                        'details' => [
                            [
                                'label' => 'Prep item',
                                'value' => $updated->title,
                            ],
                            [
                                'label' => 'Estado',
                                'value' => $updated->status,
                            ],
                        ],
                        'status' => 'success',
                        'title' => 'Prep item actualizado',
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'result_ref_json' => (new PrepItemResource($updated))->resolve(),
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function buildConfirmationPreview(
        array $tool,
        array $source,
        array $context,
        array $payload,
        array $previewData,
        array $confirmationDetails,
        array $draft
    ): array {
        [$token, $confirmation] = $this->createConfirmation(
            $source,
            $tool,
            $context,
            $payload,
            [
                ...$draft,
                'preview' => $previewData,
            ]
        );

        return [
            'blocks' => [
                [
                    'text' => 'Preparé un borrador seguro. Revisa el cambio antes de confirmarlo.',
                    'type' => 'text',
                ],
                [
                    'component' => 'action.preview',
                    'data' => $previewData,
                    'schema_version' => 1,
                    'type' => 'component',
                ],
                [
                    'component' => 'action.confirm',
                    'data' => [
                        'confirmation_token' => $token,
                        'description' => 'Esta acción se ejecutará solo después de tu confirmación explícita.',
                        'details' => $confirmationDetails,
                        'title' => 'Confirma esta acción',
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'confirmation' => [
                'expires_at' => $confirmation->expires_at?->toIso8601String(),
                'id' => $confirmation->id,
                'status' => $confirmation->status,
                'token' => $token,
            ],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function createConfirmation(
        array $source,
        array $tool,
        array $context,
        array $payload,
        array $draft
    ): array {
        $token = Str::random(48);
        $confirmation = ActionConfirmation::query()->create([
            'workspace_id' => $source['workspace_id'],
            'message_id' => $source['message_id'],
            'ai_tool_call_id' => $context['ai_tool_call_id'] ?? null,
            'action_key' => $tool['key'],
            'token_hash' => hash('sha256', $token),
            'draft_json' => [
                ...$draft,
                'action_id' => $payload['action_id'] ?? $tool['action_id'],
                'component_instance_id' => $source['component_instance_id'],
                'source_component_key' => $source['component_key'],
            ],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'correlation_id' => (string) Str::ulid(),
        ]);

        return [$token, $confirmation];
    }

    private function validateEntityPayload(array $entity, string $expectedType): array
    {
        return Validator::make($entity, [
            'id' => ['required', 'ulid'],
            'type' => ['required', Rule::in([$expectedType])],
            'version' => ['required', 'integer', 'min:1'],
        ])->validate();
    }

    private function validateTaskInput(array $input, string $workspaceId): array
    {
        return Validator::make($input, [
            'due_at' => ['sometimes', 'nullable', 'date'],
            'membership_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('workspace_memberships', 'id')->where(function ($query) use ($workspaceId): void {
                    $query
                        ->where('workspace_id', $workspaceId)
                        ->where('status', 'active');
                }),
            ],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'title' => ['sometimes', 'string', 'max:255'],
        ])->validate();
    }

    private function validatePrepItemInput(array $input, string $workspaceId): array
    {
        return Validator::make($input, [
            'assignment_membership_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('workspace_memberships', 'id')->where(function ($query) use ($workspaceId): void {
                    $query
                        ->where('workspace_id', $workspaceId)
                        ->where('status', 'active');
                }),
            ],
            'blocked_reason' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'quantity' => ['sometimes', 'nullable', 'numeric'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'skipped'])],
            'title' => ['sometimes', 'string', 'max:255'],
        ])->validate();
    }

    private function buildTaskChanges(Task $task, array $input, string $workspaceId): array
    {
        $changes = [];

        if (array_key_exists('title', $input) && $input['title'] !== $task->title) {
            $changes[] = [
                'after' => $input['title'],
                'before' => $task->title,
                'label' => 'Título',
            ];
        }

        if (array_key_exists('status', $input) && $input['status'] !== $task->status) {
            $changes[] = [
                'after' => $input['status'],
                'before' => $task->status,
                'label' => 'Estado',
            ];
        }

        if (array_key_exists('priority', $input) && $input['priority'] !== $task->priority) {
            $changes[] = [
                'after' => $input['priority'],
                'before' => $task->priority,
                'label' => 'Prioridad',
            ];
        }

        if (array_key_exists('due_at', $input)) {
            $currentValue = $task->due_at?->toIso8601String();
            $nextValue = $input['due_at'] ?? null;

            if ($currentValue !== $nextValue) {
                $changes[] = [
                    'after' => $nextValue ?? 'Sin fecha',
                    'before' => $currentValue ?? 'Sin fecha',
                    'label' => 'Fecha límite',
                ];
            }
        }

        if (array_key_exists('membership_id', $input)) {
            $currentAssignment = $task->assignments->firstWhere('is_primary', true)
                ?? $task->assignments->first();
            $before = $currentAssignment?->membership?->user?->name ?? 'Sin asignar';
            $after = $this->resolveMembershipLabel($workspaceId, $input['membership_id'] ?? null);

            if ($before !== $after) {
                $changes[] = [
                    'after' => $after,
                    'before' => $before,
                    'label' => 'Responsable',
                ];
            }
        }

        return $changes;
    }

    private function buildPrepItemChanges(PrepItem $prepItem, array $input, string $workspaceId): array
    {
        $changes = [];

        if (array_key_exists('title', $input) && $input['title'] !== $prepItem->title) {
            $changes[] = [
                'after' => $input['title'],
                'before' => $prepItem->title,
                'label' => 'Título',
            ];
        }

        if (array_key_exists('status', $input) && $input['status'] !== $prepItem->status) {
            $changes[] = [
                'after' => $input['status'],
                'before' => $prepItem->status,
                'label' => 'Estado',
            ];
        }

        if (array_key_exists('quantity', $input) && (string) ($input['quantity'] ?? '') !== (string) ($prepItem->quantity ?? '')) {
            $changes[] = [
                'after' => (string) ($input['quantity'] ?? 'Sin cantidad'),
                'before' => (string) ($prepItem->quantity ?? 'Sin cantidad'),
                'label' => 'Cantidad',
            ];
        }

        if (array_key_exists('priority', $input) && $input['priority'] !== $prepItem->priority) {
            $changes[] = [
                'after' => $input['priority'],
                'before' => $prepItem->priority,
                'label' => 'Prioridad',
            ];
        }

        if (array_key_exists('assignment_membership_id', $input)) {
            $currentAssignment = $prepItem->assignments->firstWhere('is_primary', true)
                ?? $prepItem->assignments->first();
            $before = $currentAssignment?->membership?->user?->name ?? 'Sin asignar';
            $after = $this->resolveMembershipLabel(
                $workspaceId,
                $input['assignment_membership_id'] ?? null
            );

            if ($before !== $after) {
                $changes[] = [
                    'after' => $after,
                    'before' => $before,
                    'label' => 'Responsable',
                ];
            }
        }

        return $changes;
    }

    private function mapTaskUpdateAttributes(array $input): array
    {
        $attributes = [];

        if (array_key_exists('title', $input)) {
            $attributes['title'] = $input['title'];
        }

        if (array_key_exists('status', $input)) {
            $attributes['status'] = $input['status'];
        }

        if (array_key_exists('priority', $input)) {
            $attributes['priority'] = $input['priority'];
        }

        if (array_key_exists('due_at', $input)) {
            $attributes['due_at'] = $input['due_at'];
        }

        if (array_key_exists('membership_id', $input)) {
            $attributes['assignments'] = $input['membership_id']
                ? [[
                    'is_primary' => true,
                    'membership_id' => $input['membership_id'],
                    'status' => 'assigned',
                ]]
                : [];
        }

        return $attributes;
    }

    private function mapPrepItemUpdateAttributes(array $input): array
    {
        $attributes = [];

        foreach ([
            'assignment_membership_id',
            'blocked_reason',
            'due_at',
            'notes',
            'priority',
            'quantity',
            'status',
            'title',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $attributes[$key] = $input[$key];
            }
        }

        return $attributes;
    }

    private function loadTaskForTool(string $workspaceId, string $taskId): Task
    {
        return Task::query()
            ->whereKey($taskId)
            ->where('workspace_id', $workspaceId)
            ->with([
                'assignments.assignedBy',
                'assignments.membership.role',
                'assignments.membership.user',
                'completedBy',
                'createdBy',
                'event',
                'station.team',
                'team',
                'updatedBy',
            ])
            ->firstOrFail();
    }

    private function loadPrepItemForTool(string $workspaceId, string $prepItemId): PrepItem
    {
        return PrepItem::query()
            ->whereKey($prepItemId)
            ->where('workspace_id', $workspaceId)
            ->with([
                'assignments.assignedBy',
                'assignments.membership.role',
                'assignments.membership.teams',
                'assignments.membership.user',
                'actualUnit',
                'completedBy',
                'createdBy',
                'recipe',
                'recipeVersion',
                'unit',
                'updatedBy',
                'yieldUnit',
            ])
            ->firstOrFail();
    }

    private function resolveConfirmationSource(array $context): array
    {
        if (isset($context['source_block']) && $context['source_block'] instanceof MessageBlock) {
            return [
                'component_instance_id' => $context['source_block']->instance_id,
                'component_key' => $context['source_block']->component_key,
                'message_id' => $context['source_block']->message_id,
                'workspace_id' => $context['source_block']->workspace_id,
            ];
        }

        if (isset($context['source_message']) && $context['source_message'] instanceof Message) {
            return [
                'component_instance_id' => (string) Str::ulid(),
                'component_key' => 'assistant.message',
                'message_id' => $context['source_message']->id,
                'workspace_id' => $context['source_message']->workspace_id,
            ];
        }

        throw ValidationException::withMessages([
            'component_instance_id' => ['The source component block or assistant message is required.'],
        ]);
    }

    private function resolveMembershipLabel(string $workspaceId, ?string $membershipId): string
    {
        if (!$membershipId) {
            return 'Sin asignar';
        }

        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->with('user')
            ->find($membershipId);

        return $membership?->user?->name
            ?? $membership?->id
            ?? 'Sin asignar';
    }

    private function assertHasChanges(array $changes): void
    {
        if ($changes === []) {
            throw ValidationException::withMessages([
                'input' => ['The requested action does not introduce a meaningful change.'],
            ]);
        }
    }

    private function readSummaryText(string $toolKey, int $count): string
    {
        return match ($toolKey) {
            'events.list' => "Encontré {$count} eventos para este contexto.",
            'prep.list' => "Encontré {$count} listas de prep para este contexto.",
            'tasks.mine' => "Encontré {$count} tareas abiertas asignadas a tu membresía.",
            default => "Encontré {$count} resultados.",
        };
    }

    private function readComponentPayload(string $toolKey, array $result): array
    {
        return match ($toolKey) {
            'events.list' => [
                'events' => $result['items'] ?? [],
                'title' => 'Eventos',
            ],
            'prep.list' => [
                'items' => $result['items'] ?? [],
                'title' => 'Prep activa',
            ],
            'tasks.mine' => [
                'tasks' => $result['items'] ?? [],
                'title' => 'Tus tareas abiertas',
            ],
            default => [
                'items' => $result['items'] ?? [],
            ],
        };
    }
}
