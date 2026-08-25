<?php

namespace App\Application\Actions\ChatTools;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\NotificationPreference;

class ListNotificationsForTool
{
    public function execute(string $workspaceId, string $userId, array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 12), 50));
        $unreadOnly = (bool) ($filters['unread_only'] ?? false);
        $items = Notification::query()->where('workspace_id', $workspaceId)->where('user_id', $userId)
            ->whereNull('dismissed_at')->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')->limit($limit)->get();
        return ['count' => $items->count(), 'items' => NotificationResource::collection($items)->resolve()];
    }

    public function unreadCount(string $workspaceId, string $userId): int
    {
        return Notification::query()->where('workspace_id', $workspaceId)->where('user_id', $userId)->whereNull('read_at')->whereNull('dismissed_at')->count();
    }

    public function preferences(string $workspaceId, string $userId): array
    {
        $stored = NotificationPreference::query()->where('workspace_id', $workspaceId)->where('user_id', $userId)->whereIn('event_key', NotificationPreference::SUPPORTED_EVENT_KEYS)->get()->keyBy('event_key');
        return collect(NotificationPreference::SUPPORTED_EVENT_KEYS)->map(function (string $eventKey) use ($stored): array {
            $preference = $stored->get($eventKey);
            return ['event_key' => $eventKey, 'enabled' => $preference?->enabled ?? true, 'in_app' => $preference?->in_app ?? true, 'minimum_priority' => $preference?->minimum_priority ?? 'all', 'supported_channels' => ['in_app']];
        })->values()->all();
    }
}
