<?php

namespace App\Application\Actions\Recipes;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\RecipeVersion;
use App\Models\RecipeYield;
use App\Support\RecipeVersionComparer;

class CreateRecipeVersion
{
    private RecipeVersionComparer $comparer;

    public function __construct(RecipeVersionComparer $comparer)
    {
        $this->comparer = $comparer;
    }

    public function execute(
        Recipe $recipe,
        string $workspaceId,
        string $userId,
        array $payload,
        ?RecipeVersion $baseVersion = null,
        string $source = 'manual'
    ): RecipeVersion {
        $versionNumber = (int) $recipe->current_version + 1;
        $defaultYield = $this->resolveDefaultYield($payload['yields'] ?? []);
        $recipeVersion = RecipeVersion::query()->create([
            'workspace_id' => $workspaceId,
            'recipe_id' => $recipe->id,
            'version' => $versionNumber,
            'name' => trim((string) ($payload['name'] ?? $recipe->name)),
            'description' => $this->trimOrNull($payload['description'] ?? null),
            'category' => $this->trimOrNull($payload['category'] ?? null) ?? $recipe->category,
            'base_yield' => $defaultYield['quantity'] ?? null,
            'yield_unit_id' => $defaultYield['unit_id'] ?? null,
            'prep_time_minutes' => $payload['prep_time_minutes'] ?? null,
            'cook_time_minutes' => $payload['cook_time_minutes'] ?? null,
            'rest_time_minutes' => $payload['rest_time_minutes'] ?? null,
            'total_time_minutes' => $payload['total_time_minutes'] ?? null,
            'shelf_life_hours' => $payload['shelf_life_hours'] ?? null,
            'storage_instructions' => $this->trimOrNull($payload['storage_instructions'] ?? null),
            'storage_temperature_min' => $payload['storage_temperature_min'] ?? null,
            'storage_temperature_max' => $payload['storage_temperature_max'] ?? null,
            'temperature_unit_id' => $payload['temperature_unit_id'] ?? null,
            'equipment_required' => $this->trimOrNull($payload['equipment_required'] ?? null),
            'status' => $payload['status'] ?? 'draft',
            'locked' => false,
            'change_summary' => $this->trimOrNull($payload['change_summary'] ?? null),
            'source' => $source,
            'revision' => 1,
            'metadata' => $payload['metadata'] ?? null,
            'created_by' => $userId,
        ]);

        $this->syncIngredients($recipeVersion, $workspaceId, $payload['ingredients'] ?? []);
        $this->syncSteps($recipeVersion, $workspaceId, $payload['steps'] ?? []);
        $this->syncYields($recipeVersion, $workspaceId, $payload['yields'] ?? []);
        $this->syncAllergens($recipeVersion, $payload['allergens'] ?? []);
        $this->updateCostSnapshot($recipeVersion);

        $recipeVersion = $recipeVersion->fresh([
            'ingredients.unit',
            'steps.temperatureUnit',
            'yields.unit',
            'allergens',
            'createdBy',
            'yieldUnit',
            'temperatureUnit',
        ]);

        $this->comparer->syncChanges($baseVersion?->fresh([
            'ingredients.unit',
            'steps.temperatureUnit',
            'yields.unit',
            'allergens',
        ]), $recipeVersion);

        return $recipeVersion;
    }

    private function syncIngredients(RecipeVersion $recipeVersion, string $workspaceId, array $ingredients): void
    {
        foreach (array_values($ingredients) as $index => $ingredient) {
            RecipeIngredient::query()->create([
                'workspace_id' => $workspaceId,
                'recipe_version_id' => $recipeVersion->id,
                'inventory_item_id' => $ingredient['inventory_item_id'] ?? null,
                'component_recipe_id' => $ingredient['component_recipe_id'] ?? null,
                'component_recipe_version_id' => $ingredient['component_recipe_version_id'] ?? null,
                'ingredient_name' => trim((string) $ingredient['ingredient_name']),
                'quantity' => $ingredient['quantity'],
                'unit_id' => $ingredient['unit_id'],
                'waste_percentage' => $ingredient['waste_percentage'] ?? 0,
                'yield_percentage' => $ingredient['yield_percentage'] ?? null,
                'conversion_factor' => $ingredient['conversion_factor'] ?? null,
                'unit_cost' => $ingredient['unit_cost'] ?? null,
                'extended_cost' => $this->resolveExtendedCost($ingredient),
                'cost_currency' => $this->normalizeCurrency($ingredient['cost_currency'] ?? null),
                'optional' => (bool) ($ingredient['optional'] ?? false),
                'scalable' => array_key_exists('scalable', $ingredient)
                    ? (bool) $ingredient['scalable']
                    : true,
                'preparation' => $this->trimOrNull($ingredient['preparation'] ?? null),
                'position' => $index + 1,
                'notes' => $this->trimOrNull($ingredient['notes'] ?? null),
            ]);
        }
    }

