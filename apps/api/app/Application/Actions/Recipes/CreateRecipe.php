<?php

namespace App\Application\Actions\Recipes;

use App\Models\Recipe;
use App\Models\RecipeTag;
use Illuminate\Support\Facades\DB;

class CreateRecipe
{
    private CreateRecipeVersion $createRecipeVersion;

    public function __construct(CreateRecipeVersion $createRecipeVersion)
    {
        $this->createRecipeVersion = $createRecipeVersion;
    }

    public function execute(string $workspaceId, string $userId, array $payload, string $source = 'manual'): Recipe
    {
        return DB::transaction(function () use ($workspaceId, $userId, $payload, $source): Recipe {
            $recipe = Recipe::query()->create([
                'workspace_id' => $workspaceId,
                'name' => trim((string) $payload['name']),
                'description' => $this->trimOrNull($payload['description'] ?? null),
                'category' => $this->trimOrNull($payload['category'] ?? null),
                'type' => $this->trimOrNull($payload['type'] ?? null) ?? 'standard',
                'status' => $payload['status'] ?? 'draft',
                'recipe_code' => $this->trimOrNull($payload['recipe_code'] ?? null),
                'metadata' => $payload['metadata'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $version = $this->createRecipeVersion->execute(
                $recipe,
                $workspaceId,
                $userId,
                $payload['version'],
                null,
                $source
            );

            $allowedTagIds = RecipeTag::query()
                ->whereIn('id', $payload['tags'] ?? [])
                ->where(function ($query) use ($workspaceId): void {
                    $query->whereNull('workspace_id')
                        ->orWhere('workspace_id', $workspaceId);
                })
                ->pluck('id')
                ->all();

            $recipe->tags()->sync($allowedTagIds);
            $recipe->forceFill([
                'current_version' => $version->version,
            ])->save();

            return $recipe->fresh();
        });
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
