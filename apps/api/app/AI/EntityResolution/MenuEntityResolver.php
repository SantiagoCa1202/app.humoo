<?php

namespace App\AI\EntityResolution;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MenuEntityResolver
{
    public function resolveMenu(
        string $workspaceId,
        array $references,
        ?string $menuId = null,
        ?string $menuSearch = null
    ): array {
        $query = Menu::query()
            ->where('workspace_id', $workspaceId)
            ->with($this->menuRelations());

        if (filled($menuId)) {
            $menu = $query->whereKey($menuId)->first();

            return $menu ? ['status' => 'resolved', 'menu' => $menu] : ['status' => 'missing'];
        }

        $search = $this->normalize($menuSearch);
        if ($search !== '') {
            $exact = (clone $query)
                ->whereRaw('LOWER(name) = ?', [$search])
                ->get();

            if ($exact->count() === 1) {
                return ['status' => 'resolved', 'menu' => $exact->first()];
            }

            $matches = (clone $query)
                ->whereRaw('LOWER(name) like ?', ["%{$search}%"])
                ->limit(5)
                ->get();

            return $this->resolveCollection($matches);
        }

        $activeReference = collect($references)
            ->first(fn (array $reference): bool => ($reference['type'] ?? null) === 'menu'
                && ($reference['role'] ?? null) === 'active');
        $activeId = is_array($activeReference) ? ($activeReference['id'] ?? null) : null;

        if (filled($activeId)) {
            $menu = (clone $query)->whereKey($activeId)->first();

            return $menu ? ['status' => 'resolved', 'menu' => $menu] : ['status' => 'missing'];
        }

        return ['status' => 'missing'];
    }

    public function resolveItem(Menu $menu, ?string $itemId, ?string $itemSearch): array
    {
        $items = $this->items($menu);

        if (filled($itemId)) {
            $item = $items->firstWhere('id', $itemId);

            return $item ? ['status' => 'resolved', 'item' => $item] : ['status' => 'missing'];
        }

        $search = $this->normalize($itemSearch);
        $matches = $items->filter(fn (MenuItem $item): bool => $search !== ''
            && (Str::lower(trim($item->name)) === $search
                || Str::contains(Str::lower($item->name), $search)));

        return $this->resolveCollection($matches, 'item');
    }

    public function resolveSection(Menu $menu, ?string $sectionId, ?string $sectionSearch): array
    {
        $sections = $this->sections($menu);

        if (filled($sectionId)) {
            $section = $sections->firstWhere('id', $sectionId);

            return $section ? ['status' => 'resolved', 'section' => $section] : ['status' => 'missing'];
        }

        $search = $this->normalize($sectionSearch);
        $matches = $sections->filter(fn (MenuSection $section): bool => $search !== ''
            && (Str::lower(trim($section->name)) === $search
                || Str::contains(Str::lower($section->name), $search)));

        return $this->resolveCollection($matches, 'section');
    }

    public function menuRelations(): array
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

    private function sections(Menu $menu): Collection
    {
        return $menu->currentVersionRecord?->sections ?? collect();
    }

    private function items(Menu $menu): Collection
    {
        return $this->sections($menu)->flatMap(fn (MenuSection $section) => $section->items);
    }

    private function resolveCollection(Collection $matches, string $key = 'menu'): array
    {
        if ($matches->count() === 1) {
            return ['status' => 'resolved', $key => $matches->first()];
        }

        if ($matches->count() > 1) {
            return [
                'status' => 'ambiguous',
                'candidates' => $matches->map(fn ($model) => [
                    'id' => $model->id,
                    'name' => $model->name,
                ])->values()->all(),
            ];
        }

        return ['status' => 'missing'];
    }

    private function normalize(?string $value): string
    {
        return Str::lower(trim((string) $value));
    }
}
