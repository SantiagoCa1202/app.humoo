<?php

namespace App\Policies;

use App\Models\Availability;
use App\Models\User;

class AvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.manage');
    }

    public function update(User $user, Availability $availability): bool
    {
        return $availability->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($availability->workspace_id, 'members.manage');
    }
}
