<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        $workspace = app('currentWorkspace');

        return $client->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'clients.view');
    }

    public function create(User $user): bool
    {
        $workspace = app('currentWorkspace');

        return $user->hasWorkspacePermission($workspace->id, 'clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        $workspace = app('currentWorkspace');

        return $client->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'clients.edit');
    }

    public function delete(User $user, Client $client): bool
    {
        $workspace = app('currentWorkspace');

        return $client->workspace_id === $workspace->id
            && $user->hasWorkspacePermission($workspace->id, 'clients.delete');
    }
}
