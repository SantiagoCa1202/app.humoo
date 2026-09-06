<?php

namespace Tests\Feature\Feature;

use App\Models\Conversation;
use App\Models\AiRun;
use App\Models\AiObjective;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Jobs\ProcessChatMessage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_new_private_chat_conversation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');

        $conversationId = (string) $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/chat/conversations')
            ->assertCreated()
            ->assertJsonPath('data.conversation.title', 'Humoo AI')
            ->assertJsonCount(1, 'data.conversation.messages')
            ->json('data.conversation.id');

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'workspace_id' => $workspace->id,
            'scope_type' => 'general',
            'visibility' => 'private',
        ]);
        $this->assertDatabaseHas('conversation_participants', [
            'conversation_id' => $conversationId,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_chat_messages_are_returned_in_bounded_cursor_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()
            ->where('email', 'owner@humoo.local')
            ->firstOrFail();
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'title' => 'Paged chat',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        ConversationParticipant::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        foreach (range(1, 5) as $position) {
            Message::query()->create([
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversation->id,
                'sender_type' => 'user',
                'sender_id' => $user->id,
                'status' => 'completed',
                'content_text' => "Message {$position}",
                'created_at' => now()->subMinutes(6 - $position),
                'updated_at' => now()->subMinutes(6 - $position),
            ]);
        }

        $token = $this->login('owner@humoo.local', 'password');
        $firstPage = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/chat?conversation_id={$conversation->id}&limit=2")
            ->assertOk()
            ->assertJsonCount(2, 'data.conversation.messages')
            ->assertJsonPath('data.pagination.has_more', true)
            ->json('data');

        $this->assertSame(
            ['Message 4', 'Message 5'],
            array_column($firstPage['conversation']['messages'], 'content_text'),
        );

        $secondPage = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson(
                "/api/v1/chat?conversation_id={$conversation->id}&limit=2&before_message_id={$firstPage['pagination']['next_before_message_id']}"
            )
            ->assertOk()
            ->assertJsonCount(2, 'data.conversation.messages')
            ->assertJsonPath('data.pagination.has_more', true)
            ->json('data');

        $this->assertSame(
            ['Message 2', 'Message 3'],
            array_column($secondPage['conversation']['messages'], 'content_text'),
        );
    }

    public function test_chat_message_is_accepted_and_queued_without_waiting_for_ai(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()
            ->where('email', 'owner@humoo.local')
            ->firstOrFail();
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'title' => 'Queued chat',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        ConversationParticipant::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $token = $this->login('owner@humoo.local', 'password');
        $response = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/chat/messages', [
                'client_message_id' => 'queued-chat-message',
                'content' => 'Create the technical recipes for this menu.',
                'conversation_id' => $conversation->id,
                'locale' => 'en',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.assistant_response', null)
            ->assertJsonPath('data.ai_run.status', 'queued')
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.assistant_message.status', 'pending')
            ->assertJsonPath('data.user_message.status', 'pending');

        $messageId = $response->json('data.user_message.id');
        $runId = $response->json('data.ai_run_id');
        Queue::assertPushed(ProcessChatMessage::class, function (ProcessChatMessage $job) use ($conversation, $messageId, $user, $workspace): bool {
            return $job->conversationId === $conversation->id
                && $job->messageId === $messageId
                && filled($job->aiRunId)
                && $job->userId === $user->id
                && $job->workspaceId === $workspace->id;
        });
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('ai_runs', [
            'id' => $runId,
            'conversation_id' => $conversation->id,
            'input_message_id' => $messageId,
            'status' => 'queued',
        ]);
        $objective = AiObjective::query()
            ->where('workspace_id', $workspace->id)
            ->where('conversation_id', $conversation->id)
            ->where('source_message_id', $messageId)
            ->firstOrFail();
        $this->assertSame('analyzing', $objective->status);
        $this->assertSame($objective->id, AiRun::query()->findOrFail($runId)->objective_id);
        $this->assertNotNull(AiRun::query()->findOrFail($runId)->deadline_at);
        $this->assertSame($objective->id, data_get($conversation->fresh()->metadata, 'active_ai_objective_id'));
        $this->assertSame(
            $response->json('data.assistant_message_id'),
            AiRun::query()->findOrFail($runId)->message_id,
        );
    }

    public function test_duplicate_client_message_delivery_reuses_the_same_messages_and_run(): void
    {
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()
            ->where('email', 'owner@humoo.local')
            ->firstOrFail();
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'title' => 'Idempotent chat',
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        ConversationParticipant::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);
        $payload = [
            'client_message_id' => 'duplicate-delivery-001',
            'content' => 'List the active recipes.',
            'conversation_id' => $conversation->id,
            'locale' => 'en',
        ];
        $token = $this->login('owner@humoo.local', 'password');

        $first = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/chat/messages', $payload)
            ->assertAccepted();
        $second = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/chat/messages', $payload)
            ->assertAccepted();

        $this->assertSame($first->json('data.user_message.id'), $second->json('data.user_message.id'));
        $this->assertSame($first->json('data.assistant_message_id'), $second->json('data.assistant_message_id'));
        $this->assertSame($first->json('data.ai_run_id'), $second->json('data.ai_run_id'));
        $this->assertSame(1, Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('client_message_id', 'duplicate-delivery-001')
            ->count());
        $this->assertSame(1, AiRun::query()
            ->where('conversation_id', $conversation->id)
            ->where('input_message_id', $first->json('data.user_message.id'))
            ->count());
        Queue::assertPushed(ProcessChatMessage::class, 1);
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-chat-conversation',
        ])->assertOk()->json('token');
    }
}
