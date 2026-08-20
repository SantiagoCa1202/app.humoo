<?php

namespace App\Support;

use App\Models\RecipeVersion;
use App\Models\RecipeVersionChange;
use Illuminate\Support\Collection;

class RecipeVersionComparer
{
    public function syncChanges(?RecipeVersion $baseVersion, RecipeVersion $targetVersion): void
    {
        $targetVersion->changes()->delete();

        foreach ($this->buildChanges($baseVersion, $targetVersion) as $change) {
            RecipeVersionChange::query()->create([
                'workspace_id' => $targetVersion->workspace_id,
                'recipe_id' => $targetVersion->recipe_id,
                'from_version_id' => $baseVersion?->id,
                'to_version_id' => $targetVersion->id,
                ...$change,
            ]);
        }
    }

    public function buildChanges(?RecipeVersion $baseVersion, RecipeVersion $targetVersion): array
    {
        $changes = [];

        $this->pushScalarChange(
            $changes,
            'recipe.name_changed',
            'recipe',
            [
                'label' => 'Recipe name',
                'value' => $baseVersion?->name,
            ],
            [
                'label' => 'Recipe name',
                'value' => $targetVersion->name,
            ]
        );

        $this->pushScalarChange(
            $changes,
            'recipe.description_changed',
            'recipe',
            [
                'label' => 'Description',
                'value' => $baseVersion?->description,
            ],
            [
                'label' => 'Description',
                'value' => $targetVersion->description,
            ]
        );

        $this->pushScalarChange(
            $changes,
            'recipe.total_time_changed',
            'recipe',
            [
                'label' => 'Total time',
                'value' => $baseVersion?->total_time_minutes,
            ],
            [
                'label' => 'Total time',
                'value' => $targetVersion->total_time_minutes,
            ]
        );

        return [
            ...$changes,
            ...$this->compareIngredients($baseVersion, $targetVersion),
            ...$this->compareSteps($baseVersion, $targetVersion),
            ...$this->compareYields($baseVersion, $targetVersion),
            ...$this->compareAllergens($baseVersion, $targetVersion),
        ];
    }

    private function compareIngredients(?RecipeVersion $baseVersion, RecipeVersion $targetVersion): array
    {
        $base = collect($baseVersion?->ingredients ?? [])->keyBy(
            fn ($ingredient) => $this->ingredientKey($ingredient->ingredient_name, $ingredient->unit_id)
        );
        $target = collect($targetVersion->ingredients)->keyBy(
            fn ($ingredient) => $this->ingredientKey($ingredient->ingredient_name, $ingredient->unit_id)
        );

        return $this->compareCollections($base, $target, 'ingredient', true, function ($ingredient): array {
            return [
                'ingredient_name' => $ingredient->ingredient_name,
                'label' => $ingredient->ingredient_name,
                'quantity' => $ingredient->quantity,
                'unit' => $ingredient->unit?->symbol ?? $ingredient->unit?->name,
            ];
        });
    }

    private function compareSteps(?RecipeVersion $baseVersion, RecipeVersion $targetVersion): array
    {
        $base = collect($baseVersion?->steps ?? [])->keyBy(
            fn ($step) => (string) $step->position
        );
        $target = collect($targetVersion->steps)->keyBy(
            fn ($step) => (string) $step->position
        );

        return $this->compareCollections($base, $target, 'step', true, function ($step): array {
            return [
                'title' => $step->title ?: "Step {$step->position}",
                'label' => $step->title ?: "Step {$step->position}",
                'instruction' => $step->instruction,
            ];
        });
    }

    private function compareYields(?RecipeVersion $baseVersion, RecipeVersion $targetVersion): array
    {
        $base = collect($baseVersion?->yields ?? [])->keyBy(
            fn ($yieldRecord) => $this->yieldKey($yieldRecord->label, $yieldRecord->unit_id, $yieldRecord->is_default)
        );
        $target = collect($targetVersion->yields)->keyBy(
            fn ($yieldRecord) => $this->yieldKey($yieldRecord->label, $yieldRecord->unit_id, $yieldRecord->is_default)
        );

        return $this->compareCollections($base, $target, 'yield', true, function ($yieldRecord): array {
            return [
                'label' => $yieldRecord->label ?: 'Yield',
                'quantity' => $yieldRecord->quantity,
                'unit' => $yieldRecord->unit?->symbol ?? $yieldRecord->unit?->name,
            ];
        });
    }

    private function compareAllergens(?RecipeVersion $baseVersion, RecipeVersion $targetVersion): array
    {
        $base = collect($baseVersion?->allergens ?? [])->keyBy('id');
        $target = collect($targetVersion->allergens)->keyBy('id');

        return $this->compareCollections($base, $target, 'allergen', false, function ($allergen): array {
            return [
                'name' => $allergen->name,
                'label' => $allergen->name,
                'presence' => $allergen->pivot?->presence,
            ];
        });
    }

    private function compareCollections(
        Collection $base,
        Collection $target,
        string $entityType,
        bool $affectsProduction,
        callable $serializer
    ): array {
        $changes = [];
        $keys = $base->keys()->merge($target->keys())->unique()->values();

        foreach ($keys as $key) {
            $before = $base->get($key);
            $after = $target->get($key);

            if (!$before && $after) {
                $changes[] = [
                    'change_type' => "{$entityType}.added",
                    'entity_type' => $entityType,
                    'entity_id' => $after->id,
                    'before_value' => null,
                    'after_value' => $serializer($after),
                    'severity' => 'warning',
                    'affects_production' => $affectsProduction,
                ];
                continue;
            }

            if ($before && !$after) {
                $changes[] = [
                    'change_type' => "{$entityType}.removed",
                    'entity_type' => $entityType,
                    'entity_id' => $before->id,
                    'before_value' => $serializer($before),
                    'after_value' => null,
                    'severity' => 'warning',
                    'affects_production' => $affectsProduction,
                ];
                continue;
            }

            $beforeValue = $serializer($before);
            $afterValue = $serializer($after);

            if ($beforeValue !== $afterValue) {
                $changes[] = [
                    'change_type' => "{$entityType}.changed",
                    'entity_type' => $entityType,
                    'entity_id' => $after->id,
                    'before_value' => $beforeValue,
                    'after_value' => $afterValue,
                    'severity' => 'warning',
                    'affects_production' => $affectsProduction,
                ];
            }
        }

        return $changes;
    }

    private function pushScalarChange(
        array &$changes,
        string $changeType,
        string $entityType,
        ?array $before,
        ?array $after
    ): void {
        if (($before['value'] ?? null) === ($after['value'] ?? null)) {
            return;
        }

        $changes[] = [
            'change_type' => $changeType,
            'entity_type' => $entityType,
            'entity_id' => null,
            'before_value' => $before,
            'after_value' => $after,
            'severity' => 'info',
            'affects_production' => false,
        ];
    }

    private function ingredientKey(string $name, ?string $unitId): string
    {
        return strtolower(trim($name)) . '|' . ($unitId ?? '');
    }

    private function yieldKey(?string $label, ?string $unitId, ?bool $isDefault): string
    {
        return strtolower(trim((string) $label)) . '|' . ($unitId ?? '') . '|' . ((int) $isDefault);
    }
}
