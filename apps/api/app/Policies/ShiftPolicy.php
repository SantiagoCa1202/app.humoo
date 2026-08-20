<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.view');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $shift->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($shift->workspace_id, 'members.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.manage');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $shift->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($shift->workspace_id, 'members.manage');
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $this->update($user, $shift);
    }
}
