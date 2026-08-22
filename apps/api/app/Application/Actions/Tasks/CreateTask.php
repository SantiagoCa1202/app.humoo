<?php

namespace App\Application\Actions\Tasks;

use App\Events\Tasks\TaskAssigned;
use App\Models\Station;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    public function execute(
        string $workspaceId,
        ?string $userId,
        array $attributes
    ): Task {
        return DB::transaction(function () use ($attributes, $userId, $workspaceId): Task {
            $payload = $this->preparePayload($workspaceId, $attributes, $userId);

            $task = Task::query()->create([
                ...$payload,
                'workspace_id' => $workspaceId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'version' => 1,
            ]);

            $this->syncAssignments(
                $task,
                $workspaceId,
                $attributes['assignments'] ?? [],
                $userId
            );

            return $task;
        });
    }

    private function preparePayload(
        string $workspaceId,
        array $attributes,
        ?string $userId,
        ?Task $existingTask = null
    ): array {
        $status = $attributes['status'] ?? $existingTask?->status ?? 'todo';
        $stationId = $attributes['station_id'] ?? $existingTask?->station_id;
        $teamId = $attributes['team_id'] ?? $existingTask?->team_id;

        if ($stationId) {
            $station = Station::query()
                ->where('workspace_id', $workspaceId)
                ->find($stationId);

            if ($station?->team_id && !$teamId) {
                $teamId = $station->team_id;
            }
        }

        return [
            'title' => $attributes['title'] ?? $existingTask?->title,
            'description' => $attributes['description'] ?? $existingTask?->description,
            'type' => $attributes['type'] ?? $existingTask?->type ?? 'general',
            'status' => $status,
            'priority' => $attributes['priority'] ?? $existingTask?->priority ?? 'normal',
            'event_id' => $attributes['event_id'] ?? $existingTask?->event_id,
            'team_id' => $teamId,
            'station_id' => $stationId,
            'starts_at' => $attributes['starts_at'] ?? $existingTask?->starts_at,
            'due_at' => $attributes['due_at'] ?? $existingTask?->due_at,
            'blocked_reason' => $status === 'blocked'
                ? ($attributes['blocked_reason'] ?? $existingTask?->blocked_reason)
                : null,
            'source' => $attributes['source'] ?? $existingTask?->source ?? 'user',
            'source_type' => $attributes['source_type'] ?? $existingTask?->source_type,
            'source_id' => $attributes['source_id'] ?? $existingTask?->source_id,
            'metadata' => $attributes['metadata'] ?? $existingTask?->metadata,
            'completed_at' => $status === 'done'
                ? ($existingTask?->completed_at ?? now())
                : null,
            'completed_by' => $status === 'done'
                ? ($existingTask?->completed_by ?? $userId)
                : null,
        ];
    }

    private function syncAssignments(
        Task $task,
        string $workspaceId,
        array $assignments,
        ?string $userId
    ): void {
        $incoming = collect($assignments)
            ->filter(fn ($assignment) => filled($assignment['membership_id'] ?? null))
            ->values();
        $existingByMembership = $task->assignments()
            ->get()
            ->keyBy('membership_id');
        $primaryMembershipId = $incoming->firstWhere('is_primary', true)['membership_id']
            ?? $incoming->first()['membership_id']
            ?? null;

        foreach ($incoming as $assignment) {
            $membershipId = $assignment['membership_id'];
            /** @var TaskAssignment|null $existing */
            $existing = $existingByMembership->get($membershipId);
            $status = $this->resolveAssignmentStatus($task->status, $existing?->status, $assignment['status'] ?? null);
            $completedAt = $status === 'completed'
                ? ($existing?->completed_at ?? now())
                : null;

            TaskAssignment::query()->updateOrCreate(
                [
                    'task_id' => $task->id,
                    'membership_id' => $membershipId,
                ],
                [
                    'workspace_id' => $workspaceId,
                    'assigned_by' => $existing?->assigned_by ?? $userId,
                    'assigned_at' => $existing?->assigned_at ?? now(),
                    'completed_at' => $completedAt,
                    'is_primary' => $membershipId === $primaryMembershipId,
                    'status' => $status,
                ]
            );

            if (!$existing) {
                event(new TaskAssigned(
                    workspaceId: $workspaceId,
                    taskId: $task->id,
                    membershipId: $membershipId,
                    actorUserId: $userId,
                ));
            }
        }

        $task->assignments()
            ->when(
                $incoming->isNotEmpty(),
                fn ($query) => $query->whereNotIn('membership_id', $incoming->pluck('membership_id')->all()),
                fn ($query) => $query
            )
            ->delete();
    }

    private function resolveAssignmentStatus(
        string $taskStatus,
        ?string $existingStatus,
        ?string $requestedStatus
    ): string {
        if ($taskStatus === 'done') {
            return 'completed';
        }

        if ($taskStatus === 'cancelled') {
            return 'cancelled';
        }

        if (in_array($existingStatus, ['assigned', 'accepted', 'declined'], true)) {
            return $existingStatus;
        }

        if ($requestedStatus && $requestedStatus !== 'completed' && $requestedStatus !== 'cancelled') {
            return $requestedStatus;
        }

        return 'assigned';
    }
}
