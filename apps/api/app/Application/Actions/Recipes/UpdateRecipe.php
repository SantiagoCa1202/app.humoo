<?php

namespace App\Application\Actions\Recipes;

use App\Models\Recipe;
use App\Models\RecipeTag;
use Illuminate\Support\Facades\DB;

class UpdateRecipe
{
    private CreateRecipeVersion $createRecipeVersion;

    public function __construct(CreateRecipeVersion $createRecipeVersion)
    {
        $this->createRecipeVersion = $createRecipeVersion;
    }

    public function execute(
        Recipe $recipe,
        string $workspaceId,
        string $userId,
        string $currentVersionId,
        int $expectedRevision,
        array $payload
    ): ?Recipe {
        $currentVersion = $recipe->currentVersionRecord()
            ->with([
                'ingredients.unit',
                'steps.temperatureUnit',
                'yields.unit',
                'allergens',
            ])
            ->first();

        if (
            !$currentVersion
            || $currentVersion->id !== $currentVersionId
            || (int) $currentVersion->revision !== $expectedRevision
        ) {
            return null;
        }

        return DB::transaction(function () use (
            $recipe,
            $workspaceId,
            $userId,
            $payload,
            $currentVersion
        ): Recipe {
            $recipe->forceFill([
                'name' => trim((string) $payload['name']),
                'description' => $this->trimOrNull($payload['description'] ?? null),
                'category' => $this->trimOrNull($payload['category'] ?? null),
                'type' => $this->trimOrNull($payload['type'] ?? null) ?? 'standard',
                'status' => $payload['status'] ?? $recipe->status,
                'recipe_code' => $this->trimOrNull($payload['recipe_code'] ?? null),
                'metadata' => $payload['metadata'] ?? $recipe->metadata,
                'updated_by' => $userId,
            ])->save();

            $version = $this->createRecipeVersion->execute(
                $recipe,
                $workspaceId,
                $userId,
                $payload['version'],
                $currentVersion,
                'manual'
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
