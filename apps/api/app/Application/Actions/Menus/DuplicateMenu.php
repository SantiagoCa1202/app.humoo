<?php

namespace App\Application\Actions\Menus;

use App\Models\Menu;

class DuplicateMenu
{
    private CreateMenu $createMenu;

    public function __construct(CreateMenu $createMenu)
    {
        $this->createMenu = $createMenu;
    }

    public function execute(
        Menu $menu,
        string $workspaceId,
        string $userId,
        array $options
    ): Menu {
        $currentVersion = $menu->currentVersionRecord()
            ->with([
                'sections.items.recipe',
                'sections.items.recipe.currentVersionRecord',
            ])
            ->firstOrFail();

        $includeSections = (bool) ($options['include_sections'] ?? true);
        $includeItems = (bool) ($options['include_items'] ?? true);
        $includeRecipeLinks = (bool) ($options['include_recipe_links'] ?? false);

        $payload = [
            'name' => trim((string) ($options['proposed_name'] ?? 'Copy of '.$menu->name)),
            'description' => $menu->description,
            'type' => $menu->type,
            'status' => 'draft',
            'default_guest_count' => $menu->default_guest_count,
            'event_id' => $options['target_event_id'] ?? null,
            'sections' => [],
        ];

        if ($includeSections) {
            $payload['sections'] = $currentVersion->sections->map(function ($section) use (
                $includeItems,
                $includeRecipeLinks
            ) {
                return [
                    'name' => $section->name,
                    'description' => $section->description,
                    'type' => $section->type,
                    'position' => $section->position,
                    'items' => $includeItems
                        ? $section->items->map(function ($item) use ($includeRecipeLinks) {
                            return [
                                'name' => $item->name,
                                'description' => $item->description,
                                'notes' => $item->notes,
                                'position' => $item->position,
                                'recipe_id' => $includeRecipeLinks ? $item->recipe_id : null,
                                'recipe_version_id' => $includeRecipeLinks ? $item->recipe_version_id : null,
                            ];
                        })->values()->all()
                        : [],
                ];
            })->values()->all();
        }

        return $this->createMenu->execute($workspaceId, $userId, $payload);
    }
}
