<?php

namespace App\AI\Orchestration;

final class ContinuationResolution
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $status,
        public string $source = 'none',
        public ?string $continuationId = null,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $entityType = null,
        public ?string $actionKey = null,
        public ?string $unresolvedField = null,
        public float $confidence = 0.0,
        public ?string $expiresAt = null,
        public array $data = [],
    ) {
    }

    public static function notApplicable(): self
    {
        return new self(status: 'not_applicable');
    }
}
