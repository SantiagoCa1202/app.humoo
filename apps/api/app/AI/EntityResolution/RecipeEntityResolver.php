<?php

namespace App\AI\EntityResolution;

use App\Models\Recipe;
use App\Models\RecipeVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RecipeEntityResolver
{
    public function resolve(
        string $workspaceId,
        array $references,
        ?string $recipeId = null,
        ?string $recipeSearch = null,
        ?string $versionId = null
    ): array {
        $query = Recipe::query()->where('workspace_id', $workspaceId)->with($this->relations());

        if (filled($recipeId)) {
            $recipe = $query->whereKey($recipeId)->first();
            return $recipe ? $this->resolved($recipe, $versionId) : ['status' => 'missing'];
        }

        if (filled($versionId)) {
            $version = RecipeVersion::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($versionId)
                ->first();
            if (!$version) {
                return ['status' => 'missing'];
            }
            $recipe = $query->whereKey($version->recipe_id)->first();
            return $recipe ? ['status' => 'resolved', 'recipe' => $recipe, 'version' => $version->load(['ingredients.unit', 'steps.temperatureUnit', 'yields.unit', 'allergens'])] : ['status' => 'missing'];
        }

        $search = $this->normalize($recipeSearch);
        if ($search !== '') {
            $exact = (clone $query)->whereRaw('LOWER(name) = ?', [$search])->get();
            if ($exact->count() === 1) {
                return $this->resolved($exact->first());
            }

            return $this->collectionResult((clone $query)
                ->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                ->limit(5)
                ->get());
        }

        $reference = collect($references)->first(fn (array $item): bool =>
            ($item['type'] ?? null) === 'recipe'
            && in_array(($item['role'] ?? null), ['active', 'recent', 'previous'], true)
        );
        if (is_array($reference) && filled($reference['id'] ?? null)) {
            $recipe = (clone $query)->whereKey($reference['id'])->first();
            return $recipe ? $this->resolved($recipe) : ['status' => 'missing'];
        }

        return ['status' => 'missing'];
    }

    public function relations(): array
    {
        return [
            'createdBy',
            'updatedBy',
            'tags',
            'currentVersionRecord.ingredients.unit',
            'currentVersionRecord.steps.temperatureUnit',
            'currentVersionRecord.yields.unit',
            'currentVersionRecord.allergens',
            'currentVersionRecord.createdBy',
            'currentVersionRecord.approvedBy',
        ];
    }

    private function resolved(Recipe $recipe, ?string $versionId = null): array
    {
        $version = $recipe->currentVersionRecord;

        return ['status' => 'resolved', 'recipe' => $recipe, 'version' => $version];
    }

    private function collectionResult(Collection $recipes): array
    {
        if ($recipes->count() === 1) {
            return $this->resolved($recipes->first());
        }
        if ($recipes->count() > 1) {
            return [
                'status' => 'ambiguous',
                'candidates' => $recipes->map(fn (Recipe $recipe): array => [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                ])->values()->all(),
            ];
        }
        return ['status' => 'missing'];
    }

    private function normalize(?string $value): string
    {
        return Str::lower(trim((string) $value));
    }
}
