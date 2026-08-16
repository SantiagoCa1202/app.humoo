<?php

namespace Tests\Feature\Feature;

use App\Models\Event;
use App\Models\PrepItem;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrepItemOptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_prep_item_updates_increment_version_and_stale_updates_conflict(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $owner = $this->owner();
        $token = $this->login('owner@humoo.local', 'password');
        $prepItem = $this->createPrepItem($workspace->id, $owner->id);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/prep-items/{$prepItem->id}", [
                'version' => 1,
                'title' => 'Roasted carrots',
                'status' => 'in_progress',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Roasted carrots')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.status', 'in_progress');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/prep-items/{$prepItem->id}", [
                'version' => 1,
                'status' => 'done',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT')
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'prep_item.updated',
            'entity_type' => 'App\\Models\\PrepItem',
            'entity_id' => $prepItem->id,
        ]);
    }

    private function createPrepItem(string $workspaceId, string $userId): PrepItem
    {
        $event = Event::query()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Prep Lock Event',
            'starts_at' => now()->addDay(),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'created_by' => $userId,
            'updated_by' => $userId,
            'version' => 1,
        ]);

        $prepListId = (string) Str::ulid();
        $prepListVersionId = (string) Str::ulid();
        $prepSectionId = (string) Str::ulid();

        DB::table('prep_lists')->insert([
            'id' => $prepListId,
            'workspace_id' => $workspaceId,
            'event_id' => $event->id,
            'name' => 'Main prep list',
            'current_version' => 1,
            'status' => 'draft',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('prep_list_versions')->insert([
            'id' => $prepListVersionId,
            'workspace_id' => $workspaceId,
            'prep_list_id' => $prepListId,
            'version' => 1,
            'status' => 'approved',
            'source' => 'manual',
            'approved_at' => now(),
            'approved_by' => $userId,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('prep_sections')->insert([
            'id' => $prepSectionId,
            'workspace_id' => $workspaceId,
            'prep_list_version_id' => $prepListVersionId,
            'name' => 'Vegetable station',
            'type' => 'custom',
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PrepItem::query()->create([
            'workspace_id' => $workspaceId,
            'prep_section_id' => $prepSectionId,
            'title' => 'Roast carrots',
            'status' => 'todo',
            'source' => 'manual',
            'version' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-web',
        ])->assertOk()->json('token');
    }

    private function owner()
    {
        return \App\Models\User::query()
            ->where('email', 'owner@humoo.local')
            ->firstOrFail();
    }
}
