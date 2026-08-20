<?php

namespace App\Application\Actions\Chat;

use App\AI\Presentation\ComponentRegistry;
use App\Http\Resources\EventResource;
use App\Http\Resources\PrepListResource;
use App\Http\Resources\TaskResource;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Message;
use App\Models\PrepList;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SendMessage
{
    public function bootstrap(
        Conversation $conversation,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
    ): Message {
        $existingMessage = $conversation->messages()
            ->where('sender_type', 'assistant')
            ->with('blocks')
            ->first();

        if ($existingMessage) {
            return $existingMessage;
        }

        return $this->createAssistantMessage(
            $conversation,
            $workspace,
            $membership,
            $user,
            [
                'blocks' => [
                    [
                        'type' => 'text',
                        'text' => 'Puedo convertir mensajes del equipo en contexto operativo con componentes seguros del workspace activo.',
                    ],
                    [
                        'type' => 'component',
                        'component' => 'clarification.options',
                        'schema_version' => 1,
                        'data' => [
                            'description' => 'Elige el foco inicial para esta conversación.',
                            'options' => [
                                [
                                    'id' => 'events',
                                    'label' => 'Próximos eventos',
                                    'value' => 'Muéstrame los próximos eventos',
                                ],
                                [
                                    'id' => 'prep',
                                    'label' => 'Listas de prep',
                                    'value' => 'Muéstrame las listas de prep activas',
                                ],
                                [
                                    'id' => 'tasks',
                                    'label' => 'Mis tareas',
                                    'value' => 'Muéstrame mis tareas abiertas',
                                ],
                            ],
                            'selection_mode' => 'immediate',
                            'title' => '¿Qué necesitas revisar primero?',
                        ],
                    ],
                ],
                'suggestions' => [
                    'Muéstrame los próximos eventos',
                    'Muéstrame las listas de prep activas',
                    'Muéstrame mis tareas abiertas',
                ],
            ]
        );
    }

    public function execute(
        Conversation $conversation,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
        array $payload
    ): array {
        $clientMessageId = $payload['client_message_id'] ?? null;

        if ($clientMessageId) {
            $existingUserMessage = $conversation->messages()
                ->where('client_message_id', $clientMessageId)
                ->with('blocks')
                ->first();

            if ($existingUserMessage) {
                $assistantMessage = $conversation->messages()
                    ->where('parent_message_id', $existingUserMessage->id)
                    ->where('sender_type', 'assistant')
                    ->with('blocks')
                    ->latest('created_at')
                    ->first();

                return [
                    'assistant_message' => $assistantMessage,
                    'conversation' => $conversation->fresh(['messages.blocks']),
                    'user_message' => $existingUserMessage,
                ];
            }
        }

        $userMessage = Message::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'status' => 'completed',
            'locale' => $payload['locale'] ?? null,
            'content_text' => $payload['content'],
            'client_message_id' => $clientMessageId,
            'metadata' => [
                'source' => 'chat',
            ],
        ]);

        $assistantPayload = $this->buildAssistantPayload(
            $workspace,
            $membership,
            $user,
            $payload['content']
        );

        $assistantMessage = $this->createAssistantMessage(
            $conversation,
            $workspace,
            $membership,
            $user,
            $assistantPayload,
            $userMessage
        );

        return [
            'assistant_message' => $assistantMessage->load('blocks'),
            'conversation' => $conversation->fresh(['messages.blocks']),
            'user_message' => $userMessage->fresh('blocks'),
        ];
    }

    private function buildAssistantPayload(
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
        string $content
    ): array {
        $normalized = Str::lower(trim($content));

        if ($this->containsAny($normalized, ['evento', 'eventos', 'event', 'events'])) {
            return $this->buildEventsPayload($workspace, $user);
        }

        if ($this->containsAny($normalized, ['prep', 'prepar', 'mise en place', 'producción', 'produccion'])) {
            return $this->buildPrepPayload($workspace, $user);
        }

        if ($this->containsAny($normalized, ['tarea', 'tareas', 'task', 'tasks', 'pendiente'])) {
            return $this->buildTasksPayload($workspace, $membership, $user);
        }

        return [
            'blocks' => [
                [
                    'type' => 'text',
                    'text' => 'Puedo responder con bloques de eventos, prep o tareas usando el workspace activo.',
                ],
                [
                    'type' => 'component',
                    'component' => 'clarification.options',
                    'schema_version' => 1,
                    'data' => [
                        'description' => 'Elige una de estas consultas guiadas para continuar.',
                        'options' => [
                            [
                                'id' => 'clarify-events',
                                'label' => 'Próximos eventos',
                                'value' => 'Muéstrame los próximos eventos',
                            ],
                            [
                                'id' => 'clarify-prep',
                                'label' => 'Prep activa',
                                'value' => 'Muéstrame las listas de prep activas',
                            ],
                            [
                                'id' => 'clarify-tasks',
                                'label' => 'Mis tareas',
                                'value' => 'Muéstrame mis tareas abiertas',
                            ],
                        ],
                        'selection_mode' => 'immediate',
                        'title' => 'Necesito un poco más de dirección',
                    ],
                ],
            ],
            'suggestions' => [
                'Muéstrame los próximos eventos',
                'Muéstrame las listas de prep activas',
                'Muéstrame mis tareas abiertas',
            ],
        ];
    }

    private function buildEventsPayload(Workspace $workspace, User $user): array
    {
        if (!$user->hasWorkspacePermission($workspace->id, 'events.view')) {
            return $this->forbiddenPayload(
                'No tienes permiso para ver eventos en este workspace.'
            );
        }

        $events = Event::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereRaw('COALESCE(ends_at, starts_at) >= ?', [now()])
            ->with([
                'client.primaryContact',
                'contact.client',
                'group',
                'venue',
            ])
            ->orderBy('starts_at')
            ->limit(4)
            ->get();

        if ($events->isEmpty()) {
            return [
                'blocks' => [
                    [
                        'type' => 'text',
                        'text' => 'No encontré eventos próximos en este momento.',
                    ],
                    [
                        'type' => 'component',
                        'component' => 'events.list',
                        'schema_version' => 1,
                        'data' => [
                            'events' => [],
                            'title' => 'Próximos eventos',
                        ],
                    ],
                ],
                'suggestions' => [
                    'Muéstrame las listas de prep activas',
                    'Muéstrame mis tareas abiertas',
                ],
            ];
        }

        return [
            'blocks' => [
                [
                    'type' => 'text',
                    'text' => sprintf(
                        'Encontré %d eventos activos en el horizonte inmediato del workspace.',
                        $events->count()
                    ),
                ],
                [
                    'type' => 'component',
                    'component' => 'events.list',
                    'schema_version' => 1,
                    'data' => [
                        'events' => EventResource::collection($events)->resolve(),
                        'title' => 'Próximos eventos',
                    ],
                ],
            ],
            'suggestions' => [
                'Muéstrame las listas de prep activas',
                'Muéstrame mis tareas abiertas',
            ],
        ];
    }

    private function buildPrepPayload(Workspace $workspace, User $user): array
    {
        if (!$user->hasWorkspacePermission($workspace->id, 'prep_lists.view')) {
            return $this->forbiddenPayload(
                'No tienes permiso para ver listas de prep en este workspace.'
            );
        }

        $prepLists = PrepList::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('status', ['active', 'in_progress', 'review', 'approved'])
            ->with([
                'event',
                'currentVersionRecord.sections.items.assignments',
            ])
            ->orderByRaw("case when status in ('active', 'in_progress') then 0 else 1 end")
            ->orderByRaw('production_starts_at is null')
            ->orderBy('production_starts_at')
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        if ($prepLists->isEmpty()) {
            return [
                'blocks' => [
                    [
                        'type' => 'text',
                        'text' => 'No hay listas de prep activas ahora mismo.',
                    ],
                    [
                        'type' => 'component',
                        'component' => 'prep.list',
                        'schema_version' => 1,
                        'data' => [
                            'items' => [],
                            'title' => 'Prep activa',
                        ],
                    ],
                ],
                'suggestions' => [
                    'Muéstrame los próximos eventos',
                    'Muéstrame mis tareas abiertas',
                ],
            ];
        }

        $items = $prepLists->map(function (PrepList $prepList): array {
            $resource = (new PrepListResource($prepList))->resolve();

            return [
                'prep_list' => $resource,
                'progress' => $resource['progress'] ?? null,
            ];
        })->values()->all();

        return [
            'blocks' => [
                [
                    'type' => 'text',
                    'text' => sprintf(
                        'Estas son las %d listas de prep con mayor prioridad en este momento.',
                        count($items)
                    ),
                ],
                [
                    'type' => 'component',
                    'component' => 'prep.list',
                    'schema_version' => 1,
                    'data' => [
                        'items' => $items,
                        'title' => 'Prep activa',
                    ],
                ],
            ],
            'suggestions' => [
                'Muéstrame los próximos eventos',
                'Muéstrame mis tareas abiertas',
            ],
        ];
    }

    private function buildTasksPayload(
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user
    ): array {
        if (!$user->hasWorkspacePermission($workspace->id, 'tasks.view')) {
            return $this->forbiddenPayload(
                'No tienes permiso para ver tareas en este workspace.'
            );
        }

        $tasks = Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereHas('assignments', function ($query) use ($membership): void {
                $query->where('membership_id', $membership->id);
            })
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
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return [
            'blocks' => [
                [
                    'type' => 'text',
                    'text' => $tasks->isEmpty()
                        ? 'No tienes tareas abiertas asignadas ahora mismo.'
                        : sprintf(
                            'Tienes %d tareas abiertas asignadas en este workspace.',
                            $tasks->count()
                        ),
                ],
                [
                    'type' => 'component',
                    'component' => 'tasks.mine',
                    'schema_version' => 1,
                    'data' => [
                        'tasks' => TaskResource::collection($tasks)->resolve(),
                        'title' => 'Tus tareas abiertas',
                    ],
                ],
            ],
            'suggestions' => [
                'Muéstrame los próximos eventos',
                'Muéstrame las listas de prep activas',
            ],
        ];
    }

    private function forbiddenPayload(string $message): array
    {
        return [
            'blocks' => [
                [
                    'type' => 'component',
                    'component' => 'error.recovery',
                    'schema_version' => 1,
                    'data' => [
                        'description' => 'Necesitas un permiso adicional o cambiar de workspace antes de continuar.',
                        'safe_detail' => $message,
                        'title' => 'No puedo completar esa consulta',
                    ],
                ],
            ],
            'suggestions' => [
                'Muéstrame los próximos eventos',
                'Muéstrame las listas de prep activas',
                'Muéstrame mis tareas abiertas',
            ],
        ];
    }

    private function createAssistantMessage(
        Conversation $conversation,
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user,
        array $payload,
        ?Message $parentMessage = null
    ): Message {
        $assistantMessage = Message::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'sender_type' => 'assistant',
            'sender_id' => null,
            'status' => 'completed',
            'locale' => $membership->workspace?->locale ?? null,
            'content_text' => $this->firstTextBlock($payload['blocks'] ?? []),
            'parent_message_id' => $parentMessage?->id,
            'metadata' => [
                'suggestions' => $payload['suggestions'] ?? [],
                'source' => 'assistant-response',
            ],
        ]);

        foreach ($payload['blocks'] ?? [] as $position => $block) {
            $this->createBlock(
                $assistantMessage,
                $workspace->id,
                $position,
                $block
            );
        }

        $timestamp = now();
        $conversation->forceFill([
            'last_message_at' => $timestamp,
        ])->save();

        if ($parentMessage) {
            $parentMessage->forceFill([
                'updated_at' => $timestamp,
            ])->save();
        }

        return $assistantMessage->fresh('blocks');
    }

    private function createBlock(
        Message $message,
        string $workspaceId,
        int $position,
        array $block
    ): void {
        $type = (string) ($block['type'] ?? 'text');

        if ($type === 'component') {
            $component = (string) ($block['component'] ?? '');
            $schemaVersion = (int) ($block['schema_version'] ?? 1);

            abort_unless(
                ComponentRegistry::supportsComponent($component, $schemaVersion),
                422,
                'Unsupported chat component.'
            );

            $message->blocks()->create([
                'workspace_id' => $workspaceId,
                'position' => $position,
                'block_type' => 'component',
                'component_key' => $component,
                'schema_version' => $schemaVersion,
                'instance_id' => (string) Str::ulid(),
                'payload_json' => [
                    'actions' => $block['actions'] ?? [],
                    'data' => $block['data'] ?? [],
                    'meta' => $block['meta'] ?? [],
                ],
                'refreshable' => (bool) ($block['meta']['refreshable'] ?? false),
                'generated_at' => now(),
            ]);

            return;
        }

        $message->blocks()->create([
            'workspace_id' => $workspaceId,
            'position' => $position,
            'block_type' => $type,
            'payload_json' => [
                'data' => $block['data'] ?? null,
                'meta' => $block['meta'] ?? [],
                'text' => $block['text'] ?? null,
            ],
            'generated_at' => now(),
        ]);
    }

    private function containsAny(string $content, array $needles): bool
    {
        return collect($needles)->contains(
            fn (string $needle) => Str::contains($content, Str::lower($needle))
        );
    }

    private function firstTextBlock(array $blocks): ?string
    {
        $firstTextBlock = collect($blocks)->first(
            fn (array $block) => ($block['type'] ?? null) === 'text'
                && is_string($block['text'] ?? null)
                && trim((string) $block['text']) !== ''
        );

        return $firstTextBlock['text'] ?? null;
    }
}
