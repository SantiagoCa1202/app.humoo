<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeStep extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'temperature' => 'decimal:2',
            'critical' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    public function temperatureUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'temperature_unit_id');
    }
}
