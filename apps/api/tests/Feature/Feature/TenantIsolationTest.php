<?php

namespace Tests\Feature\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_are_isolated_by_selected_workspace(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $primaryWorkspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $secondaryWorkspace = Workspace::query()->create([
            'name' => 'Humoo Second Kitchen',
            'slug' => 'humoo-second-kitchen',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $ownerRole = Role::query()
            ->whereNull('workspace_id')
            ->where('key', 'owner')
            ->firstOrFail();

        WorkspaceMembership::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'user_id' => $user->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $primaryEvent = Event::query()->create([
            'workspace_id' => $primaryWorkspace->id,
            'name' => 'Primary Event',
            'starts_at' => now()->addDay(),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'version' => 1,
        ]);

        $secondaryEvent = Event::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'name' => 'Secondary Event',
            'starts_at' => now()->addDays(2),
            'timezone' => 'America/New_York',
            'status' => 'draft',
            'priority' => 'high',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'version' => 1,
        ]);

        $token = $this->login('owner@humoo.local', 'password');

        $this->withToken($token)
            ->getJson('/api/v1/events')
            ->assertStatus(400);

        $primaryList = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $primaryWorkspace->id)
            ->getJson('/api/v1/events');

        $primaryList
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $primaryEvent->id,
                'name' => 'Primary Event',
            ])
            ->assertJsonMissing([
                'id' => $secondaryEvent->id,
            ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $primaryWorkspace->id)
            ->getJson("/api/v1/events/{$secondaryEvent->id}")
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $secondaryWorkspace->id)
            ->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $secondaryEvent->id,
                'name' => 'Secondary Event',
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
