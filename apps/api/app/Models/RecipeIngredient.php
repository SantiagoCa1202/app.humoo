<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'waste_percentage' => 'decimal:4',
            'yield_percentage' => 'decimal:4',
            'conversion_factor' => 'decimal:8',
            'unit_cost' => 'decimal:4',
            'extended_cost' => 'decimal:4',
            'optional' => 'boolean',
            'scalable' => 'boolean',
        ];
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function componentRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'component_recipe_id');
    }

    public function componentRecipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'component_recipe_version_id');
    }
}
