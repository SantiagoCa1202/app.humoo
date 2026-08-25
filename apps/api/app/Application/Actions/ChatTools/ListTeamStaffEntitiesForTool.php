<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\ShiftResource;
use App\Http\Resources\StationResource;
use App\Http\Resources\TeamResource;
use App\Http\Resources\WorkspaceMemberReferenceResource;
use App\Models\Shift;
use App\Models\Station;
use App\Models\Team;
use App\Models\WorkspaceMembership;

class ListTeamStaffEntitiesForTool
{
    public function execute(string $workspaceId, string $type, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 12), 50));
        $search = trim((string) ($filters['search'] ?? $filters['entity_search'] ?? ''));
        $query = match ($type) {
            'team' => Team::query()->where('workspace_id', $workspaceId)->with(['leadMembership.user', 'members.user', 'members.role', 'stations']),
            'station' => Station::query()->where('workspace_id', $workspaceId)->with('team'),
            'shift' => Shift::query()->where('workspace_id', $workspaceId)->with(['membership.user', 'membership.role', 'team', 'station.team', 'event', 'conflicts.membership.user']),
            'availability' => WorkspaceMembership::query()->where('workspace_id', $workspaceId)->with(['user', 'role', 'teams', 'availability', 'availabilityRules']),
            default => throw new \InvalidArgumentException('Unsupported team staff entity type.'),
        };

        $query
            ->when($search !== '' && in_array($type, ['team', 'station'], true), function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')->orWhere('key', 'like', '%'.$search.'%');
                });
            })
            ->when($type === 'shift' && filled($filters['membership_id'] ?? null), fn ($builder) => $builder->where('membership_id', $filters['membership_id']))
            ->when($type === 'shift' && filled($filters['team_id'] ?? null), fn ($builder) => $builder->where('team_id', $filters['team_id']))
            ->when($type === 'shift' && filled($filters['station_id'] ?? null), fn ($builder) => $builder->where('station_id', $filters['station_id']))
            ->when($type === 'shift' && filled($filters['from'] ?? null), fn ($builder) => $builder->where('ends_at', '>=', $filters['from']))
            ->when($type === 'shift' && filled($filters['to'] ?? null), fn ($builder) => $builder->where('starts_at', '<=', $filters['to']))
            ->when($type === 'availability' && filled($filters['membership_id'] ?? null), fn ($builder) => $builder->whereKey($filters['membership_id']))
            ->when($type === 'shift', fn ($builder) => $builder->orderBy('starts_at'))
            ->when(in_array($type, ['team', 'station'], true), fn ($builder) => $builder->orderBy('name'))
            ->when($type === 'availability', fn ($builder) => $builder->orderBy('created_at'));

        $items = $query->limit($limit)->get()->map(function ($entity) use ($type): array {
            return match ($type) {
                'team' => (new TeamResource($entity))->resolve(),
                'station' => (new StationResource($entity))->resolve(),
                'shift' => (new ShiftResource($entity))->resolve(),
                'availability' => [
                    'member' => (new WorkspaceMemberReferenceResource($entity))->resolve(),
                    'records' => $entity->availability->map(fn ($record) => [
                        'id' => $record->id, 'membership_id' => $record->membership_id,
                        'starts_at' => $record->starts_at?->toIso8601String(), 'ends_at' => $record->ends_at?->toIso8601String(),
                        'timezone' => $record->timezone, 'available' => $record->available, 'type' => $record->type,
                    ])->values()->all(),
                    'rules' => $entity->availabilityRules->map(fn ($rule) => [
                        'id' => $rule->id, 'day_of_week' => $rule->day_of_week, 'starts_at' => $rule->starts_at,
                        'ends_at' => $rule->ends_at, 'timezone' => $rule->timezone, 'available' => $rule->available,
                    ])->values()->all(),
                ],
            };
        })->values()->all();

        return ['count' => count($items), 'items' => $items];
    }
}
