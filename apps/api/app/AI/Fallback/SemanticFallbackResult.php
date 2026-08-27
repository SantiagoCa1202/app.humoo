<?php

namespace App\AI\Fallback;

class SemanticFallbackResult
{
    public function __construct(
        public string $status,
        public ?string $resolvedActionKey = null,
        public array $payloadPatch = [],
        public array $searchRequests = [],
        public array $selectedCandidateIds = [],
        public ?float $confidence = null,
        public bool $needsClarification = false,
        public array $clarificationFields = [],
        public ?string $reasonCode = null,
        public bool $providerUsed = false,
    ) {
    }
}