    private function syncSteps(RecipeVersion $recipeVersion, string $workspaceId, array $steps): void
    {
        foreach (array_values($steps) as $index => $step) {
            RecipeStep::query()->create([
                'workspace_id' => $workspaceId,
                'recipe_version_id' => $recipeVersion->id,
                'position' => $index + 1,
                'title' => $this->trimOrNull($step['title'] ?? null),
                'instruction' => trim((string) $step['instruction']),
                'duration_minutes' => $step['duration_minutes'] ?? null,
                'station_id' => $step['station_id'] ?? null,
                'temperature' => $step['temperature'] ?? null,
                'temperature_unit_id' => $step['temperature_unit_id'] ?? null,
                'type' => $this->trimOrNull($step['type'] ?? null),
                'critical' => (bool) ($step['critical'] ?? false),
                'notes' => $this->trimOrNull($step['notes'] ?? null),
            ]);
        }
    }

    private function syncYields(RecipeVersion $recipeVersion, string $workspaceId, array $yields): void
    {
        $normalizedYields = array_values($yields);
        $defaultIndex = collect($normalizedYields)->search(
            fn ($yieldRecord) => (bool) ($yieldRecord['is_default'] ?? false)
        );

        foreach ($normalizedYields as $index => $yieldRecord) {
            RecipeYield::query()->create([
                'workspace_id' => $workspaceId,
                'recipe_version_id' => $recipeVersion->id,
                'quantity' => $yieldRecord['quantity'],
                'unit_id' => $yieldRecord['unit_id'],
                'label' => $this->trimOrNull($yieldRecord['label'] ?? null),
                'factor_to_base' => $yieldRecord['factor_to_base'] ?? ($index === ($defaultIndex === false ? 0 : $defaultIndex) ? 1 : null),
                'is_default' => $index === ($defaultIndex === false ? 0 : $defaultIndex),
            ]);
        }
    }

    private function syncAllergens(RecipeVersion $recipeVersion, array $allergens): void
    {
        if ($allergens === []) {
            return;
        }

        $payload = collect($allergens)
            ->filter(fn ($allergen) => filled($allergen['id'] ?? null))
            ->mapWithKeys(fn ($allergen) => [
                $allergen['id'] => [
                    'presence' => $allergen['presence'] ?? 'contains',
                    'source' => $allergen['source'] ?? 'manual',
                ],
            ])
            ->all();

        $recipeVersion->allergens()->sync($payload);
    }

    private function updateCostSnapshot(RecipeVersion $recipeVersion): void
    {
        $ingredients = $recipeVersion->ingredients()->get();
        $totalCost = $ingredients->reduce(
            fn ($carry, RecipeIngredient $ingredient) => $carry + (float) ($ingredient->extended_cost ?? 0),
            0.0
        );
        $defaultYield = $recipeVersion->yields()->where('is_default', true)->first()
            ?? $recipeVersion->yields()->first();
        $currency = $ingredients
            ->pluck('cost_currency')
            ->filter(fn ($value) => filled($value))
            ->first();

        $recipeVersion->forceFill([
            'estimated_total_cost' => $totalCost > 0 ? number_format($totalCost, 4, '.', '') : null,
            'estimated_cost_per_yield' => $defaultYield && (float) $defaultYield->quantity > 0
                ? number_format($totalCost / (float) $defaultYield->quantity, 4, '.', '')
                : null,
            'cost_currency' => $this->normalizeCurrency($currency),
        ])->save();
    }

    private function resolveExtendedCost(array $ingredient): ?string
    {
        if (filled($ingredient['extended_cost'] ?? null)) {
            return (string) $ingredient['extended_cost'];
        }

        if (!filled($ingredient['unit_cost'] ?? null) || !filled($ingredient['quantity'] ?? null)) {
            return null;
        }

        return number_format(
            (float) $ingredient['unit_cost'] * (float) $ingredient['quantity'],
            4,
            '.',
            ''
        );
    }

    private function resolveDefaultYield(array $yields): ?array
    {
        if ($yields === []) {
            return null;
        }

        $defaultYield = collect($yields)->first(
            fn ($yieldRecord) => (bool) ($yieldRecord['is_default'] ?? false)
        );

        return $defaultYield ?: $yields[0];
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeCurrency(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? strtoupper($trimmed) : null;
    }
}
