<?php

namespace App\Application\Actions\Chat;

use App\AI\Orchestration\MessageLocaleResolver;
use App\AI\Objectives\AiObjectiveLifecycle;
use App\Jobs\ProcessChatMessage;
use App\Models\Conversation;
use App\Models\AiRun;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendMessage
{
    public function __construct(
        private AssistantMessageWriter $assistantMessageWriter,
        private MessageLocaleResolver $messageLocaleResolver,
        private AiObjectiveLifecycle $objectiveLifecycle,
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
                $aiRun = AiRun::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('input_message_id', $existingUserMessage->id)
                    ->latest('created_at')
                    ->first();

                return [
                    'assistant_message' => $assistantMessage,
                    'ai_run' => $aiRun,
                    'conversation' => $conversation->fresh(['messages.blocks']),
                    'user_message' => $existingUserMessage,
                ];
            }
        }

        $accepted = DB::transaction(function () use ($clientMessageId, $conversation, $messageLocale, $payload, $user, $workspace): array {
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
            $correlationId = (string) Str::ulid();

            // Persist the human objective before any provider call or queue
            // handoff. A reply to a waiting objective resumes the same durable
            // record; a terminal objective starts a new one.
            $objective = $this->objectiveLifecycle->startOrResume(
                $conversation,
                $workspace,
                $user,
                $message,
                (string) $payload['content'],
                $correlationId,
            );

            $assistantMessage = $this->assistantMessageWriter->createPending(
                $conversation,
                $workspace,
                $messageLocale,
                $message,
                [
                    'source' => 'assistant-response',
                    'orchestration_version' => 'tool-loop-v1',
                ],
            );
            $aiRun = AiRun::query()->create([
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation->id,
                'objective_id' => $objective->id,
                'actor_id' => $user->id,
                'message_id' => $assistantMessage->id,
                'input_message_id' => $message->id,
                'provider' => (string) config('ai.default', 'openai'),
                'model_key' => (string) config('ai.providers.'.config('ai.default', 'openai').'.model', 'openai'),
                'status' => 'queued',
                'current_stage' => 'queued',
                'queued_at' => now(),
                'deadline_at' => now()->addSeconds(max(60, (int) config('ai.deadlines.run_seconds', 540))),
                'last_heartbeat_at' => now(),
                'prompt_version' => (string) config('ai.prompt_version', 'humoo-chat-v1'),
                'orchestrator_version' => 'tool-loop-v1',
                'correlation_id' => $correlationId,
                'metadata' => [
                    'correlation_id' => $correlationId,
                    'runtime_version' => 'p0.4b-v1',
                ],
            ]);

            ProcessChatMessage::dispatch(
                (string) $conversation->id,
                (string) $workspace->id,
                (string) $user->id,
                (string) $message->id,
                (string) $aiRun->id,
            )->afterCommit();

            return compact('message', 'assistantMessage', 'aiRun');
        });

        return [
            'assistant_message' => $accepted['assistantMessage']->fresh('blocks'),
            'ai_run' => $accepted['aiRun']->fresh(),
            'conversation' => $conversation->fresh(),
            'user_message' => $accepted['message']->fresh('blocks'),
        ];
    }

}
