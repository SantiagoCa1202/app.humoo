<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrepItem extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'actual_quantity' => 'decimal:4',
            'completed_at' => 'datetime',
            'due_at' => 'datetime',
            'generated' => 'boolean',
            'metadata' => 'array',
            'portions' => 'decimal:4',
            'position' => 'integer',
            'quantity' => 'decimal:4',
            'requires_confirmation' => 'boolean',
            'scale_factor' => 'decimal:6',
            'started_at' => 'datetime',
            'starts_at' => 'datetime',
            'version' => 'integer',
            'yield_quantity' => 'decimal:4',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(
            PrepSection::class,
            'prep_section_id'
        );
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipeVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function yieldUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'yield_unit_id');
    }

    public function actualUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'actual_unit_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(
            PrepItemAssignment::class
        );
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(
            PrepItem::class,
            'prep_item_dependencies',
            'prep_item_id',
            'depends_on_prep_item_id'
        );
    }
}
