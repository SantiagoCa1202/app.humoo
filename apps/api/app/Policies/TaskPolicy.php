<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $task->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($task->workspace_id, 'tasks.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        return $task->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($task->workspace_id, 'tasks.edit');
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($task->workspace_id, 'tasks.delete');
    }
}
