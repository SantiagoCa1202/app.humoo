<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends WorkspaceModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RecipeVersion::class)
            ->orderByDesc('version');
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(RecipeVersion::class)
            // Eager loading runs a query rooted at recipe_versions, so join the
            // owning recipes row before comparing its current_version value.
            ->join('recipes as current_recipes', function ($join): void {
                $join
                    ->on('current_recipes.id', '=', 'recipe_versions.recipe_id')
                    ->on('current_recipes.workspace_id', '=', 'recipe_versions.workspace_id');
            })
            ->whereColumn('recipe_versions.version', 'current_recipes.current_version')
            ->select('recipe_versions.*');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            RecipeTag::class,
            'recipe_tag_assignments'
        );
    }

    public function imageDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'image_document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versionChanges(): HasMany
    {
        return $this->hasMany(RecipeVersionChange::class);
    }
}
