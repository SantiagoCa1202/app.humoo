<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Menus\CreateMenu;
use App\Application\Actions\Menus\DuplicateMenu;
use App\Application\Actions\Menus\UpdateMenu;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menus\DuplicateMenuRequest;
use App\Http\Requests\Menus\StoreMenuRequest;
use App\Http\Requests\Menus\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Http\Resources\MenuVersionResource;
use App\Models\Menu;
use App\Models\MenuVersion;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Menu::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $menus = Menu::query()
            ->where('workspace_id', $workspace->id)
            ->with($this->menuRelations())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => MenuResource::collection(collect($menus->items())),
            'path' => $menus->path(),
            'per_page' => $menus->perPage(),
            'next_cursor' => $menus->nextCursor()?->encode(),
            'next_page_url' => $menus->nextPageUrl(),
            'prev_cursor' => $menus->previousCursor()?->encode(),
            'prev_page_url' => $menus->previousPageUrl(),
        ]);
    }

    public function store(
        StoreMenuRequest $request,
        CreateMenu $action,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('create', Menu::class);

        $workspace = app('currentWorkspace');
        $menu = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );

        $menu = $this->loadMenu($menu);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'menu.created',
            Menu::class,
            $menu->id,
            null,
            $menu->toArray()
        );

        return response()->json([
            'data' => new MenuResource($menu),
        ], 201);
    }

    public function show(Menu $menu)
    {
        $workspace = app('currentWorkspace');

        abort_unless($menu->workspace_id === $workspace->id, 404);
        $this->authorize('view', $menu);

        return response()->json([
            'data' => new MenuResource($this->loadMenu($menu)),
        ]);
    }

    public function update(
        UpdateMenuRequest $request,
        Menu $menu,
        UpdateMenu $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($menu->workspace_id === $workspace->id, 404);
        $this->authorize('update', $menu);

        $before = $menu->toArray();
        $updated = $action->execute(
            $menu,
            $workspace->id,
            $request->user()->id,
            $request->validated('current_version_id'),
            $request->integer('expected_revision'),
            $request->safe()->except(['current_version_id', 'expected_revision'])
        );

        if (!$updated) {
            return response()->json([
                'message' => 'Resource conflict.',
                'code' => 'VERSION_CONFLICT',
                'data' => (new MenuResource(
                    $this->loadMenu(
                        Menu::query()
                            ->whereKey($menu->getKey())
                            ->where('workspace_id', $workspace->id)
                            ->firstOrFail()
                    )
                ))->resolve(),
            ], 409);
        }

        $updated = $this->loadMenu($updated);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'menu.updated',
            Menu::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new MenuResource($updated),
        ]);
    }

    public function versions(Menu $menu)
    {
        $workspace = app('currentWorkspace');

        abort_unless($menu->workspace_id === $workspace->id, 404);
        $this->authorize('view', $menu);

        $versions = MenuVersion::query()
            ->where('workspace_id', $workspace->id)
            ->where('menu_id', $menu->id)
            ->with($this->versionRelations())
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => MenuVersionResource::collection($versions),
        ]);
    }

    public function duplicate(
        DuplicateMenuRequest $request,
        Menu $menu,
        DuplicateMenu $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($menu->workspace_id === $workspace->id, 404);
        $this->authorize('view', $menu);
        $this->authorize('create', Menu::class);

        $duplicate = $action->execute(
            $menu,
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );

        $duplicate = $this->loadMenu($duplicate);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'menu.duplicated',
            Menu::class,
            $duplicate->id,
            null,
            $duplicate->toArray()
        );

        return response()->json([
            'data' => new MenuResource($duplicate),
        ], 201);
    }

    private function menuRelations(): array
    {
        return [
            'createdBy',
            'updatedBy',
            'currentVersionRecord.createdBy',
            'currentVersionRecord.approvedBy',
            'currentVersionRecord.sections.items.recipe.currentVersionRecord',
            'currentVersionRecord.sections.items.recipeVersion.allergens',
            'currentVersionRecord.eventAssignments.event.venue',
        ];
    }

    private function versionRelations(): array
    {
        return [
            'createdBy',
            'approvedBy',
            'sections.items.recipe.currentVersionRecord',
            'sections.items.recipeVersion.allergens',
            'eventAssignments.event.venue',
        ];
    }

    private function loadMenu(Menu $menu): Menu
    {
        return $menu->fresh($this->menuRelations());
    }
}
