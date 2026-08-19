<?php

namespace Tests\Feature\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_workspace_and_receives_owner_membership(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->create([
            'name' => 'Workspace Creator',
            'email' => 'creator@humoo.local',
            'password' => Hash::make('secret123'),
            'locale' => 'es',
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $token = $this->login($user->email, 'secret123');

        $response = $this->withToken($token)->postJson('/api/v1/workspaces', [
            'name' => 'Kitchen Norte',
            'default_locale' => 'es',
            'timezone' => 'America/Mexico_City',
            'currency' => 'mxn',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.workspace.name', 'Kitchen Norte')
            ->assertJsonPath('data.workspace.currency', 'MXN')
            ->assertJsonPath('data.role.key', 'owner');

        $workspaceId = (string) $response->json('data.workspace.id');

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspaceId,
            'name' => 'Kitchen Norte',
            'currency' => 'MXN',
        ]);

        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspaceId)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.current_workspace.id', $workspaceId)
            ->assertJsonFragment([
                'key' => 'members.manage',
            ]);
    }

    public function test_workspaces_index_lists_only_the_authenticated_users_memberships(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $ownerToken = $this->login('owner@humoo.local', 'password');

        $ownerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'owner')
            ->firstOrFail();

        $secondaryWorkspace = Workspace::query()->create([
            'name' => 'Owner Second Kitchen',
            'slug' => 'owner-second-kitchen',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        WorkspaceMembership::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'other@humoo.local',
            'password' => Hash::make('secret123'),
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $foreignWorkspace = Workspace::query()->create([
            'name' => 'Foreign Kitchen',
            'slug' => 'foreign-kitchen',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        WorkspaceMembership::query()->create([
            'workspace_id' => $foreignWorkspace->id,
            'user_id' => $otherUser->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->withToken($ownerToken)
            ->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $secondaryWorkspace->id,
                'name' => 'Owner Second Kitchen',
            ])
            ->assertJsonMissing([
                'id' => $foreignWorkspace->id,
                'name' => 'Foreign Kitchen',
            ]);
    }

    public function test_user_cannot_select_a_foreign_workspace_by_manually_sending_the_header(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $ownerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'owner')
            ->firstOrFail();

        $foreignWorkspace = Workspace::query()->create([
            'name' => 'Foreign Kitchen',
            'slug' => 'foreign-owner-kitchen',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $otherUser = User::query()->create([
            'name' => 'Foreign Owner',
            'email' => 'foreign-owner@humoo.local',
            'password' => Hash::make('secret123'),
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        WorkspaceMembership::query()->create([
            'workspace_id' => $foreignWorkspace->id,
            'user_id' => $otherUser->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $this->login($owner->email, 'password');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $foreignWorkspace->id)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }

    public function test_owner_can_remove_member_and_the_removed_member_loses_workspace_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $viewerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'viewer')
            ->firstOrFail();

        $member = User::query()->create([
            'name' => 'Member To Remove',
            'email' => 'remove-me@humoo.local',
            'password' => Hash::make('secret123'),
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $membership = WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role_id' => $viewerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $ownerToken = $this->login('owner@humoo.local', 'password');

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/workspaces/current/members/{$membership->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('workspace_memberships', [
            'id' => $membership->id,
            'status' => 'removed',
        ]);

        $memberToken = $this->login($member->email, 'secret123');
        $this->app['auth']->forgetGuards();

        $this->withToken($memberToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/workspaces/current')
            ->assertForbidden();
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-web',
        ])->assertOk()->json('token');
    }
}
