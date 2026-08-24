<?php

namespace Tests\Feature\Feature;

use App\Application\Actions\Chat\RecordConversationEntityRefs;
use App\Models\Conversation;
use App\Models\ConversationEntityRef;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationEntityRefTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_menu_reference_is_persisted_and_replaced_safely(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        $action = app(RecordConversationEntityRefs::class);
        $action->execute($conversation, $workspace, [[
            'id' => '01menu00000000000000000001',
            'role' => 'active',
            'snapshot' => ['name' => 'Down South Boulevard'],
            'type' => 'menu',
        ]]);
        $action->execute($conversation, $workspace, [[
            'id' => '01menu00000000000000000002',
            'role' => 'active',
            'snapshot' => ['name' => 'Summer Dinner'],
            'type' => 'menu',
        ]]);

        $this->assertDatabaseHas('conversation_entity_refs', [
            'conversation_id' => $conversation->id,
            'entity_id' => '01menu00000000000000000001',
            'role' => 'recent',
        ]);
        $this->assertDatabaseHas('conversation_entity_refs', [
            'conversation_id' => $conversation->id,
            'entity_id' => '01menu00000000000000000002',
            'role' => 'active',
        ]);
        $this->assertSame(2, ConversationEntityRef::query()
            ->where('conversation_id', $conversation->id)
            ->count());
    }

    public function test_entity_refs_do_not_cross_conversations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $first = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);
        $second = Conversation::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'scope_type' => 'general',
            'visibility' => 'private',
            'status' => 'active',
        ]);

        app(RecordConversationEntityRefs::class)->execute($first, $workspace, [[
            'id' => '01menu00000000000000000003',
            'role' => 'active',
            'snapshot' => ['name' => 'Private Menu'],
            'type' => 'menu',
        ]]);

        $this->assertDatabaseHas('conversation_entity_refs', [
            'conversation_id' => $first->id,
            'entity_id' => '01menu00000000000000000003',
        ]);
        $this->assertDatabaseMissing('conversation_entity_refs', [
            'conversation_id' => $second->id,
            'entity_id' => '01menu00000000000000000003',
        ]);
    }
}
