<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\EventResource;
use App\Models\Event;

class ListEventsForTool
{
    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 6), 12));
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        $events = Event::query()
            ->where('workspace_id', $workspaceId)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('event_number', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('starts_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('starts_at', '<=', $dateTo))
            ->with($this->relations())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        return [
            'count' => $events->count(),
            'items' => EventResource::collection($events)->resolve(),
        ];
    }

    private function relations(): array
    {
        return [
            'client.primaryContact',
            'contact.client',
            'group',
            'venue',
        ];
    }
}
