<?php

namespace App\AI\EntityResolution;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DirectoryEntityResolver
{
    public function resolve(
        string $workspaceId,
        string $type,
        ?string $id = null,
        ?string $search = null,
        array $entityRefs = []
    ): array {
        $modelClass = $this->modelClass($type);
        if ($modelClass === null) {
            return ['status' => 'not_found', 'entity' => null, 'matches' => []];
        }

        $query = $modelClass::query()->where('workspace_id', $workspaceId);
        $resolvedId = trim((string) $id);

        if ($resolvedId === '') {
            $resolvedId = $this->referenceId($type, $entityRefs, $search);
        }

        if ($resolvedId !== '') {
            $entity = $query->whereKey($resolvedId)->first();

            return $entity
                ? ['status' => 'resolved', 'entity' => $entity, 'matches' => [$entity]]
                : ['status' => 'not_found', 'entity' => null, 'matches' => []];
        }

        $normalizedSearch = trim((string) $search);
        if ($normalizedSearch === '' || Str::lower($normalizedSearch) === 'that' || Str::lower($normalizedSearch) === 'ese') {
            return ['status' => 'not_found', 'entity' => null, 'matches' => []];
        }

        $terms = $this->searchColumns($type);
        $entities = $query
            ->where(function ($builder) use ($normalizedSearch, $terms): void {
                foreach ($terms as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}($column, 'like', '%'.$normalizedSearch.'%');
                }
            })
            ->limit(5)
            ->get();

        if ($entities->count() === 1) {
            return ['status' => 'resolved', 'entity' => $entities->first(), 'matches' => $entities->all()];
        }

        return [
            'status' => $entities->isEmpty() ? 'not_found' : 'ambiguous',
            'entity' => null,
            'matches' => $entities->all(),
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

    private function modelClass(string $type): ?string
    {
        return match ($type) {
            'client' => Client::class,
            'contact' => Contact::class,
            'event' => Event::class,
            'venue' => Venue::class,
            default => null,
        };
    }

    private function searchColumns(string $type): array
    {
        return match ($type) {
            'client' => ['name', 'company_name', 'email', 'phone'],
            'contact' => ['first_name', 'last_name', 'display_name', 'email', 'phone'],
            'event' => ['name', 'event_number', 'service_type', 'event_type'],
            'venue' => ['name', 'city', 'state', 'contact_name'],
            default => ['name'],
        };
    }

    private function referenceId(string $type, array $entityRefs, ?string $search): string
    {
        $normalizedSearch = Str::lower(trim((string) $search));
        if ($normalizedSearch !== '' && !in_array($normalizedSearch, ['that', 'this', 'ese', 'esa', 'this one', 'ese'], true)) {
            return '';
        }

        $reference = collect($entityRefs)
            ->filter(fn (mixed $ref): bool => is_array($ref) && ($ref['type'] ?? null) === $type)
            ->sortByDesc(fn (array $ref): int => ($ref['role'] ?? null) === 'active' ? 1 : 0)
            ->first();

        return is_array($reference) ? (string) ($reference['id'] ?? '') : '';
    }
}
