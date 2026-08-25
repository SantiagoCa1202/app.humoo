<?php

namespace Tests\Feature\Feature;

use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunctionalAcceptanceCoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_can_build_core_workflow_through_http_boundaries(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $registration = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Acceptance',
            'last_name' => 'Owner',
            'email' => 'acceptance-owner@humoo.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'device_name' => 'acceptance-suite',
            'locale' => 'en',
            'timezone' => 'America/New_York',
        ])->assertCreated();

        $token = (string) $registration->json('token');
        $workspace = $this->withToken($token)->postJson('/api/v1/workspaces', [
            'name' => 'Humoo Acceptance Kitchen',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'usd',
        ])->assertCreated();

        $workspaceId = (string) $workspace->json('data.workspace.id');
        $membershipId = (string) $workspace->json('data.id');
        $headers = fn () => $this->withToken($token)->withHeader('X-Workspace-ID', $workspaceId);

        $client = $headers()->postJson('/api/v1/clients', [
            'name' => 'Acme Corporate Events',
            'company_name' => 'Acme Corporate Events',
            'email' => 'events@acme.test',
            'status' => 'active',
        ])->assertCreated();
        $clientId = (string) $client->json('data.id');

        $contact = $headers()->postJson('/api/v1/contacts', [
            'client_id' => $clientId,
            'first_name' => 'Jordan',
            'last_name' => 'Smith',
            'display_name' => 'Jordan Smith',
            'email' => 'jordan.smith@acme.test',
            'contact_type' => 'planner',
            'is_primary' => true,
        ])->assertCreated();

        $venue = $headers()->postJson('/api/v1/venues', [
            'name' => 'Carolina Ballroom',
            'city' => 'Charlotte',
            'state' => 'NC',
            'timezone' => 'America/New_York',
            'capacity' => 500,
        ])->assertCreated();

        $event = $headers()->postJson('/api/v1/events', [
            'name' => 'Corporate Leadership Dinner',
            'client_id' => $clientId,
            'contact_id' => $contact->json('data.id'),
            'venue_id' => $venue->json('data.id'),
            'starts_at' => now()->addDays(7)->setTime(18, 0)->toIso8601String(),
            'ends_at' => now()->addDays(7)->setTime(22, 0)->toIso8601String(),
            'timezone' => 'America/New_York',
            'guest_count_expected' => 150,
            'status' => 'confirmed',
            'priority' => 'high',
        ])->assertCreated();
        $eventId = (string) $event->json('data.id');

        $recipes = collect([
            'Roasted Chicken Breast',
            'Herb Roasted Potatoes',
            'Mixed Greens Salad',
        ])->map(function (string $name) use ($headers): array {
            $response = $headers()->postJson('/api/v1/recipes', $this->recipePayload($name))
                ->assertCreated();

            return [
                'id' => (string) $response->json('data.id'),
                'version_id' => (string) $response->json('data.current_version_record.id'),
            ];
        })->values()->all();

        $menu = $headers()->postJson('/api/v1/menus', [
            'name' => 'Corporate Dinner Menu',
            'status' => 'active',
            'event_id' => $eventId,
            'sections' => [
                [
                    'name' => 'Salad',
                    'items' => [[
                        'name' => 'Mixed Greens Salad',
                        'recipe_id' => $recipes[2]['id'],
                        'recipe_version_id' => $recipes[2]['version_id'],
                    ]],
                ],
                [
                    'name' => 'Entree',
                    'items' => [
                        [
                            'name' => 'Roasted Chicken Breast',
                            'recipe_id' => $recipes[0]['id'],
                            'recipe_version_id' => $recipes[0]['version_id'],
                        ],
                        [
                            'name' => 'Herb Roasted Potatoes',
                            'recipe_id' => $recipes[1]['id'],
                            'recipe_version_id' => $recipes[1]['version_id'],
                        ],
                        [
                            'name' => 'Fresh Fruit',
                            'quantity_per_guest' => 0.5,
                            'serving_unit' => 'lb',
                        ],
                        [
                            'name' => 'Coffee Service',
                        ],
                    ],
                ],
            ],
        ])->assertCreated();

        $prepList = $headers()->postJson('/api/v1/prep-lists', [
            'name' => 'Corporate Leadership Dinner Prep',
            'event_id' => $eventId,
            'status' => 'draft',
        ])->assertCreated();
        $prepListId = (string) $prepList->json('data.id');

        $generation = $headers()->postJson("/api/v1/prep-lists/{$prepListId}/generate", [
            'guest_count' => 150,
            'assignment_membership_id' => $membershipId,
            'source' => 'manual',
        ])->assertCreated();

        $generation
            ->assertJsonPath('data.prep_list.event_id', $eventId)
            ->assertJsonPath('data.version.guest_count_snapshot', 150);
        $this->assertCount(4, $generation->json('data.items'));
        $this->assertJsonPath('data.items.3.title', 'Fresh Fruit');
        $this->assertJsonPath('data.items.3.quantity', 75);
        $this->assertJsonPath('data.items.3.unit_label', 'lb');

        $headers()->patchJson("/api/v1/events/{$eventId}", [
            'version' => 1,
            'guest_count_expected' => 175,
        ])->assertOk()->assertJsonPath('data.version', 2);

        $headers()->postJson("/api/v1/prep-lists/{$prepListId}/regenerate", [
            'guest_count' => 175,
            'preserve_assignments' => true,
            'preserve_completed_items' => true,
            'source' => 'regeneration',
        ])->assertCreated()->assertJsonPath('data.version.guest_count_snapshot', 175);

        $headers()->postJson('/api/v1/tasks', [
            'title' => 'Confirm dietary meals',
            'status' => 'todo',
            'priority' => 'high',
            'event_id' => $eventId,
            'assignments' => [[
                'membership_id' => $membershipId,
                'is_primary' => true,
            ]],
        ])->assertCreated();

        $headers()->getJson('/api/v1/command-center')
            ->assertOk()
            ->assertJsonPath('data.workspace_summary.open_tasks', 1)
            ->assertJsonPath('data.workspace_summary.menus', 1)
            ->assertJsonPath('data.workspace_summary.recipes', 3);

        $headers()->getJson('/api/v1/search?q=Corporate&limit=20')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Corporate Leadership Dinner']);

        $headers()->getJson('/api/v1/events')->assertOk()
            ->assertJsonFragment(['name' => 'Corporate Leadership Dinner']);
    }

    public function test_workspace_created_through_api_rejects_foreign_resource_ids(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $registration = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Tenant',
            'last_name' => 'Owner',
            'email' => 'tenant-owner@humoo.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'device_name' => 'acceptance-suite',
        ])->assertCreated();
        $token = (string) $registration->json('token');

        $workspaceA = $this->withToken($token)->postJson('/api/v1/workspaces', [
            'name' => 'Acceptance Kitchen A',
            'timezone' => 'America/New_York',
            'currency' => 'usd',
        ])->assertCreated();
        $workspaceAId = (string) $workspaceA->json('data.workspace.id');

        $workspaceB = $this->withToken($token)->postJson('/api/v1/workspaces', [
            'name' => 'Acceptance Kitchen B',
            'timezone' => 'America/New_York',
            'currency' => 'usd',
        ])->assertCreated();
        $workspaceBId = (string) $workspaceB->json('data.workspace.id');

        $foreignClient = $this->withToken($token)->withHeader('X-Workspace-ID', $workspaceBId)
            ->postJson('/api/v1/clients', ['name' => 'Workspace B Client'])
            ->assertCreated();
        $foreignEvent = $this->withToken($token)->withHeader('X-Workspace-ID', $workspaceBId)
            ->postJson('/api/v1/events', [
                'name' => 'Workspace B Event',
                'client_id' => $foreignClient->json('data.id'),
                'starts_at' => now()->addDays(8)->toIso8601String(),
                'timezone' => 'America/New_York',
                'status' => 'draft',
            ])->assertCreated();

        $this->withToken($token)->withHeader('X-Workspace-ID', $workspaceAId)
            ->getJson('/api/v1/events/' . $foreignEvent->json('data.id'))
            ->assertNotFound();
    }

    private function recipePayload(string $name): array
    {
        $unitId = (string) Unit::query()->firstOrFail()->id;

        return [
            'name' => $name,
            'status' => 'active',
            'type' => 'standard',
            'version' => [
                'name' => $name,
                'status' => 'draft',
                'ingredients' => [[
                    'ingredient_name' => 'Primary ingredient',
                    'quantity' => 1,
                    'unit_id' => $unitId,
                ]],
                'steps' => [[
                    'instruction' => 'Prepare according to the recipe card.',
                ]],
                'yields' => [[
                    'quantity' => 1,
                    'unit_id' => $unitId,
                    'is_default' => true,
                ]],
            ],
        ];
    }

}
