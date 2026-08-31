<?php

namespace App\Application\Actions\Recipes;

use App\Models\MenuItem;
use App\Models\PrepItem;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

final class DeleteRecipe
{
    public function execute(Recipe $recipe): void
    {
        DB::transaction(function () use ($recipe): void {
            $versionIds = $recipe->versions()->pluck('id');
            MenuItem::query()
                ->where('workspace_id', $recipe->workspace_id)
                ->where(fn ($nested) => $nested->where('recipe_id', $recipe->id)->orWhereIn('recipe_version_id', $versionIds))
                ->update(['recipe_id' => null, 'recipe_version_id' => null]);
            PrepItem::query()
                ->where('workspace_id', $recipe->workspace_id)
                ->where(fn ($nested) => $nested->where('recipe_id', $recipe->id)->orWhereIn('recipe_version_id', $versionIds))
                ->update(['recipe_id' => null, 'recipe_version_id' => null]);
            $recipe->delete();
        });
    }
}
