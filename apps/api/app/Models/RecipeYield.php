<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeYield extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'factor_to_base' => 'decimal:8',
            'is_default' => 'boolean',
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
}
