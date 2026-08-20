<?php

namespace App\Application\Actions\Menus;

use App\Models\Event;
use App\Models\EventMenu;
use App\Models\Menu;
use App\Models\MenuVersion;

class AssignMenuToEvent
{
    public function execute(
        Menu $menu,
        MenuVersion $menuVersion,
        string $workspaceId,
        ?string $userId,
        ?string $eventId
    ): ?EventMenu {
        EventMenu::query()
            ->where('workspace_id', $workspaceId)
            ->where('menu_id', $menu->id)
            ->where('type', 'primary')
            ->whereIn('status', ['draft', 'approved'])
            ->update([
                'status' => 'superseded',
            ]);

        if (!$eventId) {
            return null;
        }

        $event = Event::query()
            ->where('workspace_id', $workspaceId)
            ->with('venue')
            ->findOrFail($eventId);

        return EventMenu::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'menu_version_id' => $menuVersion->id,
                'type' => 'primary',
            ],
            [
                'workspace_id' => $workspaceId,
                'menu_id' => $menu->id,
                'guest_count' => $menu->default_guest_count,
                'status' => 'approved',
                'assigned_by' => $userId,
                'assigned_at' => now(),
                'approved_by' => $userId,
                'approved_at' => now(),
                'snapshot_json' => [
                    'event_name' => $event->name,
                    'menu_name' => $menuVersion->name,
                    'menu_version' => $menuVersion->version,
                    'venue_name' => $event->venue?->name,
                ],
            ]
        );
    }
}
