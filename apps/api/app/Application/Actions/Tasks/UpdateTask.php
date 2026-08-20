<?php

namespace App\Application\Actions\Tasks;

use App\Models\Task;
use Illuminate\Support\Facades\DB;

class UpdateTask
{
    public function __construct(
        private CreateTask $createTask
    ) {
    }

    public function execute(
        Task $task,
        int $expectedVersion,
        array $attributes,
        ?string $userId = null
    ): ?Task {
        return DB::transaction(function () use ($attributes, $expectedVersion, $task, $userId): ?Task {
            $payload = (fn () => $this->preparePayload(
                $task->workspace_id,
                $attributes,
                $userId,
                $task
            ))->call($this->createTask);

            $updated = Task::query()
                ->whereKey($task->getKey())
                ->where('workspace_id', $task->workspace_id)
                ->where('version', $expectedVersion)
                ->update([
                    ...$payload,
                    'updated_at' => now(),
                    'updated_by' => $userId,
                    'version' => $expectedVersion + 1,
                ]);

            if ($updated === 0) {
                return null;
            }

            $freshTask = Task::query()
                ->whereKey($task->getKey())
                ->where('workspace_id', $task->workspace_id)
                ->firstOrFail();

            if (array_key_exists('assignments', $attributes)) {
                (fn () => $this->syncAssignments(
                    $freshTask,
                    $task->workspace_id,
                    $attributes['assignments'] ?? [],
                    $userId
                ))->call($this->createTask);
            }

            return Task::query()
                ->whereKey($task->getKey())
                ->where('workspace_id', $task->workspace_id)
                ->first();
        });
    }
}
