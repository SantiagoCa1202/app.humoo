<?php

use App\Models\ConversationParticipant;
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

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId): bool {
    return ConversationParticipant::query()
        ->where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();
});
