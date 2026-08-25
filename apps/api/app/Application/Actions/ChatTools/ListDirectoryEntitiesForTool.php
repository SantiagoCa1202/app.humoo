<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\ClientResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\VenueResource;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Venue;

class ListDirectoryEntitiesForTool
{
    public function execute(string $workspaceId, string $type, array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $query = match ($type) {
            'client' => Client::query()->with('primaryContact')->withCount('contacts'),
            'contact' => Contact::query()->with('client'),
            'venue' => Venue::query(),
            default => null,
        };

        if ($query === null) {
            return ['count' => 0, 'items' => []];
        }

        $query->where('workspace_id', $workspaceId)
            ->when($search !== '', function ($builder) use ($search, $type): void {
                $columns = match ($type) {
                    'client' => ['name', 'company_name', 'email', 'phone'],
                    'contact' => ['first_name', 'last_name', 'display_name', 'email', 'phone'],
                    default => ['name', 'city', 'state', 'contact_name'],
                };
                $builder->where(function ($searchQuery) use ($columns, $search): void {
                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $searchQuery->{$method}($column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->when(!empty($filters['client_id']) && $type === 'contact', fn ($builder) => $builder->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']) && in_array($type, ['client', 'venue'], true), fn ($builder) => $builder->where('status', $filters['status']))
            ->orderBy($type === 'contact' ? 'first_name' : 'name')
            ->limit(max(1, min((int) ($filters['limit'] ?? 6), 25)));

        $items = $query->get()->map(function ($entity) use ($type): array {
            return match ($type) {
                'client' => (new ClientResource($entity))->resolve(),
                'contact' => (new ContactResource($entity))->resolve(),
                'venue' => (new VenueResource($entity))->resolve(),
            };
        })->values()->all();

        return ['count' => count($items), 'items' => $items];
    }
}
