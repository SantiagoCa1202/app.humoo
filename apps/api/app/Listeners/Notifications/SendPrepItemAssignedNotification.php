<?php

namespace App\Listeners\Notifications;

use App\Data\Notifications\NotificationMessage;
use App\Events\Prep\PrepItemAssigned;
use App\Models\PrepItem;
use App\Models\WorkspaceMembership;
use App\Services\NotificationService;

class SendPrepItemAssignedNotification
{
    public function __construct(
        private NotificationService $notifications
    ) {
    }

    public function handle(PrepItemAssigned $event): void
    {
        $item = PrepItem::query()
            ->where('workspace_id', $event->workspaceId)
            ->with('section.version')
            ->whereKey($event->prepItemId)
            ->first();
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $event->workspaceId)
            ->whereKey($event->membershipId)
            ->where('status', 'active')
            ->first();

        if (!$item || !$membership) {
            return;
        }

        $prepListId = $item->section?->version?->prep_list_id;

        $this->notifications->send(new NotificationMessage(
            workspaceId: $event->workspaceId,
            recipientUserId: $membership->user_id,
            eventKey: 'prep.assigned',
            type: 'action_required',
            priority: $item->priority === 'urgent' ? 'high' : 'normal',
            title: 'notifications.prepAssignedTitle',
            body: 'notifications.prepAssignedBody',
            entityType: $prepListId ? 'prep_list' : null,
            entityId: $prepListId,
            actionKey: $prepListId ? 'prep.detail' : null,
            actionPayload: [
                'prep_list_id' => $prepListId,
                'prep_item_id' => $item->id,
            ],
            payload: ['prep_item_title' => $item->title],
            deduplicationKey: "prep.assigned:{$item->id}:{$membership->id}",
        ));
    }
}
