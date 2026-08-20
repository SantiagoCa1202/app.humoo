<?php

namespace App\Application\Actions\Recipes;

use App\Models\RecipeVersion;

class ScaleRecipe
{
    public function execute(RecipeVersion $version, array $targetYield): array
    {
        $baseYield = $version->yields->firstWhere('is_default', true) ?? $version->yields->first();

        if (!$baseYield || !$targetYield['quantity'] || !$baseYield->quantity) {
            return [
                'scale_factor' => null,
                'scaled_ingredients' => [],
            ];
        }

        if (
            filled($targetYield['unit_id'] ?? null)
            && filled($baseYield->unit_id)
            && $targetYield['unit_id'] !== $baseYield->unit_id
        ) {
            return [
                'scale_factor' => null,
                'scaled_ingredients' => [],
            ];
        }

        $scaleFactor = (float) $targetYield['quantity'] / (float) $baseYield->quantity;

        return [
            'scale_factor' => $scaleFactor,
            'scaled_ingredients' => $version->ingredients->map(fn ($ingredient) => [
                'id' => $ingredient->id,
                'ingredient_name' => $ingredient->ingredient_name,
                'quantity' => $ingredient->quantity !== null
                    ? number_format((float) $ingredient->quantity * $scaleFactor, 6, '.', '')
                    : null,
                'unit_id' => $ingredient->unit_id,
            ])->all(),
        ];
    }
}
