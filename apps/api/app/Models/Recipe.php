<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends WorkspaceModel
{
    use SoftDeletes;

    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            RecipeTag::class,
            'recipe_tag_assignments'
        );
    }
}
