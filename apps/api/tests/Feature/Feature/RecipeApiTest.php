<?php

namespace Tests\Feature\Feature;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Unit;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_user_can_create_recipe_with_version_children(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');

        $response = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/recipes', [
                'name' => 'Tomato Sauce',
                'status' => 'active',
                'type' => 'standard',
                'version' => [
                    'name' => 'Tomato Sauce',
                    'status' => 'draft',
                    'ingredients' => [[
                        'ingredient_name' => 'Tomato',
                        'quantity' => 2,
                        'unit_id' => $unit->id,
                    ]],
                    'steps' => [[
                        'instruction' => 'Blend ingredients.',
                    ]],
                    'yields' => [[
                        'quantity' => 1,
                        'unit_id' => $unit->id,
                        'is_default' => true,
                    ]],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.current_version', 1)
            ->assertJsonPath('data.current_version_record.version', 1);

        $this->assertDatabaseHas('recipes', [
            'workspace_id' => $workspace->id,
            'name' => 'Tomato Sauce',
            'current_version' => 1,
        ]);
    }

    public function test_recipe_update_creates_new_version_and_stale_revision_returns_409(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = \App\Models\Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $token = $this->login('owner@humoo.local', 'password');

        $create = $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->postJson('/api/v1/recipes', [
                'name' => 'Pan Sauce',
                'status' => 'active',
                'type' => 'standard',
                'version' => [
                    'name' => 'Pan Sauce',
                    'status' => 'draft',
                    'ingredients' => [[
                        'ingredient_name' => 'Butter',
                        'quantity' => 1,
                        'unit_id' => $unit->id,
                    ]],
                    'steps' => [[
                        'instruction' => 'Whisk.',
                    ]],
                    'yields' => [[
                        'quantity' => 1,
                        'unit_id' => $unit->id,
                        'is_default' => true,
                    ]],
                ],
            ])->assertCreated();

        $recipe = Recipe::query()->findOrFail((string) $create->json('data.id'));
        $version = RecipeVersion::query()
            ->where('recipe_id', $recipe->id)
            ->firstOrFail();

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/recipes/{$recipe->id}", [
                'name' => 'Pan Sauce Updated',
                'status' => 'active',
                'type' => 'standard',
                'current_version_id' => $version->id,
                'expected_revision' => $version->revision,
                'version' => [
                    'name' => 'Pan Sauce Updated',
                    'status' => 'draft',
                    'ingredients' => [[
                        'ingredient_name' => 'Butter',
                        'quantity' => 2,
                        'unit_id' => $unit->id,
                    ]],
                    'steps' => [[
                        'instruction' => 'Whisk again.',
                    ]],
                    'yields' => [[
                        'quantity' => 1,
                        'unit_id' => $unit->id,
                        'is_default' => true,
                    ]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.current_version', 2);

        $this->withToken($token)
            ->withHeader('X-Workspace-ID', $workspace->id)
            ->patchJson("/api/v1/recipes/{$recipe->id}", [
                'name' => 'Pan Sauce Conflict',
                'status' => 'active',
                'type' => 'standard',
                'current_version_id' => $version->id,
                'expected_revision' => $version->revision,
                'version' => [
                    'name' => 'Pan Sauce Conflict',
                    'status' => 'draft',
                    'ingredients' => [[
                        'ingredient_name' => 'Butter',
                        'quantity' => 3,
                        'unit_id' => $unit->id,
                    ]],
                    'steps' => [[
                        'instruction' => 'Whisk conflict.',
                    ]],
                    'yields' => [[
                        'quantity' => 1,
                        'unit_id' => $unit->id,
                        'is_default' => true,
                    ]],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'VERSION_CONFLICT');
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
