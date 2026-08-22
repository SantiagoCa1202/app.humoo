<?php

use App\Models\User;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workspace.{workspaceId}', function (User $user, string $workspaceId): bool {
    return WorkspaceMembership::query()
        ->where('workspace_id', $workspaceId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});
