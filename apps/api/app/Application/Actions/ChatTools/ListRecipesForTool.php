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
            ->with($this->resolver->relations())
            ->latest('updated_at')
            ->limit(10)
            ->get();

        return ['count' => $recipes->count(), 'items' => RecipeResource::collection($recipes)->resolve(), 'mode' => 'list'];
    }
}
