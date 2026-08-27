<?php

namespace App\AI\EntityResolution;

class EntityResolutionResult
{
    /** @param EntityCandidate[] $candidates */
    public function __construct(
        public string $status,
        public ?EntityCandidate $resolved = null,
        public array $candidates = [],
        public bool $aiFallbackUsed = false,
        public ?string $strategy = null,
        public ?float $topScore = null,
        public ?float $scoreGap = null,
        public ?string $localStatus = null,
        public ?string $fallbackReason = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'resolved' => $this->resolved?->toArray(),
            'candidates' => array_map(fn (EntityCandidate $candidate) => $candidate->toArray(), $this->candidates),
            'ai_fallback_used' => $this->aiFallbackUsed,
            'strategy' => $this->strategy,
            'top_score' => $this->topScore,
            'score_gap' => $this->scoreGap,
            'local_status' => $this->localStatus,
            'fallback_reason' => $this->fallbackReason,
        ];
    }
}
