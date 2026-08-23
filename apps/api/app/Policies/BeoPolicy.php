<?php

namespace App\Policies;

use App\Models\Beo;
use App\Models\User;

class BeoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasWorkspacePermission(app('currentWorkspace')->id, 'events.view');
    }

    public function create(User $user): bool
    {
        $workspaceId = app('currentWorkspace')->id;

        return $user->hasWorkspacePermission($workspaceId, 'events.create')
            || $user->hasWorkspacePermission($workspaceId, 'events.edit');
    }
    public function view(User $user, Beo $beo): bool
    {
        $workspace = app('currentWorkspace');

        return $beo->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'events.view'
            );
    }

    public function update(User $user, Beo $beo): bool
    {
        $workspace = app('currentWorkspace');

        return $beo->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'events.edit'
            );
    }
}
