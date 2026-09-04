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
    $workspaceId = ConversationParticipant::query()
        ->join('conversations', 'conversations.id', '=', 'conversation_participants.conversation_id')
        ->where('conversation_participants.conversation_id', $conversationId)
        ->where('conversation_participants.user_id', $user->id)
        ->value('conversations.workspace_id');

    return filled($workspaceId) && WorkspaceMembership::query()
        ->where('workspace_id', $workspaceId)
        ->where('user_id', $user->id)
        ->where('status', 'active')
        ->exists();
});
