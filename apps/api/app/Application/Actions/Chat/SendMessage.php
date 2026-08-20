<?php

namespace App\Application\Actions\Chat;

use App\AI\Orchestration\AIOrchestrator;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;

class SendMessage
{
    public function __construct(
        private AIOrchestrator $aiOrchestrator,
        private AssistantMessageWriter $assistantMessageWriter
    ) {
    }

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

        $locale = $this->resolveLocale($workspace, $membership, $user);

        return $this->assistantMessageWriter->create(
            $conversation,
            $workspace,
            $locale,
            [
                'blocks' => [
                    [
                        'type' => 'text',
                        'text' => $locale === 'es'
                            ? 'Puedo convertir tus preguntas del workspace activo en contexto operativo seguro.'
                            : 'I can turn questions from the active workspace into safe operational context.',
                    ],
                    [
                        'type' => 'component',
                        'component' => 'clarification.options',
                        'schema_version' => 1,
                        'data' => [
                            'description' => $locale === 'es'
                                ? 'Elige el foco inicial para continuar.'
                                : 'Choose the initial focus to continue.',
                            'options' => [
                                [
                                    'id' => 'events',
                                    'label' => $locale === 'es' ? 'Eventos' : 'Events',
                                    'value' => $locale === 'es'
                                        ? 'Muestrame los eventos de manana'
                                        : 'Show me tomorrow events',
                                ],
                                [
                                    'id' => 'prep',
                                    'label' => $locale === 'es' ? 'Prep activa' : 'Active prep',
                                    'value' => $locale === 'es'
                                        ? 'Muestrame el prep activo'
                                        : 'Show me active prep',
                                ],
                                [
                                    'id' => 'tasks',
                                    'label' => $locale === 'es' ? 'Mis tareas' : 'My tasks',
                                    'value' => $locale === 'es'
                                        ? 'Muestrame mis tareas abiertas'
                                        : 'Show my open tasks',
                                ],
                            ],
                            'selection_mode' => 'immediate',
                            'title' => $locale === 'es'
                                ? 'Que necesitas revisar primero?'
                                : 'What should I review first?',
                        ],
                    ],
                ],
                'suggestions' => $locale === 'es'
                    ? [
                        'Muestrame los eventos de manana',
                        'Muestrame el prep activo',
                        'Muestrame mis tareas abiertas',
                    ]
                    : [
                        'Show me tomorrow events',
                        'Show me active prep',
                        'Show my open tasks',
                    ],
            ],
            null,
            [
                'source' => 'assistant-bootstrap',
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
            'locale' => $payload['locale'] ?? $this->resolveLocale($workspace, $membership, $user),
            'content_text' => $payload['content'],
            'client_message_id' => $clientMessageId,
            'metadata' => [
                'source' => 'chat',
            ],
        ]);

        $assistantMessage = $this->aiOrchestrator->respond(
            $conversation,
            $workspace,
            $membership,
            $user,
            $userMessage,
            $payload
        );

        return [
            'assistant_message' => $assistantMessage->load('blocks'),
            'conversation' => $conversation->fresh(['messages.blocks']),
            'user_message' => $userMessage->fresh('blocks'),
        ];
    }

    private function resolveLocale(
        Workspace $workspace,
        WorkspaceMembership $membership,
        User $user
    ): string {
        $locale = strtolower(substr(
            (string) ($workspace->locale ?? $membership->locale ?? $user->locale ?? config('app.locale', 'en')),
            0,
            2
        ));

        return in_array($locale, ['en', 'es'], true) ? $locale : 'en';
    }
}
