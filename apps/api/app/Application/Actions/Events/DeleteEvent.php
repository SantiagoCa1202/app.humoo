<?php

namespace App\Application\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class DeleteEvent
{
    public function execute(Event $event): array
    {
        return DB::transaction(function () use ($event): array {
            $dependencyCounts = [
                'beo_count' => $event->beo()->count(),
                'menus_count' => $event->menus()->count(),
                'notes_count' => $event->notes()->count(),
                'prep_lists_count' => $event->prepLists()->count(),
                'staff_count' => $event->staff()->count(),
            ];

            if (array_sum($dependencyCounts) > 0) {
                return ['deleted' => false, 'dependencies' => $dependencyCounts, 'before' => null];
            }

            $before = $event->toArray();
            $event->statusHistory()->delete();
            $event->delete();

            return ['deleted' => true, 'dependencies' => $dependencyCounts, 'before' => $before];
        });
    }
}
