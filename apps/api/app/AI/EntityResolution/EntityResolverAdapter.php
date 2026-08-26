<?php

namespace App\AI\EntityResolution;

interface EntityResolverAdapter
{
    public function entityType(): string;

    public function findById(EntityResolutionRequest $request, string $id): ?EntityCandidate;

    /** @return EntityCandidate[] */
    public function candidates(EntityResolutionRequest $request, int $limit): array;
}
