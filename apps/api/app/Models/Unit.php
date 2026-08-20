<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends BaseModel
{
    protected function casts(): array
    {
        return [
            'base_factor' => 'decimal:10',
            'decimal_places' => 'integer',
            'active' => 'boolean',
            'system' => 'boolean',
        ];
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function recipeYields(): HasMany
    {
        return $this->hasMany(RecipeYield::class);
    }

    public function recipeVersionYieldUnits(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'yield_unit_id');
    }

    public function recipeVersionPortionUnits(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'portion_unit_id');
    }

    public function recipeVersionTemperatureUnits(): HasMany
    {
        return $this->hasMany(RecipeVersion::class, 'temperature_unit_id');
    }

    public function recipeStepsTemperatureUnits(): HasMany
    {
        return $this->hasMany(RecipeStep::class, 'temperature_unit_id');
    }
}
