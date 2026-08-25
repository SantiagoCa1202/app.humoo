<?php

namespace App\Application\Actions\ChatTools;

use App\Models\Notification;

class MarkNotificationsRead
{
    public function execute(string $workspaceId, string $userId): int
    {
        return Notification::query()->where('workspace_id', $workspaceId)->where('user_id', $userId)->whereNull('read_at')->whereNull('dismissed_at')->update(['read_at' => now()]);
    }
}
