<?php

namespace App\AI\EntityResolution;

use App\Models\Recipe;
use App\Models\RecipeVersion;

class RecipeEntityResolver
{
    public function __construct(private EntityReferenceResolver $referenceResolver)
    {
    }

    public function resolve(
        string $workspaceId,
        array $references,
        ?string $recipeId = null,
        ?string $recipeSearch = null,
        ?string $versionId = null
    ): array {
        if (filled($versionId)) {
            $version = RecipeVersion::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($versionId)
                ->first();
            if (!$version) {
                return ['status' => 'missing'];
            }
            $recipe = Recipe::query()->where('workspace_id', $workspaceId)->with($this->relations())->whereKey($version->recipe_id)->first();
            return $recipe ? ['status' => 'resolved', 'recipe' => $recipe, 'version' => $version->load(['ingredients.unit', 'steps.temperatureUnit', 'yields.unit', 'allergens'])] : ['status' => 'missing'];
        }

        $result = $this->referenceResolver->resolve(new EntityResolutionRequest(
            workspaceId: $workspaceId,
            actorId: null,
            conversationId: null,
            actionKey: null,
            entityType: 'recipe',
            unresolvedField: 'recipe_id',
            rawReference: $recipeSearch,
            knownPayload: ['recipe_id' => $recipeId],
            conversationReferences: $references,
            riskLevel: 'write',
        ));
        if ($result->status === 'resolved' && $result->resolved?->entityId) {
            $recipe = Recipe::query()->where('workspace_id', $workspaceId)->with($this->relations())->whereKey($result->resolved->entityId)->first();
            return $recipe ? $this->resolved($recipe) : ['status' => 'missing'];
        }

        return $result->status === 'ambiguous'
            ? ['status' => 'ambiguous', 'candidates' => array_map(fn (EntityCandidate $candidate): array => ['id' => $candidate->entityId, 'name' => $candidate->displayName, 'safe_metadata' => $candidate->safeMetadata], $result->candidates)]
            : ['status' => 'missing'];
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

}
