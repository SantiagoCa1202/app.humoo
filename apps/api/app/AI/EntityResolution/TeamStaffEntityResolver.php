<?php

namespace App\AI\EntityResolution;

use App\Models\Shift;
use App\Models\Station;
use App\Models\Team;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Model;

class TeamStaffEntityResolver
{
    public function __construct(private EntityReferenceResolver $referenceResolver)
    {
    }

    public function resolve(
        string $workspaceId,
        string $type,
        ?string $id = null,
        ?string $search = null,
        array $references = []
    ): array {
        if ($this->model($type) === null) {
            return ['status' => 'missing', 'matches' => []];
        }

        $result = $this->referenceResolver->resolve(new EntityResolutionRequest(
            workspaceId: $workspaceId,
            actorId: null,
            conversationId: null,
            actionKey: null,
            entityType: $type,
            unresolvedField: $type.'_id',
            rawReference: $search,
            knownPayload: [$type.'_id' => $id],
            conversationReferences: $references,
            riskLevel: 'write',
            originalMessage: $search,
        ));
        if ($result->status === 'resolved' && $result->resolved?->entityId) {
            $model = $this->model($type);
            $entity = $model::query()
                ->where('workspace_id', $workspaceId)
                ->with($this->relations($type))
                ->whereKey($result->resolved->entityId)
                ->first();

            return $entity
                ? ['status' => 'resolved', 'entity' => $entity, 'matches' => [$entity]]
                : ['status' => 'missing', 'matches' => []];
        }

        return [
            'status' => $result->status,
            'matches' => [],
            'candidates' => array_map(static fn (EntityCandidate $candidate): array => [
                'id' => $candidate->entityId,
                'name' => $candidate->displayName,
                'safe_metadata' => $candidate->safeMetadata,
            ], $result->candidates),
        ];
    }

    public function label(Model $entity, string $type): string
    {
        if ($type === 'membership') {
            return (string) ($entity->user?->name ?? $entity->getKey());
        }

        return (string) ($entity->name ?? $entity->getKey());
    }

    public function model(string $type): ?string
    {
        return match ($type) {
            'team' => Team::class,
            'station' => Station::class,
            'shift' => Shift::class,
            'membership' => WorkspaceMembership::class,
            default => null,
        };
    }

    private function relations(string $type): array
    {
        return match ($type) {
            'team' => ['leadMembership.user', 'members.user', 'members.role', 'stations'],
            'station' => ['team'],
            'shift' => ['membership.user', 'membership.role', 'team', 'station.team', 'event', 'conflicts.membership.user'],
            'membership' => ['user', 'role', 'teams'],
            default => [],
        };
    }

}
