<?php

namespace App\AI\EntityResolution;

use Illuminate\Database\Eloquent\Model;

class DirectoryEntityResolver
{
    public function __construct(private EntityReferenceResolver $referenceResolver)
    {
    }

    public function resolve(
        string $workspaceId,
        string $type,
        ?string $id = null,
        ?string $search = null,
        array $entityRefs = []
    ): array {
        if (!in_array($type, ['client', 'contact', 'event', 'venue'], true)) {
            return ['status' => 'not_found', 'entity' => null, 'matches' => []];
        }
        $result = $this->referenceResolver->resolve(new EntityResolutionRequest(
            workspaceId: $workspaceId,
            actorId: null,
            conversationId: null,
            actionKey: null,
            entityType: $type,
            unresolvedField: 'entity_id',
            rawReference: $search,
            knownPayload: ['entity_id' => $id],
            conversationReferences: $entityRefs,
            riskLevel: 'write',
            originalMessage: $search,
        ));

        return [
            'status' => $result->status === 'not_found' ? 'not_found' : $result->status,
            'entity' => $result->resolved?->entity,
            'matches' => array_values(array_filter(array_map(static fn (EntityCandidate $candidate) => $candidate->entity, $result->candidates))),
            'candidates' => array_map(static fn (EntityCandidate $candidate): array => ['id' => $candidate->entityId, 'name' => $candidate->displayName], $result->candidates),
        ];
    }

    public function label(Model $entity, string $type): string
    {
        return match ($type) {
            'contact' => trim((string) ($entity->display_name ?: implode(' ', array_filter([
                $entity->first_name,
                $entity->last_name,
            ])))),
            'event' => (string) ($entity->name ?: $entity->event_number),
            default => (string) $entity->name,
        } ?: (string) $entity->getKey();
    }

}
