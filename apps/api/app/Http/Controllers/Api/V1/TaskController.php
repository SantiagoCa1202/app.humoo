<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Tasks\CreateTask;
use App\Application\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) ($request->input('search') ?? $request->input('filter.search', '')));
        $eventId = trim((string) ($request->input('event_id') ?? $request->input('filter.event_id', '')));
        $teamId = trim((string) ($request->input('team_id') ?? $request->input('filter.team_id', '')));
        $stationId = trim((string) ($request->input('station_id') ?? $request->input('filter.station_id', '')));
        $dueFrom = trim((string) ($request->input('due_from') ?? $request->input('filter.due_from', '')));
        $dueTo = trim((string) ($request->input('due_to') ?? $request->input('filter.due_to', '')));
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $overdue = $request->boolean('overdue');
        $unassigned = $request->boolean('unassigned');
        $statuses = $this->parseArrayFilter($request->input('status', $request->input('filter.status', [])));
        $priorities = $this->parseArrayFilter($request->input('priority', $request->input('filter.priority', [])));
        $assigneeIds = $this->parseArrayFilter(
            $request->input('assignee_id', $request->input('assignee_ids', $request->input('filter.assignee_id', [])))
        );

        $tasks = Task::query()
            ->where('workspace_id', $workspace->id)
            ->with($this->taskRelations())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($priorities !== [], fn ($query) => $query->whereIn('priority', $priorities))
            ->when($eventId !== '', fn ($query) => $query->where('event_id', $eventId))
            ->when($teamId !== '', fn ($query) => $query->where('team_id', $teamId))
            ->when($stationId !== '', fn ($query) => $query->where('station_id', $stationId))
            ->when($assigneeIds !== [], function ($query) use ($assigneeIds): void {
                $query->whereHas('assignments', fn ($assignmentQuery) => $assignmentQuery->whereIn('membership_id', $assigneeIds));
            })
            ->when($dueFrom !== '', fn ($query) => $query->where('due_at', '>=', $dueFrom))
            ->when($dueTo !== '', fn ($query) => $query->where('due_at', '<=', $dueTo))
            ->when($overdue, function ($query): void {
                $query
                    ->whereNotNull('due_at')
                    ->whereNotIn('status', ['done', 'cancelled'])
                    ->where('due_at', '<', now());
            })
            ->when($unassigned, fn ($query) => $query->doesntHave('assignments'))
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderByDesc('updated_at')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => TaskResource::collection(collect($tasks->items())),
            'path' => $tasks->path(),
            'per_page' => $tasks->perPage(),
            'next_cursor' => $tasks->nextCursor()?->encode(),
            'next_page_url' => $tasks->nextPageUrl(),
            'prev_cursor' => $tasks->previousCursor()?->encode(),
            'prev_page_url' => $tasks->previousPageUrl(),
        ]);
    }

    public function store(
        StoreTaskRequest $request,
        CreateTask $action,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('create', Task::class);

        $workspace = app('currentWorkspace');
        $task = $action->execute(
            $workspace->id,
            $request->user()?->id,
            $request->validated()
        );
        $task = $this->loadTask($task);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()?->id,
            'task.created',
            Task::class,
            $task->id,
            null,
            $task->toArray()
        );

        return response()->json([
            'data' => new TaskResource($task),
        ], 201);
    }

    public function show(Task $task)
    {
        $workspace = app('currentWorkspace');

        abort_unless($task->workspace_id === $workspace->id, 404);
        $this->authorize('view', $task);

        return response()->json([
            'data' => new TaskResource($this->loadTask($task)),
        ]);
    }

    public function update(
        UpdateTaskRequest $request,
        Task $task,
        UpdateTask $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($task->workspace_id === $workspace->id, 404);
        $this->authorize('update', $task);

        $before = $task->toArray();
        $updated = $action->execute(
            $task,
            $request->integer('version'),
            $request->safe()->except('version'),
            $request->user()?->id
        );

        if (!$updated) {
            return response()->json([
                'message' => 'Resource conflict.',
                'code' => 'VERSION_CONFLICT',
                'data' => (new TaskResource(
                    $this->loadTask(
                        Task::query()
                            ->whereKey($task->getKey())
                            ->where('workspace_id', $workspace->id)
                            ->firstOrFail()
                    )
                ))->resolve(),
            ], 409);
        }

        $updated = $this->loadTask($updated);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()?->id,
            'task.updated',
            Task::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new TaskResource($updated),
        ]);
    }

    public function destroy(
        Request $request,
        Task $task,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($task->workspace_id === $workspace->id, 404);
        $this->authorize('delete', $task);

        $before = $this->loadTask($task)->toArray();

        DB::transaction(function () use ($task): void {
            $task->forceDelete();
        });

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()?->id,
            'task.deleted',
            Task::class,
            $task->id,
            $before,
            null
        );

        return response()->noContent();
    }

    private function loadTask(Task $task): Task
    {
        return $task->load($this->taskRelations());
    }

    private function parseArrayFilter(mixed $value): array
    {
        return collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map(fn ($entry) => trim((string) $entry))
            ->filter(fn ($entry) => $entry !== '')
            ->values()
            ->all();
    }

    private function taskRelations(): array
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
