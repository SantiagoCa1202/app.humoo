<?php

namespace App\Application\Actions\ChatTools;

use App\AI\EntityResolution\EntityReferenceResolver;
use App\AI\EntityResolution\EntityResolutionRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ListTasksForTool
{
    public function __construct(private ?EntityReferenceResolver $referenceResolver = null)
    {
    }

    public function execute(string $workspaceId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 12), 50));
        $search = trim((string) ($filters['search'] ?? ''));
        $statuses = $this->listFilterValues($filters['status'] ?? null);
        $priorities = $this->listFilterValues($filters['priority'] ?? null);
        $dueFrom = $this->dateValue($filters['due_from'] ?? null);
        $dueTo = $this->dateValue($filters['due_to'] ?? null);

        $tasks = Task::query()
            ->where('workspace_id', $workspaceId)
            ->with($this->relations())
            ->when($search !== '', fn ($query) => $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            }))
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($priorities !== [], fn ($query) => $query->whereIn('priority', $priorities))
            ->when($dueFrom !== null, fn ($query) => $query->where('due_at', '>=', $dueFrom))
            ->when($dueTo !== null, fn ($query) => $query->where('due_at', '<=', $dueTo))
            ->when((bool) ($filters['overdue'] ?? false), fn ($query) => $query
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereNotIn('status', ['done', 'skipped', 'cancelled']))
            ->when((bool) ($filters['unassigned'] ?? false), fn ($query) => $query->whereDoesntHave('assignments', fn ($assignment) => $assignment->where('status', '!=', 'removed')))
            ->when(filled($filters['membership_id'] ?? null), fn ($query) => $query->whereHas('assignments', fn ($assignment) => $assignment->where('membership_id', $filters['membership_id'])))
            ->when(filled($filters['member_search'] ?? null), fn ($query) => $query->whereHas('assignments.membership.user', function ($user) use ($filters): void {
                $term = trim((string) $filters['member_search']);
                $user->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%');
            }))
            ->when(filled($filters['team_id'] ?? null), fn ($query) => $query->where('team_id', $filters['team_id']))
            ->when(filled($filters['team_search'] ?? null), fn ($query) => $query->whereHas('team', fn ($team) => $team->where('name', 'like', '%'.trim((string) $filters['team_search']).'%')))
            ->when(filled($filters['station_id'] ?? null), fn ($query) => $query->where('station_id', $filters['station_id']))
            ->when(filled($filters['station_search'] ?? null), fn ($query) => $query->whereHas('station', fn ($station) => $station->where('name', 'like', '%'.trim((string) $filters['station_search']).'%')))
            ->when(filled($filters['event_id'] ?? null), fn ($query) => $query->where('event_id', $filters['event_id']))
            ->when(filled($filters['event_search'] ?? null), fn ($query) => $query->whereHas('event', fn ($event) => $event->where('name', 'like', '%'.trim((string) $filters['event_search']).'%')))
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

    public function find(string $workspaceId, ?string $id = null, ?string $search = null, array $refs = [], ?string $actorId = null, ?string $actionKey = null): array
    {
        $resolvedId = trim((string) $id);
        if ($resolvedId === '') {
            $reference = collect($refs)
                ->filter(fn (mixed $ref): bool => is_array($ref) && ($ref['type'] ?? null) === 'task')
                ->sortByDesc(fn (array $ref): int => ($ref['role'] ?? null) === 'active' ? 1 : 0)
                ->first();
            if ($search === null || in_array(mb_strtolower(trim((string) $search)), ['that', 'this', 'ese', 'esa'], true)) {
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

        if ($matches->isEmpty() && $this->referenceResolver !== null) {
            try {
                $fallback = $this->referenceResolver->resolve(new EntityResolutionRequest(
                    workspaceId: $workspaceId,
                    actorId: $actorId,
                    conversationId: null,
                    actionKey: $actionKey,
                    entityType: 'task',
                    unresolvedField: 'task_id',
                    rawReference: $term,
                    conversationReferences: $refs,
                    riskLevel: 'read',
                ));

                if ($fallback->resolved?->entity instanceof Task) {
                    return ['status' => 'resolved', 'entity' => $fallback->resolved->entity];
                }

                if ($fallback->candidates !== []) {
                    return [
                        'status' => $fallback->status === 'suggested_match' ? 'suggested_match' : 'ambiguous',
                        'entity' => null,
                        'candidates' => collect($fallback->candidates)->map(fn ($candidate): array => [
                            'id' => $candidate->entityId,
                            'name' => $candidate->displayName,
                            'safe_metadata' => $candidate->safeMetadata,
                        ])->values()->all(),
                    ];
                }
            } catch (\Throwable) {
                // A resolver outage must not turn a normal task lookup into a 500.
            }
        }

        return [
            'status' => $matches->count() === 1 ? 'resolved' : ($matches->isEmpty() ? 'not_found' : 'ambiguous'),
            'entity' => $matches->count() === 1 ? $matches->first() : null,
            'candidates' => $matches->map(fn (Task $task): array => ['id' => $task->id, 'name' => $task->title])->values()->all(),
        ];
    }

    /** @return Collection<int, Task> */
    public function findMany(string $workspaceId, array $filters = []): Collection
    {
        $result = $this->execute($workspaceId, [...$filters, 'limit' => 50]);
        $ids = collect($result['items'] ?? [])->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $ids)
            ->with($this->relations())
            ->get()
            ->sortBy(fn (Task $task): int => (int) $ids->search($task->id))
            ->values();
    }

    private function relations(): array
    {
        return [
            'assignments.assignedBy', 'assignments.membership.role', 'assignments.membership.user',
            'completedBy', 'createdBy', 'event', 'station.team', 'team', 'updatedBy',
        ];
    }

    private function listFilterValues(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function dateValue(mixed $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
