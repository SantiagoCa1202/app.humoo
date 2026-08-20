<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends BaseModel
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function recipeVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            RecipeVersion::class,
            'recipe_version_allergens'
        )->withPivot([
            'source',
            'presence',
        ])->withTimestamps();
    }
}
