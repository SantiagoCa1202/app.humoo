<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'events.view'
        );
    }

    public function view(
        User $user,
        Event $event
    ): bool {
        $workspace = app('currentWorkspace');

        return $event->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'events.view'
            );
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'events.create'
        );
    }

    public function update(User $user, Event $event): bool
    {
        $workspace = app('currentWorkspace');

        return $event->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'events.edit'
            );
    }

    public function delete(User $user, Event $event): bool
    {
        $workspace = app('currentWorkspace');

        return $event->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'events.delete'
            );
    }
}
