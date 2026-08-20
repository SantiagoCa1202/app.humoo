<?php

namespace App\Policies;

use App\Models\Beo;
use App\Models\User;

class BeoPolicy
{
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
