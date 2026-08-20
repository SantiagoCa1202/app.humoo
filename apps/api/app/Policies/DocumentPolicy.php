<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission(
            $workspace->id,
            'events.view'
        );
    }

    public function view(User $user, Document $document): bool
    {
        $workspace = app('currentWorkspace');

        return $document->workspace_id === $workspace->id
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
        ) || $user->hasWorkspacePermission(
            $workspace->id,
            'events.edit'
        );
    }

    public function update(User $user, Document $document): bool
    {
        $workspace = app('currentWorkspace');

        return $document->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'events.edit'
            );
    }

    public function delete(User $user, Document $document): bool
    {
        $workspace = app('currentWorkspace');

        return $document->workspace_id === $workspace->id
            && $user->hasWorkspacePermission(
                $workspace->id,
                'events.delete'
            );
    }
}
