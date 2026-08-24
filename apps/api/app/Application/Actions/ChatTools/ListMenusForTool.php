<?php

namespace App\Application\Actions\ChatTools;

use App\AI\EntityResolution\MenuEntityResolver;
use App\Http\Resources\MenuResource;
use App\Models\Menu;

class ListMenusForTool
{
    public function __construct(private MenuEntityResolver $menuEntityResolver)
    {
    }

    public function execute(string $workspaceId, array $filters = []): array
    {
        $menuId = trim((string) ($filters['menu_id'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($menuId !== '') {
            $menu = Menu::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($menuId)
                ->with($this->menuRelations())
                ->first();

            return [
                'count' => $menu ? 1 : 0,
                'items' => $menu ? [(new MenuResource($menu))->resolve()] : [],
                'mode' => 'show',
            ];
        }

        $menus = Menu::query()
            ->where('workspace_id', $workspaceId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->with($this->menuRelations())
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return [
            'count' => $menus->count(),
            'items' => MenuResource::collection($menus)->resolve(),
            'mode' => 'search',
        ];
    }

    private function menuRelations(): array
    {
        return $this->menuEntityResolver->menuRelations();
    }
}
