<?php

namespace App\Policies;

use App\Models\BeoImportBatch;
use App\Models\User;

class BeoImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasWorkspacePermission(app('currentWorkspace')->id, 'events.view');
    }

    public function view(User $user, BeoImportBatch $batch): bool
    {
        return $batch->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($batch->workspace_id, 'events.view');
    }

    public function create(User $user): bool
    {
        $workspaceId = app('currentWorkspace')->id;

        return $user->hasWorkspacePermission($workspaceId, 'events.create')
            || $user->hasWorkspacePermission($workspaceId, 'events.edit');
    }
}
