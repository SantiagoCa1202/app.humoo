<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\TaskResource;
use App\Models\Task;

class ListMyTasksForTool
{
    public function execute(
        string $workspaceId,
        string $membershipId,
        array $filters = []
    ): array {
        $limit = max(1, min((int) ($filters['limit'] ?? 5), 12));
        $status = trim((string) ($filters['status'] ?? ''));
        $overdueOnly = (bool) ($filters['overdue'] ?? false);

        $tasks = Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('assignments', function ($query) use ($membershipId): void {
                $query->where('membership_id', $membershipId);
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($overdueOnly, function ($query): void {
                $query
                    ->whereNotNull('due_at')
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->where('due_at', '<', now());
            })
            ->whereNotIn('status', ['done', 'cancelled'])
            ->with($this->relations())
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return [
            'count' => $tasks->count(),
            'items' => TaskResource::collection($tasks)->resolve(),
        ];
    }

    private function relations(): array
    {
        return [
            'assignments.assignedBy',
            'assignments.membership.role',
            'assignments.membership.user',
            'completedBy',
            'createdBy',
            'event',
            'station.team',
            'team',
            'updatedBy',
        ];
    }
}
