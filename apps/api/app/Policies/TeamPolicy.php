<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $team->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($team->workspace_id, 'members.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return $team->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($team->workspace_id, 'members.manage');
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }
}
