<?php

namespace Tests\Feature\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_crud_events_with_real_directory_relationships(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = $this->workspace();
        $owner = $this->owner();
        $token = $this->login('owner@humoo.local', 'password');
        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Catering Rivera',
            'company_name' => 'Rivera Group',
            'email' => 'events@rivera.test',
            'phone' => '555-1111',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $contact = Contact::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'first_name' => 'Lucia',
            'last_name' => 'Rivera',
            'display_name' => 'Lucia Rivera',
            'email' => 'lucia@rivera.test',
            'phone' => '555-1112',
            'job_title' => 'Planner',
            'contact_type' => 'lead',
            'is_primary' => true,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $venue = Venue::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Gran Salon',
            'city' => 'New York',
            'state' => 'NY',
            'timezone' => 'America/New_York',
            'contact_name' => 'Venue Lead',
            'contact_email' => 'venue@gran-salon.test',
            'contact_phone' => '555-1113',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $createResponse = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/events', [
                'name' => 'Boda Martinez',
                'client_id' => $client->id,
                'contact_id' => $contact->id,
                'venue_id' => $venue->id,
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
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.contact.id', $contact->id)
            ->assertJsonPath('data.venue.id', $venue->id)
            ->assertJsonPath('data.name', 'Boda Martinez')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.version', 1);

        $eventId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('events', [
            'id' => $eventId,
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'contact_id' => $contact->id,
            'venue_id' => $venue->id,
            'client_name_snapshot' => 'Catering Rivera',
            'contact_name_snapshot' => 'Lucia Rivera',
            'venue_name_snapshot' => 'Gran Salon',
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
            ->getJson("/api/v1/events?search=Martinez&client_id={$client->id}&venue_id={$venue->id}&status=confirmed")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $eventId,
                'name' => 'Boda Martinez',
            ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/events/{$eventId}")
            ->assertOk()
            ->assertJsonPath('data.contact.email', 'lucia@rivera.test')
            ->assertJsonPath('data.venue.city', 'New York');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/events/{$eventId}", [
                'version' => 1,
                'guest_count_expected' => 120,
                'name' => 'Boda Rivera Actualizada',
                'status' => 'in_production',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Boda Rivera Actualizada')
            ->assertJsonPath('data.status', 'in_production')
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'event.updated',
            'entity_type' => 'App\\Models\\Event',
            'entity_id' => $eventId,
        ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/events/{$eventId}")
            ->assertNoContent();

        $this->assertSoftDeleted('events', [
            'id' => $eventId,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $workspace->id,
            'action' => 'event.deleted',
            'entity_type' => 'App\\Models\\Event',
            'entity_id' => $eventId,
        ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonFragment([
                'action' => 'event.created',
                'entity_id' => $eventId,
            ])
            ->assertJsonFragment([
                'action' => 'event.updated',
                'entity_id' => $eventId,
            ])
            ->assertJsonFragment([
                'action' => 'event.deleted',
                'entity_id' => $eventId,
            ]);
    }

    public function test_events_enforce_workspace_isolation_validation_and_optimistic_locking(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = $this->workspace();
        $owner = $this->owner();
        $token = $this->login('owner@humoo.local', 'password');
        $secondaryWorkspace = $this->attachOwnerToSecondaryWorkspace($owner);
        $foreignClient = Client::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'name' => 'Foreign Client',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $foreignEvent = Event::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'client_id' => $foreignClient->id,
            'name' => 'Foreign Event',
            'starts_at' => now()->addDays(2),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'version' => 1,
        ]);
        $event = Event::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Lockable Event',
            'starts_at' => now()->addDay(),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'version' => 1,
        ]);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/events', [
                'name' => 'Cross Workspace Client',
                'client_id' => $foreignClient->id,
                'starts_at' => now()->addDays(3)->toIso8601String(),
                'timezone' => 'America/New_York',
                'status' => 'draft',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('client_id');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/events/{$event->id}", [
                'version' => 1,
                'name' => 'Fresh update',
            ])
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/events/{$event->id}", [
                'version' => 1,
                'name' => 'Stale update',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT')
            ->assertJsonPath('data.version', 2);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/events/{$foreignEvent->id}")
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/events/{$foreignEvent->id}", [
                'version' => 1,
                'name' => 'Blocked',
            ])
            ->assertNotFound();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/events/{$foreignEvent->id}")
            ->assertNotFound();

        $viewer = $this->createWorkspaceUser(
            email: 'events-viewer@humoo.local',
            roleKey: 'viewer',
            workspace: $workspace
        );
        $viewerToken = $this->login($viewer->email, 'password');
        $this->app['auth']->forgetGuards();

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $event->id,
                'name' => 'Fresh update',
            ]);

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/events', [
                'name' => 'Viewer blocked event',
                'starts_at' => now()->addWeek()->toIso8601String(),
                'timezone' => 'America/New_York',
                'status' => 'draft',
            ])
            ->assertForbidden();
    }

    private function workspace(): Workspace
    {
        return Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
    }

    private function owner(): User
    {
        return User::query()
            ->where('email', 'owner@humoo.local')
            ->firstOrFail();
    }

    private function login(string $email, string $password): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit-web',
        ])->assertOk()->json('token');
    }

    private function attachOwnerToSecondaryWorkspace(
        User $owner,
        string $slug = 'humoo-events-secondary'
    ): Workspace {
        $workspace = Workspace::query()->create([
            'name' => 'Humoo Events Secondary',
            'slug' => $slug,
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
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $workspace;
    }

    private function createWorkspaceUser(
        string $email,
        string $roleKey,
        Workspace $workspace
    ): User {
        $user = User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
            'locale' => 'en',
            'timezone' => 'America/New_York',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $role = Role::query()
            ->whereNull('workspace_id')
            ->where('key', $roleKey)
            ->firstOrFail();

        WorkspaceMembership::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }
}
