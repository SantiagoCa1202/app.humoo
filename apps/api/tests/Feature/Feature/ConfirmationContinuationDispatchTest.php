<?php

namespace Tests\Feature\Feature;

use App\AI\Intent\IntentPatternRegistry;
use App\AI\Tools\ToolExecutor;
use App\Jobs\ContinueConfirmedConversation;
use App\Models\ActionConfirmation;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
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
        $this->app->bind(IntentPatternRegistry::class, static function (): never {
            throw new RuntimeException('Legacy intent pattern registry was resolved.');
        });

        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $actor->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Queued confirmation continuation',
            'visibility' => 'private',
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
        $this->assertSame($conversation->id, $response->json('data.conversation.id'));
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
