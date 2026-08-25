<?php

namespace App\AI\EntityResolution;

use App\Models\Shift;
use App\Models\Station;
use App\Models\Team;
use App\Models\WorkspaceMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TeamStaffEntityResolver
{
    public function resolve(
        string $workspaceId,
        string $type,
        ?string $id = null,
        ?string $search = null,
        array $references = []
    ): array {
        $model = $this->model($type);
        if ($model === null) {
            return ['status' => 'missing', 'matches' => []];
        }

        $query = $model::query()->where('workspace_id', $workspaceId)->with($this->relations($type));
        $resolvedId = trim((string) $id);
        if ($resolvedId === '') {
            $resolvedId = $this->referenceId($type, $references, $search);
        }
        if ($resolvedId !== '') {
            $entity = $query->whereKey($resolvedId)->first();
            return $entity
                ? ['status' => 'resolved', 'entity' => $entity, 'matches' => [$entity]]
                : ['status' => 'missing', 'matches' => []];
        }

        $term = Str::lower(trim((string) $search));
        if ($term === '') {
            return ['status' => 'missing', 'matches' => []];
        }

        if ($type === 'membership') {
            $matches = $query->whereHas('user', fn ($builder) => $builder->whereRaw('LOWER(name) like ?', ['%'.$term.'%']))->limit(6)->get();
            if ($matches->count() === 1) {
                return ['status' => 'resolved', 'entity' => $matches->first(), 'matches' => $matches->all()];
            }
            return [
                'status' => $matches->isEmpty() ? 'missing' : 'ambiguous', 'matches' => $matches->all(),
                'candidates' => $matches->map(fn (Model $entity): array => ['id' => $entity->getKey(), 'name' => $this->label($entity, $type)])->values()->all(),
            ];
        }

        if ($type === 'shift') {
            $matches = $query->whereHas('membership.user', fn ($builder) => $builder->whereRaw('LOWER(name) like ?', ['%'.$term.'%']))->limit(6)->get();
            if ($matches->count() === 1) {
                return ['status' => 'resolved', 'entity' => $matches->first(), 'matches' => $matches->all()];
            }
            if ($matches->count() > 0) {
                return ['status' => 'ambiguous', 'matches' => $matches->all(), 'candidates' => $matches->map(fn (Model $entity): array => ['id' => $entity->id, 'name' => $this->label($entity->membership, 'membership')])->values()->all()];
            }
            return ['status' => 'missing', 'matches' => []];
        }

        $matches = $query->where(function ($builder) use ($type, $term): void {
            foreach ($this->searchColumns($type) as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($column, 'like', '%'.$term.'%');
            }
        })->limit(6)->get();

        if ($matches->count() === 1) {
            return ['status' => 'resolved', 'entity' => $matches->first(), 'matches' => $matches->all()];
        }

        return [
            'status' => $matches->isEmpty() ? 'missing' : 'ambiguous',
            'matches' => $matches->all(),
            'candidates' => $matches->map(fn (Model $entity): array => [
                'id' => $entity->getKey(),
                'name' => $this->label($entity, $type),
            ])->values()->all(),
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

    private function searchColumns(string $type): array
    {
        return match ($type) {
            'membership' => [],
            'shift' => ['role', 'status', 'notes'],
            default => ['name', 'key', 'description'],
        };
    }

    private function referenceId(string $type, array $references, ?string $search): string
    {
        $term = Str::lower(trim((string) $search));
        if ($term !== '' && !in_array($term, ['that', 'this', 'ese', 'esa', 'this one'], true)) {
            return '';
        }

        $reference = collect($references)
            ->filter(fn (mixed $ref): bool => is_array($ref) && ($ref['type'] ?? null) === $type)
            ->sortByDesc(fn (array $ref): int => ($ref['role'] ?? null) === 'active' ? 1 : 0)
            ->first();

        return is_array($reference) ? (string) ($reference['id'] ?? '') : '';
    }
}
