<?php

namespace App\Policies;

use App\Models\Station;
use App\Models\User;

class StationPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.view');
    }

    public function view(User $user, Station $station): bool
    {
        return $station->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($station->workspace_id, 'members.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'members.manage');
    }

    public function update(User $user, Station $station): bool
    {
        return $station->workspace_id === app('currentWorkspace')->id
            && $user->hasWorkspacePermission($station->workspace_id, 'members.manage');
    }

    public function delete(User $user, Station $station): bool
    {
        return $this->update($user, $station);
    }
}
