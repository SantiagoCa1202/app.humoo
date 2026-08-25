<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\TaskResource;
use App\Models\Task;

class ListTasksForTool
{
    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 12), 50));
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $tasks = Task::query()
            ->where('workspace_id', $workspaceId)
            ->with($this->relations())
            ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            }))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
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

    public function find(string $workspaceId, ?string $id = null, ?string $search = null, array $refs = []): array
    {
        $resolvedId = trim((string) $id);
        if ($resolvedId === '') {
            $reference = collect($refs)
                ->filter(fn (mixed $ref): bool => is_array($ref) && ($ref['type'] ?? null) === 'task')
                ->sortByDesc(fn (array $ref): int => ($ref['role'] ?? null) === 'active' ? 1 : 0)
                ->first();
            if (in_array(mb_strtolower(trim((string) $search)), ['that', 'this', 'ese', 'esa'], true)) {
                $resolvedId = (string) ($reference['id'] ?? '');
            }
        }

        $query = Task::query()->where('workspace_id', $workspaceId)->with($this->relations());
        if ($resolvedId !== '') {
            $task = $query->whereKey($resolvedId)->first();
            return $task ? ['status' => 'resolved', 'entity' => $task] : ['status' => 'not_found'];
        }

        $term = trim((string) $search);
        if ($term === '') {
            return ['status' => 'not_found'];
        }

        $matches = $query->where(function ($builder) use ($term): void {
            $builder->where('title', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');
        })->limit(6)->get();

        return [
            'status' => $matches->count() === 1 ? 'resolved' : ($matches->isEmpty() ? 'not_found' : 'ambiguous'),
            'entity' => $matches->count() === 1 ? $matches->first() : null,
            'candidates' => $matches->map(fn (Task $task): array => ['id' => $task->id, 'name' => $task->title])->values()->all(),
        ];
    }

    private function relations(): array
    {
        return [
            'assignments.assignedBy', 'assignments.membership.role', 'assignments.membership.user',
            'completedBy', 'createdBy', 'event', 'station.team', 'team', 'updatedBy',
        ];
    }
}
