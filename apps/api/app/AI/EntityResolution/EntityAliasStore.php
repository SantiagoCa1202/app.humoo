<?php

namespace App\AI\EntityResolution;

use App\Models\EntityAlias;

class EntityAliasStore
{
    public function __construct(private EntityReferenceNormalizer $normalizer) {}

    public function remember(EntityResolutionRequest $request, EntityCandidate $candidate, string $alias): void
    {
        $normalized = $this->normalizer->normalize($alias);
        if ($normalized === '' || $candidate->entityId === '') {
            return;
        }

        $record = EntityAlias::query()->firstOrNew([
            'workspace_id' => $request->workspaceId,
            'entity_type' => $candidate->entityType,
            'entity_id' => $candidate->entityId,
            'normalized_alias' => $normalized,
        ]);
        $record->fill([
            'alias' => trim($alias),
            'locale' => $request->locale,
            'source' => 'confirmed_selection',
            'created_by' => $request->actorId,
        ]);
        $record->confirmation_count = $record->exists
            ? (int) $record->confirmation_count + 1
            : 1;
        $record->save();
    }
}
