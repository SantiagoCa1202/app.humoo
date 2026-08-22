<?php

namespace App\Listeners\Notifications;

use App\Data\Notifications\NotificationMessage;
use App\Events\Tasks\TaskAssigned;
use App\Models\Task;
use App\Models\WorkspaceMembership;
use App\Services\NotificationService;

class SendTaskAssignedNotification
{
    public function __construct(
        private NotificationService $notifications
    ) {
    }

    public function handle(TaskAssigned $event): void
    {
        $task = Task::query()
            ->where('workspace_id', $event->workspaceId)
            ->whereKey($event->taskId)
            ->first();
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $event->workspaceId)
            ->whereKey($event->membershipId)
            ->where('status', 'active')
            ->first();

        if (!$task || !$membership) {
            return;
        }

        $this->notifications->send(new NotificationMessage(
            workspaceId: $event->workspaceId,
            recipientUserId: $membership->user_id,
            eventKey: 'task.assigned',
            type: 'action_required',
            priority: $task->priority === 'urgent' ? 'high' : 'normal',
            title: 'notifications.taskAssignedTitle',
            body: 'notifications.taskAssignedBody',
            entityType: 'task',
            entityId: $task->id,
            actionKey: 'task.detail',
            actionPayload: ['task_id' => $task->id],
            payload: ['task_title' => $task->title],
            deduplicationKey: "task.assigned:{$task->id}:{$membership->id}",
        ));
    }
}
