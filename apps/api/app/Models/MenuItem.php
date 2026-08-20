<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuItem extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'estimated_unit_cost' => 'decimal:4',
            'metadata' => 'array',
            'optional' => 'boolean',
            'planned_quantity' => 'decimal:4',
            'position' => 'integer',
            'quantity_per_guest' => 'decimal:4',
        ];
    }

    public function menuSection(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    public function dietaryTags(): BelongsToMany
    {
        return $this->belongsToMany(
            DietaryTag::class,
            'menu_item_dietary_tags'
        )->withPivot('source')->withTimestamps();
    }
}
