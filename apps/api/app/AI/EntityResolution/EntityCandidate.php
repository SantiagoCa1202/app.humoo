<?php

namespace App\AI\EntityResolution;

class EntityCandidate
{
    public function __construct(
        public string $entityId,
        public string $entityType,
        public string $displayName,
        public array $searchableValues = [],
        public array $safeMetadata = [],
        public mixed $entity = null,
        public float $score = 0.0,
        public string $matchStrategy = 'none',
        public array $matchedFields = [],
        public float $contextScore = 0.0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'entity_id' => $this->entityId,
            'entity_type' => $this->entityType,
            'display_name' => $this->displayName,
            'score' => $this->score,
            'match_strategy' => $this->matchStrategy,
            'matched_fields' => $this->matchedFields,
            'context_score' => $this->contextScore,
            'safe_metadata' => $this->safeMetadata,
        ];
    }
}
