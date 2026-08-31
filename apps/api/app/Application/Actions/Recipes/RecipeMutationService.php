<?php

namespace App\Application\Actions\Recipes;

use App\AI\Recipes\UnitResolver;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/**
 * Applies an already structured chat mutation to a recipe snapshot.
 *
 * The AI selects the recipe and expresses the operation as typed arguments;
 * this service deliberately does not interpret user language.  The resulting
 * complete payload continues through the normal versioned UpdateRecipe path.
 */
final class RecipeMutationService
{
    public function __construct(private UnitResolver $unitResolver)
    {
    }

    /** @return array{payload: array<string, mixed>, changes: array<int, array<string, string>>} */
    public function apply(Recipe $recipe, RecipeVersion $version, array $mutation): array
    {
        $version->loadMissing(['ingredients.unit', 'steps.temperatureUnit', 'yields.unit', 'allergens']);

        $payload = $this->snapshot($recipe, $version);
        $changes = [];

        foreach ((array) ($mutation['ingredient_changes'] ?? []) as $change) {
            if (!is_array($change) || ($change['action'] ?? null) === null) {
                continue;
            }
            $action = (string) $change['action'];
            $ingredients =& $payload['version']['ingredients'];
            if ($action === 'add') {
                $ingredients[] = $this->newIngredient($change);
                $changes[] = ['label' => 'ingredient_added', 'after' => $ingredients[array_key_last($ingredients)]['ingredient_name']];
                continue;
            }

            $index = $this->ingredientIndex($ingredients, $change);
            if ($action === 'remove') {
                $label = (string) $ingredients[$index]['ingredient_name'];
                array_splice($ingredients, $index, 1);
                if ($ingredients === []) {
                    throw ValidationException::withMessages(['ingredient_changes' => ['A recipe must retain at least one ingredient.']]);
                }
                $changes[] = ['label' => 'ingredient_removed', 'after' => $label];
                continue;
            }

            if ($action === 'replace') {
                $replacement = $this->newIngredient($change, $ingredients[$index]);
                $previous = (string) $ingredients[$index]['ingredient_name'];
                $ingredients[$index] = $replacement;
                $changes[] = ['label' => 'ingredient_replaced', 'after' => $previous.' -> '.$replacement['ingredient_name']];
                continue;
            }

            if ($action === 'set_quantity') {
                $quantity = $change['quantity'] ?? null;
                if (!is_numeric($quantity) || (float) $quantity <= 0) {
                    throw ValidationException::withMessages(['ingredient_changes' => ['Ingredient quantities must be greater than zero.']]);
                }
                $ingredients[$index]['quantity'] = (float) $quantity;
                if (filled($change['unit_key'] ?? null)) {
                    $ingredients[$index]['unit_id'] = $this->unitId((string) $change['unit_key']);
                }
                $changes[] = ['label' => 'ingredient_updated', 'after' => $ingredients[$index]['ingredient_name']];
                continue;
            }

            throw ValidationException::withMessages(['ingredient_changes' => ['Unsupported ingredient mutation.']]);
        }

        foreach ((array) ($mutation['step_changes'] ?? []) as $change) {
            if (!is_array($change) || ($change['action'] ?? null) === null) {
                continue;
            }
            $steps =& $payload['version']['steps'];
            $action = (string) $change['action'];
            if ($action === 'append' || $action === 'add_after') {
                $instruction = trim((string) ($change['instruction'] ?? ''));
                if ($instruction === '') {
                    throw ValidationException::withMessages(['step_changes' => ['A new step needs an instruction.']]);
                }
                $step = [
                    'title' => $this->nullableText($change['title'] ?? null),
                    'instruction' => $instruction,
                    'duration_minutes' => is_numeric($change['duration_minutes'] ?? null) ? (int) $change['duration_minutes'] : null,
                    'notes' => $this->nullableText($change['notes'] ?? null),
                ];
                $position = $action === 'append' ? count($steps) : $this->stepIndex($steps, $change) + 1;
                array_splice($steps, $position, 0, [$step]);
                $changes[] = ['label' => 'step_added', 'after' => $instruction];
                continue;
            }
            $index = $this->stepIndex($steps, $change);
            if ($action === 'move_to_last') {
                $step = $steps[$index];
                array_splice($steps, $index, 1);
                $steps[] = $step;
                $changes[] = ['label' => 'step_moved', 'after' => (string) $step['instruction']];
                continue;
            }
            if ($action === 'replace') {
                $instruction = trim((string) ($change['instruction'] ?? ''));
                if ($instruction === '') {
                    throw ValidationException::withMessages(['step_changes' => ['A replacement step needs an instruction.']]);
                }
                $steps[$index] = [...$steps[$index], 'instruction' => $instruction, 'title' => $this->nullableText($change['title'] ?? ($steps[$index]['title'] ?? null))];
                $changes[] = ['label' => 'step_updated', 'after' => $instruction];
                continue;
            }
            throw ValidationException::withMessages(['step_changes' => ['Unsupported step mutation.']]);
        }

        if (is_array($mutation['yield'] ?? null) && array_key_exists('quantity', $mutation['yield'])) {
            $yield = $mutation['yield'];
            if (!is_numeric($yield['quantity']) || (float) $yield['quantity'] <= 0) {
                throw ValidationException::withMessages(['yield.quantity' => ['Yield must be greater than zero.']]);
            }
            $payload['version']['yields'][0]['quantity'] = (float) $yield['quantity'];
            if (filled($yield['unit_key'] ?? null)) {
                $payload['version']['yields'][0]['unit_id'] = $this->unitId((string) $yield['unit_key']);
            }
            $changes[] = ['label' => 'yield_updated', 'after' => (string) $yield['quantity']];
        }

        if (is_array($mutation['convert_units'] ?? null)) {
            $changes = [...$changes, ...$this->convertIngredients($payload['version']['ingredients'], $mutation['convert_units'])];
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['mutation' => ['The recipe mutation does not contain a change.']]);
        }

