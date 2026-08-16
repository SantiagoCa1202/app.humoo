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

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_members_endpoints_require_members_view_permission(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $ownerToken = $this->login('owner@humoo.local', 'password');

        $ownerRoles = $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/workspaces/current/roles');

        $ownerRoles
            ->assertOk()
            ->assertJsonFragment([
                'key' => 'events.create',
            ]);

        $viewer = User::query()->create([
            'name' => 'Viewer User',
            'email' => 'viewer@humoo.local',
            'password' => Hash::make('password'),
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $viewerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'viewer')
            ->firstOrFail();

        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role_id' => $viewerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->assertFalse(
            $viewer->hasWorkspacePermission($workspace->id, 'members.view')
        );

        $viewerToken = $this->login('viewer@humoo.local', 'password');

        $this->app['auth']->forgetGuards();

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/workspaces/current/roles')
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/workspaces/current/members')
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
