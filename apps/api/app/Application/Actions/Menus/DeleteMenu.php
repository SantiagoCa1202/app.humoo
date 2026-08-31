<?php

namespace App\Application\Actions\Menus;

use App\Models\Menu;
use Illuminate\Support\Facades\DB;

class DeleteMenu
{
    /**
     * Retire active event assignments before soft deleting the menu. Historical
     * event snapshots remain available, but no event keeps an active reference
     * to a menu that is no longer available to the workspace.
     */
    public function execute(Menu $menu, string $workspaceId): int
    {
        return DB::transaction(function () use ($menu, $workspaceId): int {
            $assignments = $menu->eventAssignments()
                ->where('workspace_id', $workspaceId)
                ->whereIn('status', ['draft', 'approved'])
                ->count();

            if ($assignments > 0) {
                $menu->eventAssignments()
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('status', ['draft', 'approved'])
                    ->update(['status' => 'superseded']);
            }

            $menu->delete();

            return $assignments;
        });
    }
}
