<?php

namespace App\AI\EntityResolution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EloquentEntityResolverAdapter implements EntityResolverAdapter
{
    /** @param array<string> $searchFields */
    public function __construct(
        private string $type,
        private string $modelClass,
        private array $searchFields,
        private array $relations = [],
        private ?string $ability = 'view',
        private $label = null,
        private $metadata = null,
    ) {
    }

    public function entityType(): string { return $this->type; }

    public function findById(EntityResolutionRequest $request, string $id): ?EntityCandidate
    {
        $query = ($this->modelClass)::query()->where('workspace_id', $request->workspaceId)->with($this->relations);
        $entity = $query->whereKey($id)->first();

        return $entity instanceof Model && $this->allowed($request, $entity) ? $this->candidate($entity) : null;
    }

    public function candidates(EntityResolutionRequest $request, int $limit): array
    {
        $query = ($this->modelClass)::query()->where('workspace_id', $request->workspaceId)->with($this->relations);
        foreach ($request->contextConstraints as $field => $value) {
            if ($value !== null && $field === 'prep_list_id' && $this->type === 'prep_item') {
                $query->whereHas('section.version', fn ($version) => $version->where('prep_list_id', $value));
                continue;
            }
            if ($value !== null && in_array($field, ['event_id', 'menu_id', 'prep_list_id', 'client_id'], true)) {
                $query->where($field, $value);
            }
        }

        return $query->limit($limit)->get()
            ->filter(fn (Model $entity): bool => $this->allowed($request, $entity))
            ->map(fn (Model $entity): EntityCandidate => $this->candidate($entity))
            ->all();
    }

    private function allowed(EntityResolutionRequest $request, Model $entity): bool
    {
        if ($this->ability === null || !$request->actorId) {
            return true;
        }

        $actor = \App\Models\User::query()->find($request->actorId);

        return $actor !== null && Gate::forUser($actor)->allows($this->ability, $entity);
    }

    private function candidate(Model $entity): EntityCandidate
    {
        $label = is_callable($this->label) ? ($this->label)($entity) : (string) ($entity->name ?? $entity->title ?? $entity->getKey());
        $metadata = is_callable($this->metadata) ? ($this->metadata)($entity) : [];
        $values = [$label];
        foreach ($this->searchFields as $field) {
            $value = data_get($entity, $field);
            if (is_scalar($value) && (string) $value !== '') {
                $values[$field] = (string) $value;
            }
        }

        return new EntityCandidate((string) $entity->getKey(), $this->type, $label, $values, $metadata, $entity);
    }
}
