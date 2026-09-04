<?php

namespace Tests\Feature\Feature;

use App\AI\Contracts\ToolCallingProvider;
use App\AI\Intent\IntentPatternRegistry;
use App\AI\Orchestration\AIOrchestrator;
use App\AI\Tools\ToolExecutor;
use App\Jobs\ContinueConfirmedConversation;
use App\Models\ActionConfirmation;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class ConfirmationContinuationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_queues_a_pending_provider_continuation_without_replaying_it(): void
    {
        config()->set('ai.routing.tool_loop_enabled', true);
        config()->set('ai.chat_streaming_enabled', false);
        $this->app->bind(IntentPatternRegistry::class, static function (): never {
            throw new RuntimeException('Legacy intent pattern registry was resolved.');
        });
        $provider = new class implements ToolCallingProvider
        {
            public function toolTurn(
                array $context,
                array $tools,
                ?string $previousResponseId = null,
                array $input = [],
            ): array {
                return [
                    'model' => 'test-confirmation-continuation',
                    'output' => [[
                        'type' => 'function_call',
                        'name' => 'orchestration_respond',
                        'call_id' => 'call-confirmation-continuation-complete',
                        'arguments' => json_encode([
                            'status' => 'completed',
                            'message' => 'Continuation completed.',
                        ], JSON_THROW_ON_ERROR),
                    ]],
                    'provider' => 'test',
                    'response_id' => 'response-confirmation-continuation',
                    'usage' => [
                        'input_tokens' => 11,
                        'output_tokens' => 3,
                        'total_tokens' => 14,
                    ],
                ];
            }
        };
        $this->app->bind(ToolCallingProvider::class, fn (): ToolCallingProvider => $provider);

        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $actor->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Queued confirmation continuation',
            'visibility' => 'private',
            'openai_conversation_id' => 'conversation-confirmation-continuation',
            'workspace_id' => $workspace->id,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $actor->id,
            'workspace_id' => $workspace->id,
        ]);
        $sourceMessage = Message::query()->create([
            'content_text' => 'Crea la tarea y después muéstrame el resultado.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $originatingRun = AiRun::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'actor_id' => $actor->id,
            'message_id' => $sourceMessage->id,
            'input_message_id' => $sourceMessage->id,
            'model_key' => 'test-model',
            'status' => 'waiting_confirmation',
            'current_stage' => 'waiting_confirmation',
            'queued_at' => now(),
            'started_at' => now(),
        ]);

        app()->instance('currentWorkspace', $workspace);
        $preview = app(ToolExecutor::class)->request([
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000007',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'user' => $actor,
            'workspace' => $workspace,
        ], [
            'action_id' => 'tasks.create',
            'input' => [
                'priority' => 'normal',
                'status' => 'todo',
                'title' => 'Revisar confirmación asíncrona',
                'type' => 'general',
            ],
        ]);
        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $conversation->forceFill(['metadata' => [
            'pending_provider_tool_outputs' => [[
                'action_key' => 'tasks.create',
                'call_id' => 'call-queued-confirmation-continuation',
                'continuation_id' => $confirmation->id,
                'output' => null,
            ]],
        ]])->save();

        Queue::fake();
        $token = $this->login('owner@humoo.local', 'password');

        $response = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson("/api/v1/confirmations/{$preview['confirmation']['token']}/confirm", [
                'idempotency_key' => $confirmation->idempotency_key,
            ])
            ->assertOk()
            ->assertJsonPath('data.confirmation.id', $confirmation->id)
            ->assertJsonPath('data.confirmation.status', 'executed')
            ->assertJsonPath('data.continuation.status', 'queued');

        Queue::assertPushed(ContinueConfirmedConversation::class, function (ContinueConfirmedConversation $job) use ($actor, $confirmation, $conversation, $workspace): bool {
            return $job->confirmationId === $confirmation->id
                && $job->conversationId === $conversation->id
                && $job->workspaceId === $workspace->id
                && $job->userId === $actor->id;
        });

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson("/api/v1/confirmations/{$preview['confirmation']['token']}/confirm", [
                'idempotency_key' => $confirmation->idempotency_key,
            ])
            ->assertOk()
            ->assertJsonPath('data.confirmation.status', 'executed');

        Queue::assertPushed(ContinueConfirmedConversation::class, 1);
        $this->assertSame('executed', $confirmation->fresh()->status);
        $this->assertSame('completed', $originatingRun->fresh()->status);
        $this->assertSame(0, AiRun::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', 'waiting_confirmation')->count());
        $this->assertSame($conversation->id, $response->json('data.conversation.id'));

        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->firstOrFail();
        $continuedMessage = app(AIOrchestrator::class)->continueConfirmedConversation(
            $confirmation->fresh('message.conversation'),
            [
                'status' => 'completed',
                'workflow_status' => 'completed',
                'tool_keys' => ['tasks.create'],
                'entity_refs' => [],
            ],
            $workspace,
            $membership,
            $actor,
        );

        $this->assertNotNull($continuedMessage);
        $this->assertSame('Continuation completed.', $continuedMessage->content_text);
        $continuationRun = AiRun::query()->where('message_id', $continuedMessage->id)->firstOrFail();
        $this->assertSame(11, data_get($continuationRun->usage_json, 'input_tokens'));
        $this->assertSame('completed', data_get($continuationRun->metadata, 'termination_reason'));
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'device_name' => 'phpunit-confirmation-continuation',
            'email' => $email,
            'password' => $password,
        ])->assertOk()->json('token');
    }
}
