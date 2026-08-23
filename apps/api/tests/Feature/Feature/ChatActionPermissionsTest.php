<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\ConversationParticipant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatActionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_participant_can_be_created_with_workspace_scope(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversationId = $this->createConversation($workspace->id, $user->id);

        $participant = ConversationParticipant::query()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversationId,
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->assertNotEmpty($participant->id);
        $this->assertSame($workspace->id, $participant->workspace_id);
        $this->assertSame($conversationId, $participant->conversation_id);
    }

    public function test_client_message_ids_are_unique_inside_a_conversation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();

        $conversationId = $this->createConversation($workspace->id, $user->id);

        DB::table('messages')->insert($this->messagePayload(
            $workspace->id,
            $conversationId,
            $user->id,
            'mobile-001',
        ));

        $this->expectException(QueryException::class);

        DB::table('messages')->insert($this->messagePayload(
            $workspace->id,
            $conversationId,
            $user->id,
            'mobile-001',
        ));
    }

    public function test_client_message_ids_can_be_reused_in_other_conversations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();

        $conversationA = $this->createConversation($workspace->id, $user->id);
        $conversationB = $this->createConversation($workspace->id, $user->id);

        DB::table('messages')->insert($this->messagePayload(
            $workspace->id,
            $conversationA,
            $user->id,
            'mobile-001',
        ));

        DB::table('messages')->insert($this->messagePayload(
            $workspace->id,
            $conversationB,
            $user->id,
            'mobile-001',
        ));

        $this->assertDatabaseCount('messages', 2);
    }

    private function createConversation(string $workspaceId, string $userId): string
    {
        $conversationId = (string) Str::ulid();

        DB::table('conversations')->insert([
            'id' => $conversationId,
            'workspace_id' => $workspaceId,
            'created_by' => $userId,
            'title' => 'Kitchen coordination',
            'visibility' => 'workspace',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
    }

    private function messagePayload(
        string $workspaceId,
        string $conversationId,
        string $userId,
        string $clientMessageId
    ): array {
        return [
            'id' => (string) Str::ulid(),
            'workspace_id' => $workspaceId,
            'conversation_id' => $conversationId,
            'sender_type' => 'user',
            'sender_id' => $userId,
            'status' => 'completed',
            'content_text' => 'Need 20 extra portions.',
            'client_message_id' => $clientMessageId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
