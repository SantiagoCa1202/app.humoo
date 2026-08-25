<?php

namespace App\Application\Actions\ChatTools;

use App\Models\NotificationPreference;
use Illuminate\Validation\ValidationException;

class UpdateNotificationPreference
{
    public function execute(string $workspaceId, string $userId, string $eventKey, array $input): array
    {
        if (!in_array($eventKey, NotificationPreference::SUPPORTED_EVENT_KEYS, true)) {
            throw ValidationException::withMessages(['event_key' => ['The selected notification preference is not supported.']]);
        }
        if (array_key_exists('minimum_priority', $input) && !in_array($input['minimum_priority'], ['all', 'important', 'critical'], true)) {
            throw ValidationException::withMessages(['minimum_priority' => ['The selected priority is invalid.']]);
        }
        $preference = NotificationPreference::query()->updateOrCreate([
            'workspace_id' => $workspaceId, 'user_id' => $userId, 'event_key' => $eventKey,
        ], [
            'enabled' => (bool) ($input['enabled'] ?? true), 'in_app' => (bool) ($input['in_app'] ?? true),
            'push' => false, 'email' => false, 'minimum_priority' => $input['minimum_priority'] ?? 'all',
        ]);
        return ['event_key' => $preference->event_key, 'enabled' => $preference->enabled, 'in_app' => $preference->in_app, 'minimum_priority' => $preference->minimum_priority, 'supported_channels' => ['in_app']];
    }
}
