<?php

namespace Tests\Feature\Feature;

use App\Models\Event;
use App\Models\Menu;
use App\Models\MenuVersion;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_user_can_create_menu_with_sections_items_recipe_and_event(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');
        $recipeVersion = $this->createRecipeSnapshot($workspace->id);
        $event = $this->createEvent($workspace->id);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/menus', [
                'name' => 'Summer Tasting',
                'description' => 'Seasonal tasting menu',
                'status' => 'active',
                'event_id' => $event->id,
                'sections' => [[
                    'name' => 'First Course',
                    'items' => [[
                        'name' => 'Tomato Salad',
                        'recipe_id' => $recipeVersion->recipe_id,
                        'recipe_version_id' => $recipeVersion->id,
                    ]],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.current_version', 1)
            ->assertJsonPath('data.event.id', $event->id)
            ->assertJsonPath('data.sections.0.items.0.recipe_id', $recipeVersion->recipe_id)
            ->assertJsonPath('data.sections.0.items.0.recipe_version_id', $recipeVersion->id);

        $this->assertDatabaseHas('menus', [
            'workspace_id' => $workspace->id,
            'name' => 'Summer Tasting',
            'current_version' => 1,
        ]);
    }

    public function test_menu_update_creates_new_version_and_stale_revision_returns_409(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');
        $recipeVersion = $this->createRecipeSnapshot($workspace->id);
        $event = $this->createEvent($workspace->id);

        $create = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/menus', [
                'name' => 'Dinner Menu',
                'status' => 'active',
                'event_id' => $event->id,
                'sections' => [[
                    'name' => 'Main',
                    'items' => [[
                        'name' => 'Roasted Vegetables',
                        'recipe_id' => $recipeVersion->recipe_id,
                        'recipe_version_id' => $recipeVersion->id,
                    ]],
                ]],
            ])
            ->assertCreated();

        $menu = Menu::query()->findOrFail((string) $create->json('data.id'));
        $version = MenuVersion::query()
            ->where('menu_id', $menu->id)
            ->firstOrFail();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/menus/{$menu->id}", [
                'name' => 'Dinner Menu Updated',
                'status' => 'active',
                'event_id' => $event->id,
                'current_version_id' => $version->id,
                'expected_revision' => $version->revision,
                'sections' => [[
                    'name' => 'Main',
                    'items' => [[
                        'name' => 'Roasted Vegetables Deluxe',
                        'recipe_id' => $recipeVersion->recipe_id,
                        'recipe_version_id' => $recipeVersion->id,
                    ]],
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.current_version', 2);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/menus/{$menu->id}", [
                'name' => 'Dinner Menu Conflict',
                'status' => 'active',
                'event_id' => $event->id,
                'current_version_id' => $version->id,
                'expected_revision' => $version->revision,
                'sections' => [[
                    'name' => 'Main',
                    'items' => [[
                        'name' => 'Conflict Item',
                        'recipe_id' => $recipeVersion->recipe_id,
                        'recipe_version_id' => $recipeVersion->id,
                    ]],
                ]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT');
    }

    public function test_menu_duplicate_creates_new_menu_with_new_ids(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');
        $recipeVersion = $this->createRecipeSnapshot($workspace->id);

        $created = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/menus', [
                'name' => 'Cocktail Menu',
                'status' => 'active',
                'sections' => [[
                    'name' => 'Bites',
                    'items' => [[
                        'name' => 'Mini Tart',
                        'recipe_id' => $recipeVersion->recipe_id,
                        'recipe_version_id' => $recipeVersion->id,
                    ]],
                ]],
            ])
            ->assertCreated();

        $menuId = (string) $created->json('data.id');

        $duplicate = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson("/api/v1/menus/{$menuId}/duplicate", [
                'proposed_name' => 'Cocktail Menu Copy',
                'include_sections' => true,
                'include_items' => true,
                'include_recipe_links' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Cocktail Menu Copy')
            ->assertJsonPath('data.current_version', 1);

        $this->assertNotSame($menuId, (string) $duplicate->json('data.id'));
    }

    private function createEvent(string $workspaceId): Event
    {
        return Event::query()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Menu Validation Event',
            'starts_at' => '2026-08-20T18:00:00Z',
            'timezone' => 'America/New_York',
            'status' => 'draft',
            'priority' => 'normal',
            'version' => 1,
        ]);
    }

    private function createRecipeSnapshot(string $workspaceId): RecipeVersion
    {
        $recipe = Recipe::query()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Linked Recipe',
            'status' => 'active',
            'current_version' => 1,
        ]);

        return RecipeVersion::query()->create([
            'workspace_id' => $workspaceId,
            'recipe_id' => $recipe->id,
            'version' => 1,
            'name' => 'Linked Recipe',
            'status' => 'draft',
            'revision' => 1,
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
