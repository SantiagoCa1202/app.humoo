<?php

namespace Tests\Feature\Feature;

use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_events_and_audit_log_is_written(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();

        $token = $this->login('owner@humoo.local', 'password');

        $createResponse = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/events', [
                'name' => 'Boda Martinez',
                'starts_at' => now()->addWeek()->toIso8601String(),
                'ends_at' => now()->addWeek()->addHours(6)->toIso8601String(),
                'timezone' => 'America/New_York',
                'guest_count_expected' => 80,
                'service_type' => 'buffet',
                'event_type' => 'wedding',
                'status' => 'confirmed',
                'priority' => 'high',
                'notes' => 'Seeded from phpunit.',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Boda Martinez')
            ->assertJsonPath('data.status', 'confirmed');

        $eventId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'workspace_id' => $workspace->id,
            'name' => 'Boda Martinez',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'event.created',
            'entity_type' => 'App\\Models\\Event',
            'entity_id' => $eventId,
        ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $eventId,
                'name' => 'Boda Martinez',
            ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonFragment([
                'action' => 'event.created',
                'entity_id' => $eventId,
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
