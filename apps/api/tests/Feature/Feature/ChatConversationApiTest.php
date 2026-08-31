<?php

namespace Tests\Feature\Feature;

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

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-chat-conversation',
        ])->assertOk()->json('token');
    }
}
