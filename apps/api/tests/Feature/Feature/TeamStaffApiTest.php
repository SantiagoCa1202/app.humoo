<?php

namespace Tests\Feature\Feature;

use App\Models\Station;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamStaffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_user_can_create_team_and_assign_workspace_memberships(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');
        $member = $this->createWorkspaceMember($workspace);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/teams', [
                'name' => 'Prep Team',
                'description' => 'Prep and production support',
                'lead_membership_id' => $member->id,
                'member_ids' => [$member->id],
                'status' => 'active',
                'type' => 'production',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Prep Team')
            ->assertJsonPath('data.member_count', 1)
            ->assertJsonPath('data.members.0.id', $member->id)
            ->assertJsonPath('data.lead_membership_id', $member->id);

        $this->assertDatabaseHas('teams', [
            'workspace_id' => $workspace->id,
            'name' => 'Prep Team',
        ]);

        $this->assertDatabaseHas('team_members', [
            'workspace_id' => $workspace->id,
            'membership_id' => $member->id,
        ]);
    }

    public function test_workspace_user_can_sync_availability_and_receive_shift_conflicts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');
        $member = $this->createWorkspaceMember($workspace);
        $station = Station::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Cold Prep',
            'status' => 'active',
        ]);
        $team = Team::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Operations',
            'status' => 'active',
        ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->putJson("/api/v1/availability/{$member->id}", [
                'records' => [[
                    'starts_at' => '2026-08-21T13:00:00Z',
                    'ends_at' => '2026-08-21T16:00:00Z',
                    'timezone' => 'America/New_York',
                    'available' => false,
                    'type' => 'unavailable',
                ]],
                'rules' => [[
                    'day_of_week' => 5,
                    'starts_at' => '09:00',
                    'ends_at' => '17:00',
                    'timezone' => 'America/New_York',
                    'available' => true,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.records.0.type', 'unavailable');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/shifts', [
                'membership_id' => $member->id,
                'team_id' => $team->id,
                'station_id' => $station->id,
                'starts_at' => '2026-08-21T14:00:00Z',
                'ends_at' => '2026-08-21T18:00:00Z',
                'timezone' => 'America/New_York',
                'status' => 'scheduled',
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.conflicts')
            ->assertJsonPath('data.conflicts.0.type', 'unavailable');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/shifts', [
                'membership_id' => $member->id,
                'team_id' => $team->id,
                'station_id' => $station->id,
                'starts_at' => '2026-08-21T15:00:00Z',
                'ends_at' => '2026-08-21T19:00:00Z',
                'timezone' => 'America/New_York',
                'status' => 'scheduled',
            ])
            ->assertCreated()
            ->assertJsonPath('data.conflicts.0.type', 'overlap');
    }

    public function test_team_staff_endpoints_reject_cross_workspace_membership_ids(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');
        $foreignWorkspace = Workspace::query()->create([
            'name' => 'Foreign Workspace',
            'slug' => 'foreign-workspace',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $foreignMember = $this->createWorkspaceMember($foreignWorkspace);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/teams', [
                'name' => 'Cross Tenant Team',
                'member_ids' => [$foreignMember->id],
            ])
            ->assertStatus(422);
    }

    private function createWorkspaceMember(Workspace $workspace): WorkspaceMembership
    {
        $user = User::query()->create([
            'first_name' => 'Staff',
            'last_name' => Str::random(6),
            'name' => 'Staff '.Str::random(4),
            'email' => Str::lower(Str::random(8)).'@humoo.local',
            'preferred_locale' => 'en',
            'timezone' => 'America/New_York',
            'password' => bcrypt('password'),
        ]);

        return WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'status' => 'active',
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
