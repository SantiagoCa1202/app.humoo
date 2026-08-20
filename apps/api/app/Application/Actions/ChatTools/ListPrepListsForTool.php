<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\PrepListResource;
use App\Models\PrepList;

class ListPrepListsForTool
{
    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 4), 12));
        $eventId = trim((string) ($filters['event_id'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $activeOnly = array_key_exists('active_only', $filters)
            ? (bool) $filters['active_only']
            : true;

        $prepLists = PrepList::query()
            ->where('workspace_id', $workspaceId)
            ->when($eventId !== '', fn ($builder) => $builder->where('event_id', $eventId))
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when(
                $activeOnly && $status === '',
                fn ($builder) => $builder->whereIn('status', ['active', 'in_progress', 'review', 'approved'])
            )
            ->with($this->relations())
            ->orderByRaw("case when status in ('active', 'in_progress') then 0 else 1 end")
            ->orderByRaw('production_starts_at is null')
            ->orderBy('production_starts_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $items = $prepLists->map(function (PrepList $prepList): array {
            $resource = (new PrepListResource($prepList))->resolve();

            return [
                'prep_list' => $resource,
                'progress' => $resource['progress'] ?? null,
            ];
        })->values()->all();

        return [
            'count' => count($items),
            'items' => $items,
        ];
    }

    private function relations(): array
    {
        return [
            'completedBy',
            'createdBy',
            'currentVersionRecord.approvedBy',
            'currentVersionRecord.createdBy',
            'currentVersionRecord.lockedBy',
            'currentVersionRecord.menuVersion',
            'currentVersionRecord.sections.items.assignments.assignedBy',
            'currentVersionRecord.sections.items.assignments.membership.role',
            'currentVersionRecord.sections.items.assignments.membership.teams',
            'currentVersionRecord.sections.items.assignments.membership.user',
            'currentVersionRecord.sections.items.actualUnit',
            'currentVersionRecord.sections.items.completedBy',
            'currentVersionRecord.sections.items.createdBy',
            'currentVersionRecord.sections.items.recipe',
            'currentVersionRecord.sections.items.recipeVersion',
            'currentVersionRecord.sections.items.unit',
            'currentVersionRecord.sections.items.updatedBy',
            'currentVersionRecord.sections.items.yieldUnit',
            'event',
            'updatedBy',
        ];
    }
}
