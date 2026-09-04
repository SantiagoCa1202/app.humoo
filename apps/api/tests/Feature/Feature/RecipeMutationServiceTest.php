<?php

namespace Tests\Feature\Feature;

use App\Application\Actions\Recipes\CreateRecipe;
use App\Application\Actions\Recipes\RecipeMutationService;
use App\Models\Recipe;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeMutationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_structured_mutation_preserves_the_recipe_snapshot_while_applying_many_changes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $tbsp = Unit::query()->where('key', 'tbsp')->firstOrFail();
        $cup = Unit::query()->where('key', 'cup')->firstOrFail();
        $portion = Unit::query()->where('key', 'portion')->firstOrFail();

        $recipe = app(CreateRecipe::class)->execute($workspace->id, $user->id, [
            'name' => 'Ranch Casero',
            'status' => 'active',
            'version' => [
                'name' => 'Ranch Casero', 'status' => 'draft',
                'ingredients' => [
                    ['ingredient_name' => 'Sal', 'quantity' => 1, 'unit_id' => $tbsp->id],
                    ['ingredient_name' => 'Aceite de canola', 'quantity' => 1, 'unit_id' => $cup->id],
                ],
                'steps' => [['instruction' => 'Mezclar los ingredientes.'], ['instruction' => 'Servir.']],
                'yields' => [['quantity' => 20, 'unit_id' => $portion->id, 'is_default' => true]],
            ],
        ]);
        $recipe = Recipe::query()->with([
            'tags', 'currentVersionRecord.ingredients.unit', 'currentVersionRecord.steps.temperatureUnit',
            'currentVersionRecord.yields.unit', 'currentVersionRecord.allergens',
        ])->findOrFail($recipe->id);
        $saltId = $recipe->currentVersionRecord->ingredients->firstWhere('ingredient_name', 'Sal')->id;
        $oilId = $recipe->currentVersionRecord->ingredients->firstWhere('ingredient_name', 'Aceite de canola')->id;
        $mixStepId = $recipe->currentVersionRecord->steps->firstWhere('instruction', 'Mezclar los ingredientes.')->id;
        $component = app(CreateRecipe::class)->execute($workspace->id, $user->id, [
            'name' => 'Base Dijon',
            'status' => 'active',
            'version' => [
                'name' => 'Base Dijon',
                'ingredients' => [['ingredient_name' => 'Mostaza', 'quantity' => 1, 'unit_id' => $tbsp->id]],
                'steps' => [['instruction' => 'Mezclar.']],
                'yields' => [['quantity' => 1, 'unit_id' => $portion->id, 'is_default' => true]],
            ],
        ]);
        $component->load('currentVersionRecord');

        $payload = app(RecipeMutationService::class)->apply($recipe, $recipe->currentVersionRecord, [
            'ingredient_changes' => [
                ['action' => 'set_quantity', 'target_ingredient_id' => $saltId, 'quantity' => 2, 'unit_key' => 'tbsp'],
                [
                    'action' => 'add',
                    'ingredient_name' => 'Dijon',
                    'quantity' => 0.5,
                    'unit_key' => 'cup',
                    'component_recipe_id' => $component->id,
                    'component_recipe_version_id' => $component->currentVersionRecord->id,
                ],
                ['action' => 'replace', 'target_ingredient_id' => $oilId, 'ingredient_name' => 'Aceite de oliva', 'quantity' => 1, 'unit_key' => 'cup'],
            ],
            'step_changes' => [
                ['action' => 'add_after', 'target_step_id' => $mixStepId, 'instruction' => 'Refrigerar durante 2 horas.'],
                ['action' => 'move_to_last', 'target_step_id' => $mixStepId],
            ],
            'yield' => ['quantity' => 50, 'unit_key' => 'portion'],
        ]);

        $ingredients = $payload['payload']['version']['ingredients'];
        $this->assertSame(2.0, $ingredients[0]['quantity']);
        $this->assertSame('Dijon', $ingredients[2]['ingredient_name']);
        $this->assertSame($component->id, $ingredients[2]['component_recipe_id']);
        $this->assertSame($component->currentVersionRecord->id, $ingredients[2]['component_recipe_version_id']);
        $this->assertSame('Aceite de oliva', $ingredients[1]['ingredient_name']);
        $this->assertSame(50.0, $payload['payload']['version']['yields'][0]['quantity']);
        $this->assertSame('Mezclar los ingredientes.', $payload['payload']['version']['steps'][2]['instruction']);
        $this->assertCount(6, $payload['changes']);
    }
}
