<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'venues.view');
    }

    public function view(User $user, Venue $venue): bool
    {
        $workspace = app('currentWorkspace');

        return $venue->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'venues.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'venues.create');
    }

    public function update(User $user, Venue $venue): bool
    {
        $workspace = app('currentWorkspace');

        return $venue->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'venues.edit');
    }

    public function delete(User $user, Venue $venue): bool
    {
        $workspace = app('currentWorkspace');

        return $venue->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'venues.delete');
    }
}
