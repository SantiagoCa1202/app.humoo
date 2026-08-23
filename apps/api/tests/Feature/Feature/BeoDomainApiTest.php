<?php

namespace Tests\Feature\Feature;

use App\Models\Beo;
use App\Models\Event;
use App\Models\Property;
use App\Models\User;
use App\Models\Venue;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeoDomainApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_batch_preserves_multiple_orders_revisions_functions_and_source_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $property = Property::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Sheraton Charlotte',
            'created_by' => $owner->id,
        ]);
        $venue = Venue::query()->create([
            'workspace_id' => $workspace->id,
            'property_id' => $property->id,
            'name' => 'Symphony 1',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $event = Event::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Canonical event remains untouched',
            'status' => 'draft',
            'priority' => 'normal',
            'version' => 1,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $token = $this->login('owner@humoo.local', 'password');

        $response = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/beo-import-batches', [
                'original_filename' => '8.23.26 - 8.30.26 EOs.pdf',
                'source_system' => 'cvent',
                'property_id' => $property->id,
                'event_orders' => [
                    [
                        'event_order_number' => '688857',
                        'event_id' => $event->id,
                        'versions' => [
                            [
                                'revision_label' => 'ORIGINAL',
                                'revision_type' => 'original',
                                'functions' => [[
                                    'source_function_key' => 'f-1',
                                    'source_function_name' => 'Office',
                                    'operational_category' => 'OFFICE',
                                    'source_location_text' => 'Symphony 1,2,3 & 4',
                                    'expected_count' => 150,
                                    'guaranteed_count' => 140,
                                    'set_count' => 200,
                                    'menu_status' => 'tbd',
                                    'operational_signals' => ['has_food' => false, 'has_beverage' => false],
                                    'venue_ids' => [$venue->id],
                                    'dietary_requirements' => [[
                                        'guest_name' => 'Alex',
                                        'raw_restriction' => 'No Pork',
                                        'category' => 'RELIGIOUS',
                                    ]],
                                    'instructions' => [[
                                        'category' => 'setup',
                                        'raw_text' => 'Flip room after dinner',
                                    ]],
                                ]],
                                'references' => [[
                                    'target_event_order_number' => '278135',
                                    'reference_type' => 'related_order',
                                    'raw_text' => 'Cash Bar on EO 278135',
                                    'source_event_function_key' => 'f-1',
                                ]],
                            ],
                            [
                                'revision_number' => 2,
                                'revision_label' => 'REVISED',
                                'revision_type' => 'revision',
                                'functions' => [[
                                    'source_function_name' => 'Coffee Break',
                                    'operational_category' => 'FOOD_SERVICE',
                                    'operational_signals' => ['has_food' => true, 'has_beverage' => true],
                                    'menu_status' => 'partial',
                                ]],
                            ],
                        ],
                    ],
                    [
                        'event_order_number' => '278135',
                        'versions' => [[
                            'revision_type' => 'original',
                            'functions' => [[
                                'source_function_name' => 'Reception',
                                'operational_category' => 'FOOD_SERVICE',
                                'operational_signals' => ['has_food' => true],
                            ]],
                        ]],
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.original_filename', '8.23.26 - 8.30.26 EOs.pdf')
            ->assertJsonCount(2, 'data.event_orders')
            ->assertJsonPath('data.event_orders.0.event_order_number', '688857');

        $order = Beo::query()->where('event_order_number', '688857')->firstOrFail();

        $this->assertDatabaseCount('beo_versions', 3);
        $this->assertDatabaseCount('event_functions', 3);
        $this->assertDatabaseHas('event_functions', [
            'source_location_text' => 'Symphony 1,2,3 & 4',
            'expected_count' => 150,
            'guaranteed_count' => 140,
            'set_count' => 200,
        ]);
        $this->assertDatabaseHas('event_order_references', [
            'source_beo_id' => $order->id,
            'target_event_order_number' => '278135',
        ]);
        $this->assertSame('Canonical event remains untouched', $event->fresh()->name);
        $this->assertNull($event->fresh()->description);
    }

    public function test_visibility_defaults_hide_functions_without_fnb_and_membership_can_show_all(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/beo-import-batches', [
                'original_filename' => 'office.pdf',
                'event_orders' => [[
                    'event_order_number' => '100001',
                    'versions' => [[
                        'functions' => [[
                            'source_function_name' => 'Office',
                            'operational_category' => 'OFFICE',
                            'operational_signals' => ['has_food' => false, 'has_beverage' => false],
                        ]],
                    ]],
                ]],
            ])->assertCreated();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/event-functions')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.hidden_count', 1);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson('/api/v1/operational-visibility', [
                'scope' => 'membership',
                'settings' => ['show_all' => true],
            ])
            ->assertOk();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->getJson('/api/v1/event-functions')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_cross_tenant_venue_cannot_be_associated_with_imported_function(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $otherWorkspace = Workspace::query()->create([
            'name' => 'Other Kitchen',
            'slug' => 'other-kitchen',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $otherVenue = Venue::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Other Room',
        ]);
        $token = $this->login('owner@humoo.local', 'password');

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/beo-import-batches', [
                'original_filename' => 'cross-tenant.pdf',
                'event_orders' => [[
                    'event_order_number' => '200001',
                    'versions' => [[
                        'functions' => [[
                            'source_function_name' => 'Dinner',
                            'venue_ids' => [$otherVenue->id],
                        ]],
                    ]],
                ]],
            ])
            ->assertStatus(422);
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
