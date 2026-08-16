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

class InvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_user_and_register_flow_accepts_invitation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $adminRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'admin')
            ->firstOrFail();

        $ownerToken = $this->login('owner@humoo.local', 'password');

        $inviteResponse = $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/workspaces/current/invitations', [
                'email' => 'new-admin@humoo.local',
                'role_id' => $adminRole->id,
            ]);

        $inviteResponse
            ->assertCreated()
            ->assertJsonPath('data.email', 'new-admin@humoo.local')
            ->assertJsonPath('data.role.id', $adminRole->id);

        $invitationToken = (string) $inviteResponse->json('meta.invitation_token_preview');

        $this->getJson("/api/v1/invitations/{$invitationToken}")
            ->assertOk()
            ->assertJsonPath('data.workspace.id', $workspace->id)
            ->assertJsonPath('data.role.id', $adminRole->id);

        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'New',
            'last_name' => 'Admin',
            'email' => 'new-admin@humoo.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'device_name' => 'humoo-expo-web',
            'invitation_token' => $invitationToken,
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('user.email', 'new-admin@humoo.local');

        $token = (string) $registerResponse->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.current_workspace.id', $workspace->id)
            ->assertJsonFragment([
                'key' => 'members.manage',
            ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/workspaces/current/roles')
            ->assertOk();

        $this->assertDatabaseHas('workspace_memberships', [
            'workspace_id' => $workspace->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
    }

    public function test_existing_authenticated_user_can_accept_invitation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $ownerToken = $this->login('owner@humoo.local', 'password');
        $viewerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'viewer')
            ->firstOrFail();

        $existingUser = User::query()->create([
            'name' => 'Existing Invitee',
            'email' => 'existing@humoo.local',
            'password' => Hash::make('secret123'),
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $inviteResponse = $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/workspaces/current/invitations', [
                'email' => $existingUser->email,
                'role_id' => $viewerRole->id,
            ])
            ->assertCreated();

        $invitationToken = (string) $inviteResponse->json('meta.invitation_token_preview');
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $existingUser->email,
            'password' => 'secret123',
            'device_name' => 'phpunit-existing-web',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.email', $existingUser->email);

        $userToken = (string) $loginResponse->json('token');

        $this->app['auth']->forgetGuards();

        $this->withToken($userToken)
            ->withoutHeader('X-Workspace-ID')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', $existingUser->email)
            ->assertJsonPath('data.current_workspace', null);

        $this->app['auth']->forgetGuards();

        $this->withToken($userToken)
            ->withoutHeader('X-Workspace-ID')
            ->postJson('/api/v1/invitations/accept', [
                'token' => $invitationToken,
            ])
            ->assertOk()
            ->assertJsonPath('data.membership.workspace_id', $workspace->id)
            ->assertJsonPath('data.membership.role_id', $viewerRole->id);

        $this->withToken($userToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.current_workspace.id', $workspace->id);
    }

    public function test_owner_can_change_member_role_and_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $viewerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'viewer')
            ->firstOrFail();

        $chefRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'chef')
            ->firstOrFail();

        $member = User::query()->create([
            'name' => 'Kitchen Viewer',
            'email' => 'viewer-member@humoo.local',
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
            ->patchJson("/api/v1/workspaces/current/members/{$membership->id}", [
                'role_id' => $chefRole->id,
                'status' => 'suspended',
            ])
            ->assertOk()
            ->assertJsonPath('data.role_id', $chefRole->id)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('workspace_memberships', [
            'id' => $membership->id,
            'role_id' => $chefRole->id,
            'status' => 'suspended',
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
}
