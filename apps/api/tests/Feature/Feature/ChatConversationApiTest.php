<?php

namespace Tests\Feature\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-chat-conversation',
        ])->assertOk()->json('token');
    }
}
