<?php

namespace App\Models;

class PrepItem extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'portions' => 'decimal:4',
            'yield_quantity' => 'decimal:4',
            'due_at' => 'datetime',
        ];
    }

    public function section()
    {
        return $this->belongsTo(
            PrepSection::class,
            'prep_section_id'
        );
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function recipeVersion()
    {
        return $this->belongsTo(RecipeVersion::class);
    }

    public function assignments()
    {
        return $this->hasMany(
            PrepItemAssignment::class
        );
    }

    public function dependencies()
    {
        return $this->belongsToMany(
            PrepItem::class,
            'prep_item_dependencies',
            'prep_item_id',
            'depends_on_prep_item_id'
        );
    }
}
