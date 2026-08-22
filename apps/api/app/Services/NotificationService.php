<?php

namespace App\Services;

use App\Data\Notifications\NotificationMessage;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function send(NotificationMessage $message): ?Notification
    {
        return DB::transaction(function () use ($message): ?Notification {
            $membership = WorkspaceMembership::query()
                ->where('workspace_id', $message->workspaceId)
                ->where('user_id', $message->recipientUserId)
                ->where('status', 'active')
                ->first();

            if (!$membership || !in_array($message->eventKey, NotificationPreference::SUPPORTED_EVENT_KEYS, true)) {
                return null;
            }

            $preference = NotificationPreference::query()
                ->where('workspace_id', $message->workspaceId)
                ->where('user_id', $message->recipientUserId)
                ->where('event_key', $message->eventKey)
                ->first();

            if (!$this->preferenceAllowsInApp($preference, $message->priority)) {
                return null;
            }

            if ($message->deduplicationKey) {
                $existing = Notification::query()
                    ->where('workspace_id', $message->workspaceId)
                    ->where('user_id', $message->recipientUserId)
                    ->where('deduplication_key', $message->deduplicationKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $notification = Notification::query()->create([
                'workspace_id' => $message->workspaceId,
                'user_id' => $message->recipientUserId,
                'event_key' => $message->eventKey,
                'type' => $message->type,
                'priority' => $message->priority,
                'title' => $message->title,
                'body' => $message->body,
                'entity_type' => $message->entityType,
                'entity_id' => $message->entityId,
                'action_key' => $message->actionKey,
                'action_payload' => $message->actionPayload,
                'payload' => $message->payload,
                'source' => $message->source,
                'deduplication_key' => $message->deduplicationKey,
            ]);

            NotificationDelivery::query()->create([
                'workspace_id' => $message->workspaceId,
                'notification_id' => $notification->id,
                'user_id' => $message->recipientUserId,
                'channel' => 'in_app',
                'status' => 'delivered',
                'provider' => 'internal',
                'attempts' => 1,
                'last_attempt_at' => now(),
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);

            return $notification;
        });
    }

    private function preferenceAllowsInApp(
        ?NotificationPreference $preference,
        string $priority
    ): bool {
        if (!$preference) {
            return true;
        }

        if (!$preference->enabled || !$preference->in_app) {
            return false;
        }

        $minimumPriority = [
            'all' => 0,
            'important' => 2,
            'critical' => 3,
        ][$preference->minimum_priority] ?? 0;

        $priorityValue = [
            'low' => 1,
            'normal' => 1,
            'high' => 2,
            'critical' => 3,
        ][$priority] ?? 1;

        return $priorityValue >= $minimumPriority;
    }
}
