<?php

namespace App\Application\Actions\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Illuminate\Validation\ValidationException;

class UpdateMenuFromChat
{
    public function __construct(private UpdateMenu $updateMenu)
    {
    }

    public function rename(
        Menu $menu,
        string $workspaceId,
        string $userId,
        string $name
    ): Menu {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['The menu name is required.']]);
        }

        $payload = $this->menuPayload($menu);
        $payload['name'] = trim($name);

        return $this->save($menu, $workspaceId, $userId, $payload);
    }

    public function addItem(
        Menu $menu,
        string $workspaceId,
        string $userId,
        string $sectionId,
        array $item
    ): Menu {
        $payload = $this->menuPayload($menu);
        $section = collect($payload['sections'])->firstWhere('id', $sectionId);

        $itemName = trim((string) ($item['name'] ?? ''));

        if (!$section || $itemName === '') {
            throw ValidationException::withMessages(['section' => ['The menu section was not found.']]);
        }

        $sectionIndex = collect($payload['sections'])->search(fn (array $candidate): bool => $candidate['id'] === $sectionId);
        $payload['sections'][$sectionIndex]['items'][] = [
            'name' => $itemName,
            'type' => $item['type'] ?? 'dish',
            'description' => $item['description'] ?? null,
            'notes' => $item['notes'] ?? null,
            'position' => count($section['items']) + 1,
            'recipe_id' => $item['recipe_id'] ?? null,
            'recipe_version_id' => $item['recipe_version_id'] ?? null,
            'quantity_per_guest' => $item['quantity_per_guest'] ?? null,
            'serving_unit' => $item['serving_unit'] ?? null,
        ];

        return $this->save($menu, $workspaceId, $userId, $payload);
    }

    public function updateItem(
        Menu $menu,
        string $workspaceId,
        string $userId,
        string $itemId,
        array $changes
    ): Menu {
        $payload = $this->menuPayload($menu);
        $found = false;

        foreach ($payload['sections'] as $sectionIndex => $section) {
            foreach ($section['items'] as $itemIndex => $item) {
                if (($item['id'] ?? null) !== $itemId) {
                    continue;
                }

                $payload['sections'][$sectionIndex]['items'][$itemIndex] = [
                    ...$item,
                    ...array_intersect_key($changes, array_flip([
                        'name', 'description', 'notes', 'type', 'recipe_id', 'recipe_version_id',
                        'quantity_per_guest', 'serving_unit', 'optional', 'active',
                    ])),
                ];
                $found = true;
                break 2;
            }
        }

        if (!$found) {
            throw ValidationException::withMessages(['item' => ['The menu item was not found.']]);
        }

        return $this->save($menu, $workspaceId, $userId, $payload);
    }

    public function deleteItem(Menu $menu, string $workspaceId, string $userId, string $itemId): Menu
    {
        $payload = $this->menuPayload($menu);
        $found = false;

        foreach ($payload['sections'] as $sectionIndex => $section) {
            $items = collect($section['items'])
                ->reject(function (array $item) use ($itemId, &$found): bool {
                    if (($item['id'] ?? null) === $itemId) {
                        $found = true;
                        return true;
                    }

                    return false;
                })
                ->values()
                ->map(fn (array $item, int $index): array => [...$item, 'position' => $index + 1])
                ->all();
            $payload['sections'][$sectionIndex]['items'] = $items;
        }

        if (!$found) {
            throw ValidationException::withMessages(['item' => ['The menu item was not found.']]);
        }

        return $this->save($menu, $workspaceId, $userId, $payload);
    }

    public function updatePayload(Menu $menu, string $workspaceId, string $userId, array $payload): Menu
    {
        return $this->save($menu, $workspaceId, $userId, $payload);
    }

    public function payload(Menu $menu): array
    {
        return $this->menuPayload($menu);
    }

    public function moveItem(
        Menu $menu,
        string $workspaceId,
        string $userId,
        string $itemId,
        string $targetSectionId
    ): Menu {
        $payload = $this->menuPayload($menu);
        $itemPayload = null;
        $sourceSectionIndex = null;
        $itemIndex = null;

        foreach ($payload['sections'] as $sectionIndex => $section) {
            foreach ($section['items'] as $candidateIndex => $candidate) {
                if (($candidate['id'] ?? null) === $itemId) {
                    $itemPayload = $candidate;
                    $sourceSectionIndex = $sectionIndex;
                    $itemIndex = $candidateIndex;
                    break 2;
                }
            }
        }

        $targetSectionIndex = collect($payload['sections'])
            ->search(fn (array $section): bool => ($section['id'] ?? null) === $targetSectionId);

        if ($itemPayload === null || $sourceSectionIndex === false || $targetSectionIndex === false) {
            throw ValidationException::withMessages(['menu' => ['The menu item or target section was not found.']]);
        }

        if ($sourceSectionIndex === $targetSectionIndex) {
            return $menu->fresh();
        }

        array_splice($payload['sections'][$sourceSectionIndex]['items'], $itemIndex, 1);
        $itemPayload['position'] = count($payload['sections'][$targetSectionIndex]['items']) + 1;
        $payload['sections'][$targetSectionIndex]['items'][] = $itemPayload;

        return $this->save($menu, $workspaceId, $userId, $payload);
    }

    private function save(Menu $menu, string $workspaceId, string $userId, array $payload): Menu
    {
        $currentVersion = $menu->currentVersionRecord;

        if (!$currentVersion) {
            throw ValidationException::withMessages(['menu' => ['The menu has no current version.']]);
        }

        $updated = $this->updateMenu->execute(
            $menu,
            $workspaceId,
            $userId,
            $currentVersion->id,
            (int) $currentVersion->revision,
            $payload
        );

        if (!$updated) {
            throw ValidationException::withMessages(['menu' => ['The menu changed before this request completed.']]);
        }

        return $updated->fresh([
            'currentVersionRecord.sections.items.recipe.currentVersionRecord',
            'currentVersionRecord.sections.items.recipeVersion.allergens',
        ]);
    }

    private function menuPayload(Menu $menu): array
    {
        $version = $menu->currentVersionRecord;

        return [
            'name' => $menu->name,
            'description' => $menu->description,
            'type' => $menu->type,
            'status' => $menu->status,
            'default_guest_count' => $menu->default_guest_count,
            'sections' => $version?->sections->map(fn (MenuSection $section): array => [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
                'type' => $section->type,
                'position' => $section->position,
                'items' => $section->items->map(fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'notes' => $item->notes,
                    'type' => $item->type,
                    'position' => $item->position,
                    'recipe_id' => $item->recipe_id,
                    'recipe_version_id' => $item->recipe_version_id,
                    'quantity_per_guest' => $item->quantity_per_guest,
                    'serving_unit' => $item->serving_unit,
                    'planned_quantity' => $item->planned_quantity,
                    'estimated_unit_cost' => $item->estimated_unit_cost,
                    'cost_currency' => $item->cost_currency,
                    'optional' => $item->optional,
                    'active' => $item->active,
                    'metadata' => $item->metadata,
                ])->values()->all(),
            ])->values()->all() ?? [],
        ];
    }
}
