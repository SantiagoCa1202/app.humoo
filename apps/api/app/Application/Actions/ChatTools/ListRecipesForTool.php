<?php

namespace App\Application\Actions\ChatTools;

use App\AI\EntityResolution\RecipeEntityResolver;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;

class ListRecipesForTool
{
    public function __construct(private RecipeEntityResolver $resolver)
    {
    }

    public function execute(string $workspaceId, array $filters = []): array
    {
        $recipeId = trim((string) ($filters['recipe_id'] ?? ''));
        $search = trim((string) ($filters['search'] ?? $filters['recipe_search'] ?? ''));

        if ($recipeId !== '') {
            $recipe = Recipe::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($recipeId)
                ->with($this->resolver->relations())
                ->first();
            return ['count' => $recipe ? 1 : 0, 'items' => $recipe ? [(new RecipeResource($recipe))->resolve()] : [], 'mode' => 'detail'];
        }

        $recipes = Recipe::query()
            ->where('workspace_id', $workspaceId)
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('recipe_code', 'like', "%{$search}%");
            }))
            ->latest('updated_at')
            // List tools are used to select records for the next action. They
            // must carry enough references for a normal batch without loading
            // each recipe's ingredients, yields and steps. Full data remains
            // available through recipes.detail.
            ->limit(25)
            ->get();

        return [
            'count' => $recipes->count(),
            'items' => $recipes->map(fn (Recipe $recipe): array => $this->listItem($recipe))->all(),
            'mode' => 'list',
        ];
    }

    private function listItem(Recipe $recipe): array
    {
        return [
            'id' => $recipe->id,
            'name' => $recipe->name,
            'category' => $recipe->category,
            'current_version' => $recipe->current_version,
            'current_version_id' => null,
            'recipe_code' => $recipe->recipe_code,
            'status' => $recipe->status,
            'updated_at' => $recipe->updated_at?->toIso8601String(),
        ];
    }
}
