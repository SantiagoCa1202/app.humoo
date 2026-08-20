<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeVersion extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'base_yield' => 'decimal:4',
            'portion_size' => 'decimal:4',
            'storage_temperature_min' => 'decimal:2',
            'storage_temperature_max' => 'decimal:2',
            'locked' => 'boolean',
            'locked_at' => 'datetime',
            'approved_at' => 'datetime',
            'estimated_total_cost' => 'decimal:4',
            'estimated_cost_per_yield' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)
            ->orderBy('position');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)
            ->orderBy('position');
    }

    public function yields(): HasMany
    {
        return $this->hasMany(RecipeYield::class)
            ->orderByDesc('is_default')
            ->orderBy('id');
    }

    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(
            Allergen::class,
            'recipe_version_allergens'
        )->withPivot([
            'source',
            'presence',
        ])->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function yieldUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'yield_unit_id');
    }

    public function portionUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'portion_unit_id');
    }

    public function temperatureUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'temperature_unit_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(RecipeVersionChange::class, 'to_version_id');
    }
}
