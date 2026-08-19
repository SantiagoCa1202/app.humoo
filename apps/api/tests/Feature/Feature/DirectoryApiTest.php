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

class DirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_crud_validation_permissions_and_tenant_isolation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = $this->workspace();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $ownerToken = $this->login('owner@humoo.local', 'password');

        $this->getJson('/api/v1/clients')->assertUnauthorized();

        $createResponse = $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/clients', [
                'name' => '  Catering Martinez  ',
                'company_name' => ' Martinez Group ',
                'email' => 'Sales@Martinez.test ',
                'phone' => '555-1000',
                'country_code' => 'mx',
                'notes' => '  Cliente prioritario  ',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Catering Martinez')
            ->assertJsonPath('data.email', 'sales@martinez.test')
            ->assertJsonPath('data.country_code', 'MX')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.notes', 'Cliente prioritario');

        $clientId = (string) $createResponse->json('data.id');

        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'workspace_id' => $workspace->id,
            'name' => 'Catering Martinez',
            'email' => 'sales@martinez.test',
            'country_code' => 'MX',
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/clients?search=Martinez')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $clientId,
                'name' => 'Catering Martinez',
            ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/clients/{$clientId}")
            ->assertOk()
            ->assertJsonPath('data.contacts_count', 0);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/clients/{$clientId}", [
                'company_name' => 'Martinez Catering Group',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Martinez Catering Group')
            ->assertJsonPath('data.status', 'inactive');

        $relatedContact = Contact::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $clientId,
            'first_name' => 'Lucia',
            'last_name' => 'Martinez',
            'display_name' => 'Lucia Martinez',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/clients/{$clientId}")
            ->assertStatus(409)
            ->assertJsonPath('data.contacts_count', 1)
            ->assertJsonPath('data.events_count', 0);

        $deletableClient = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Disposable Client',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/clients/{$deletableClient->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($deletableClient);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/clients', [
                'email' => 'invalid-email',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
            ]);

        $secondaryWorkspace = $this->attachOwnerToSecondaryWorkspace($owner);

        $foreignClient = Client::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'name' => 'Foreign Client',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/clients/{$foreignClient->id}")
            ->assertNotFound();

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/clients/{$foreignClient->id}", [
                'name' => 'Should not update',
            ])
            ->assertNotFound();

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/clients/{$foreignClient->id}")
            ->assertNotFound();

        $viewer = $this->createWorkspaceUser(
            email: 'clients-viewer@humoo.local',
            roleKey: 'viewer',
            workspace: $workspace
        );
        $viewerToken = $this->login($viewer->email, 'password');
        $this->app['auth']->forgetGuards();

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $clientId,
                'name' => 'Catering Martinez',
            ]);

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/clients', [
                'name' => 'Viewer blocked client',
            ])
            ->assertForbidden();

        $relatedContact->delete();
    }

    public function test_contacts_crud_validation_permissions_and_tenant_isolation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = $this->workspace();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $ownerToken = $this->login('owner@humoo.local', 'password');

        $this->getJson('/api/v1/contacts')->assertUnauthorized();

        $client = Client::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Contact Client',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $firstContactResponse = $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/contacts', [
                'client_id' => $client->id,
                'first_name' => '  Ana ',
                'last_name' => ' Rivera  ',
                'email' => 'Ana.Rivera@Test.local ',
                'phone' => '555-2000',
                'job_title' => ' Planner ',
                'contact_type' => 'lead',
                'is_primary' => true,
            ]);

        $firstContactResponse
            ->assertCreated()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.first_name', 'Ana')
            ->assertJsonPath('data.last_name', 'Rivera')
            ->assertJsonPath('data.full_name', 'Ana Rivera')
            ->assertJsonPath('data.email', 'ana.rivera@test.local')
            ->assertJsonPath('data.is_primary', true);

        $firstContactId = (string) $firstContactResponse->json('data.id');

        $secondContactResponse = $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/contacts', [
                'client_id' => $client->id,
                'first_name' => 'Luis',
                'last_name' => 'Rivera',
                'display_name' => 'Luis Rivera',
                'email' => 'luis@test.local',
                'is_primary' => true,
            ]);

        $secondContactResponse
            ->assertCreated()
            ->assertJsonPath('data.display_name', 'Luis Rivera')
            ->assertJsonPath('data.is_primary', true);

        $secondContactId = (string) $secondContactResponse->json('data.id');

        $this->assertDatabaseHas('contacts', [
            'id' => $firstContactId,
            'is_primary' => false,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/contacts?client_id={$client->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $firstContactId,
                'client_id' => $client->id,
            ])
            ->assertJsonFragment([
                'id' => $secondContactId,
                'client_id' => $client->id,
            ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/contacts/{$secondContactId}")
            ->assertOk()
            ->assertJsonPath('data.client.id', $client->id);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/contacts/{$secondContactId}", [
                'phone' => '555-3000',
                'job_title' => 'Operations Lead',
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', '555-3000')
            ->assertJsonPath('data.job_title', 'Operations Lead');

        $secondaryWorkspace = $this->attachOwnerToSecondaryWorkspace($owner, 'directory-secondary-kitchen');

        $foreignClient = Client::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'name' => 'Foreign Contact Client',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/contacts', [
                'client_id' => $foreignClient->id,
                'first_name' => 'Cross',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('client_id');

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/contacts', [
                'email' => 'bad-email',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name',
                'email',
            ]);

        $foreignContact = Contact::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'client_id' => $foreignClient->id,
            'first_name' => 'Foreign',
            'last_name' => 'Contact',
            'display_name' => 'Foreign Contact',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/contacts/{$foreignContact->id}")
            ->assertNotFound();

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/contacts/{$foreignContact->id}", [
                'first_name' => 'Blocked',
            ])
            ->assertNotFound();

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/contacts/{$foreignContact->id}")
            ->assertNotFound();

        $contactWithEvent = Contact::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'first_name' => 'Booked',
            'display_name' => 'Booked Contact',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Event::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'contact_id' => $contactWithEvent->id,
            'name' => 'Protected Contact Event',
            'starts_at' => now()->addDay(),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'normal',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'version' => 1,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/contacts/{$contactWithEvent->id}")
            ->assertStatus(409)
            ->assertJsonPath('data.events_count', 1);

        $deletableContact = Contact::query()->create([
            'workspace_id' => $workspace->id,
            'client_id' => $client->id,
            'first_name' => 'Delete',
            'display_name' => 'Delete Contact',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/contacts/{$deletableContact->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($deletableContact);

        $viewer = $this->createWorkspaceUser(
            email: 'contacts-viewer@humoo.local',
            roleKey: 'viewer',
            workspace: $workspace
        );
        $viewerToken = $this->login($viewer->email, 'password');
        $this->app['auth']->forgetGuards();

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $secondContactId,
                'display_name' => 'Luis Rivera',
            ]);

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/contacts', [
                'first_name' => 'Viewer Blocked',
            ])
            ->assertForbidden();
    }

    public function test_venues_crud_validation_permissions_and_tenant_isolation(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = $this->workspace();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $ownerToken = $this->login('owner@humoo.local', 'password');

        $this->getJson('/api/v1/venues')->assertUnauthorized();

        $createResponse = $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/venues', [
                'name' => '  Salon Central ',
                'city' => ' Mexico City ',
                'state' => 'CDMX',
                'country_code' => 'mx',
                'timezone' => 'America/Mexico_City',
                'contact_name' => ' Venue Lead ',
                'contact_email' => 'Lead@Venue.test ',
                'contact_phone' => '555-4000',
                'capacity' => 120,
                'notes' => '  Main hall  ',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Salon Central')
            ->assertJsonPath('data.city', 'Mexico City')
            ->assertJsonPath('data.country_code', 'MX')
            ->assertJsonPath('data.contact_email', 'lead@venue.test')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.notes', 'Main hall');

        $venueId = (string) $createResponse->json('data.id');

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/venues?search=Salon&status=active')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $venueId,
                'name' => 'Salon Central',
            ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/venues/{$venueId}")
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/Mexico_City');

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/venues/{$venueId}", [
                'status' => 'inactive',
                'parking_notes' => 'Rear lot only',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.parking_notes', 'Rear lot only');

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/venues', [
                'name' => 'Invalid Venue',
                'timezone' => 'Not/AZone',
                'contact_email' => 'not-an-email',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'timezone',
                'contact_email',
            ]);

        $secondaryWorkspace = $this->attachOwnerToSecondaryWorkspace($owner, 'venues-secondary-kitchen');

        $foreignVenue = Venue::query()->create([
            'workspace_id' => $secondaryWorkspace->id,
            'name' => 'Foreign Venue',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson("/api/v1/venues/{$foreignVenue->id}")
            ->assertNotFound();

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/venues/{$foreignVenue->id}", [
                'name' => 'Blocked Venue',
            ])
            ->assertNotFound();

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/venues/{$foreignVenue->id}")
            ->assertNotFound();

        $protectedVenue = Venue::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Protected Venue',
            'timezone' => 'America/New_York',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Event::query()->create([
            'workspace_id' => $workspace->id,
            'venue_id' => $protectedVenue->id,
            'name' => 'Protected Venue Event',
            'starts_at' => now()->addDays(2),
            'timezone' => 'America/New_York',
            'status' => 'confirmed',
            'priority' => 'high',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'version' => 1,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/venues/{$protectedVenue->id}")
            ->assertStatus(409)
            ->assertJsonPath('data.events_count', 1);

        $deletableVenue = Venue::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Disposable Venue',
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->withToken($ownerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->deleteJson("/api/v1/venues/{$deletableVenue->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($deletableVenue);

        $viewer = $this->createWorkspaceUser(
            email: 'venues-viewer@humoo.local',
            roleKey: 'viewer',
            workspace: $workspace
        );
        $viewerToken = $this->login($viewer->email, 'password');
        $this->app['auth']->forgetGuards();

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/venues')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $venueId,
                'name' => 'Salon Central',
            ]);

        $this->withToken($viewerToken)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/venues', [
                'name' => 'Viewer Blocked Venue',
            ])
            ->assertForbidden();
    }

    private function workspace(): Workspace
    {
        return Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
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
        string $slug = 'humoo-directory-secondary'
    ): Workspace {
        $workspace = Workspace::query()->create([
            'name' => 'Humoo Directory Secondary',
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