        return ['payload' => $payload, 'changes' => $changes];
    }

    /** @return array<string, mixed> */
    public function copyPayload(Recipe $recipe, RecipeVersion $version): array
    {
        $version->loadMissing(['ingredients.unit', 'steps.temperatureUnit', 'yields.unit', 'allergens']);

        return $this->snapshot($recipe, $version);
    }

    /** @return array<string, mixed> */
    private function snapshot(Recipe $recipe, RecipeVersion $version): array
    {
        return [
            'name' => $recipe->name, 'description' => $recipe->description, 'category' => $recipe->category,
            'type' => $recipe->type, 'status' => $recipe->status, 'recipe_code' => $recipe->recipe_code,
            'metadata' => $recipe->metadata, 'tags' => $recipe->relationLoaded('tags') ? $recipe->tags->pluck('id')->all() : [],
            'version' => [
                'name' => $version->name, 'description' => $version->description, 'category' => $version->category,
                'status' => $version->status, 'prep_time_minutes' => $version->prep_time_minutes,
                'cook_time_minutes' => $version->cook_time_minutes, 'rest_time_minutes' => $version->rest_time_minutes,
                'total_time_minutes' => $version->total_time_minutes, 'shelf_life_hours' => $version->shelf_life_hours,
                'storage_instructions' => $version->storage_instructions,
                'storage_temperature_min' => $version->storage_temperature_min,
                'storage_temperature_max' => $version->storage_temperature_max,
                'temperature_unit_id' => $version->temperature_unit_id,
                'equipment_required' => $version->equipment_required, 'change_summary' => $version->change_summary,
                'metadata' => $version->metadata,
                'ingredients' => $version->ingredients->map(fn ($item): array => [
                    'source_id' => $item->id, 'ingredient_name' => $item->ingredient_name, 'quantity' => (float) $item->quantity,
                    'unit_id' => $item->unit_id, 'notes' => $item->notes, 'optional' => $item->optional,
                    'preparation' => $item->preparation, 'component_recipe_id' => $item->component_recipe_id,
                    'component_recipe_version_id' => $item->component_recipe_version_id,
                    'inventory_item_id' => $item->inventory_item_id, 'waste_percentage' => $item->waste_percentage,
                    'yield_percentage' => $item->yield_percentage, 'conversion_factor' => $item->conversion_factor,
                    'unit_cost' => $item->unit_cost, 'extended_cost' => $item->extended_cost,
                    'cost_currency' => $item->cost_currency, 'scalable' => $item->scalable,
                ])->all(),
                'steps' => $version->steps->map(fn ($step): array => [
                    'source_id' => $step->id, 'instruction' => $step->instruction, 'title' => $step->title,
                    'duration_minutes' => $step->duration_minutes, 'notes' => $step->notes,
                    'station_id' => $step->station_id, 'temperature' => $step->temperature,
                    'temperature_unit_id' => $step->temperature_unit_id, 'type' => $step->type, 'critical' => $step->critical,
                ])->all(),
                'yields' => $version->yields->map(fn ($yield): array => [
                    'quantity' => (float) $yield->quantity, 'unit_id' => $yield->unit_id, 'label' => $yield->label,
                    'is_default' => $yield->is_default, 'factor_to_base' => $yield->factor_to_base,
                ])->all(),
                'allergens' => $version->allergens->map(fn ($allergen): array => ['id' => $allergen->id, 'presence' => $allergen->pivot->presence ?? 'contains', 'source' => $allergen->pivot->source ?? 'manual'])->all(),
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $ingredients */
    private function ingredientIndex(array $ingredients, array $change): int
    {
        $id = $change['target_ingredient_id'] ?? null;
        if (!filled($id)) {
            throw ValidationException::withMessages(['ingredient_changes' => ['Select the ingredient by its stable ID from the current recipe detail.']]);
        }
        $matches = array_keys(array_filter($ingredients, static fn (array $ingredient): bool =>
            ($ingredient['source_id'] ?? null) === $id
        ));
        if (count($matches) !== 1) {
            throw ValidationException::withMessages(['ingredient_changes' => ['The ingredient was not found in the current recipe.']]);
        }
        return (int) $matches[0];
    }

    /** @param array<int, array<string, mixed>> $steps */
    private function stepIndex(array $steps, array $change): int
    {
        $id = $change['target_step_id'] ?? null;
        if (!filled($id)) {
            throw ValidationException::withMessages(['step_changes' => ['Select the preparation step by its stable ID from the current recipe detail.']]);
        }
        $matches = array_keys(array_filter($steps, static fn (array $step): bool =>
            ($step['source_id'] ?? null) === $id
        ));
        if (count($matches) !== 1) {
            throw ValidationException::withMessages(['step_changes' => ['The preparation step was not found.']]);
        }
        return (int) $matches[0];
    }

    /** @param array<string, mixed> $change @param array<string, mixed>|null $current */
    private function newIngredient(array $change, ?array $current = null): array
    {
        $name = $this->nullableText($change['ingredient_name'] ?? null) ?? $current['ingredient_name'] ?? null;
        $quantity = $change['quantity'] ?? $current['quantity'] ?? null;
        if ($name === null || !is_numeric($quantity) || (float) $quantity <= 0) {
            throw ValidationException::withMessages(['ingredient_changes' => ['An ingredient needs a name and a quantity greater than zero.']]);
        }
        $unitId = filled($change['unit_key'] ?? null) ? $this->unitId((string) $change['unit_key']) : ($current['unit_id'] ?? null);
        if (!$unitId) {
            throw ValidationException::withMessages(['ingredient_changes' => ['An ingredient needs a supported unit.']]);
        }
        return [
            'ingredient_name' => $name, 'quantity' => (float) $quantity, 'unit_id' => $unitId,
            'notes' => $this->nullableText($change['notes'] ?? ($current['notes'] ?? null)),
            'optional' => (bool) ($change['optional'] ?? ($current['optional'] ?? false)),
            'preparation' => $this->nullableText($change['preparation'] ?? ($current['preparation'] ?? null)),
            'component_recipe_id' => $current['component_recipe_id'] ?? null,
            'component_recipe_version_id' => $current['component_recipe_version_id'] ?? null,
        ];
    }

    /** @param array<int, array<string, mixed>> $ingredients @return array<int, array<string, string>> */
    private function convertIngredients(array &$ingredients, array $targets): array
    {
        $unitIds = collect($ingredients)->pluck('unit_id')->filter()->unique()->all();
        $units = Unit::query()->whereIn('id', $unitIds)->get()->keyBy('id');
        $targetKeys = ['volume' => $targets['volume_unit_key'] ?? 'ml', 'weight' => $targets['weight_unit_key'] ?? 'g'];
        $changes = [];
        foreach ($ingredients as &$ingredient) {
            $from = $units->get($ingredient['unit_id'] ?? null);
            if (!$from instanceof Unit || !in_array($from->dimension, ['volume', 'weight'], true) || !$from->base_factor) continue;
            $target = Unit::query()->where('key', $targetKeys[$from->dimension])->where('dimension', $from->dimension)->where('active', true)->first();
            if (!$target || !$target->base_factor || $target->id === $from->id) continue;
            $ingredient['quantity'] = round(((float) $ingredient['quantity'] * (float) $from->base_factor) / (float) $target->base_factor, $target->decimal_places);
            $ingredient['unit_id'] = $target->id;
            $changes[] = ['label' => 'unit_converted', 'after' => $ingredient['ingredient_name'].' -> '.$target->symbol];
        }
        return $changes;
    }

    private function unitId(string $key): string
    {
        $id = $this->unitResolver->idFor($key);
        if (!$id) throw ValidationException::withMessages(['unit_key' => ['The requested unit is not supported.']]);
        return $id;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
