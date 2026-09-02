<?php

namespace App\Application\Actions\Chat;

use App\AI\Orchestration\MessageLocaleResolver;
use App\Jobs\ProcessChatMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;

class SendMessage
{
    public function __construct(
        private AssistantMessageWriter $assistantMessageWriter,
        private MessageLocaleResolver $messageLocaleResolver,
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

        $locale = $this->messageLocaleResolver->resolve(null, '', $workspace, $user);

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
                            'blocking' => false,
                            'description' => $locale === 'es'
                                ? 'Elige el foco inicial para continuar.'
                                : 'Choose the initial focus to continue.',
                            'expects_response' => false,
                            'kind' => 'onboarding',
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
        $messageLocale = $this->messageLocaleResolver->resolve(
            $payload['locale'] ?? null,
            (string) ($payload['content'] ?? ''),
            $workspace,
            $user,
        );
        $payload['locale'] = $messageLocale;

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

        $userMessage = DB::transaction(function () use ($clientMessageId, $conversation, $messageLocale, $payload, $user, $workspace): Message {
            $message = Message::query()->create([
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation->id,
                'sender_type' => 'user',
                'sender_id' => $user->id,
                'status' => 'pending',
                'locale' => $messageLocale,
                'content_text' => $payload['content'],
                'client_message_id' => $clientMessageId,
                'metadata' => [
                    'source' => 'chat',
                ],
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            ProcessChatMessage::dispatch(
                (string) $conversation->id,
                (string) $workspace->id,
                (string) $user->id,
                (string) $message->id,
            )->afterCommit();

            return $message;
        });

        return [
            'assistant_message' => null,
            'conversation' => $conversation->fresh(),
            'user_message' => $userMessage->fresh('blocks'),
        ];
    }

}
