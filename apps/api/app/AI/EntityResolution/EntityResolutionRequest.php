<?php

namespace App\AI\EntityResolution;

class EntityResolutionRequest
{
    public function __construct(
        public string $workspaceId,
        public ?string $actorId,
        public ?string $conversationId,
        public ?string $actionKey,
        public string $entityType,
        public string $unresolvedField,
        public ?string $rawReference = null,
        public array $knownPayload = [],
        public array $contextConstraints = [],
        public array $conversationReferences = [],
        public string $locale = 'en',
        public string $riskLevel = 'read',
    ) {
    }
}
