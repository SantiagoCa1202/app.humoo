<?php

namespace App\Application\Actions\Menus;

use App\Models\Event;
use App\Models\EventMenu;
use App\Models\Menu;
use App\Models\MenuVersion;
use App\Models\EventMenuItemOverride;

class AssignMenuToEvent
{
    public function execute(
        Menu $menu,
        MenuVersion $menuVersion,
        string $workspaceId,
        ?string $userId,
        ?string $eventId,
        array $payload = []
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

        $eventMenu = EventMenu::query()->updateOrCreate(
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

        $items = $menuVersion->sections()
            ->with(['items' => fn ($query) => $query->orderBy('position')])
            ->orderBy('position')
            ->get()
            ->flatMap->items
            ->values();
        $payloadItems = collect($payload['sections'] ?? [])->flatMap(
            fn (array $section) => $section['items'] ?? []
        )->values();

        $overridePayloads = $payloadItems->filter(
            fn ($item): bool => is_array($item) && ($item['event_planned_quantity'] ?? null) !== null
        );

        if ($overridePayloads->isEmpty()) {
            return $eventMenu;
        }

        $eventMenu->itemOverrides()->delete();
        foreach ($items as $index => $menuItem) {
            $itemPayload = $payloadItems->get($index);
            if (!is_array($itemPayload) || ($itemPayload['event_planned_quantity'] ?? null) === null) {
                continue;
            }

            EventMenuItemOverride::query()->create([
                'workspace_id' => $workspaceId,
                'event_menu_id' => $eventMenu->id,
                'menu_item_id' => $menuItem->id,
                'planned_quantity' => $itemPayload['event_planned_quantity'],
                'serving_unit' => $itemPayload['serving_unit'] ?? $menuItem->serving_unit,
                'metadata' => ['source' => 'user'],
            ]);
        }

        return $eventMenu;
    }
}
