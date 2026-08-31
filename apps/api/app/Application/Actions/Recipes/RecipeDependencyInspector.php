<?php

namespace App\Application\Actions\Recipes;

use App\Models\MenuItem;
use App\Models\PrepItem;
use App\Models\Recipe;

final class RecipeDependencyInspector
{
    /** @return array{menu_items: int, prep_items: int, events: int, total: int} */
    public function inspect(Recipe $recipe): array
    {
        $versionIds = $recipe->versions()->pluck('id');
        $menuScope = static fn ($query) => $query->where('menu_items.workspace_id', $recipe->workspace_id)
            ->where(fn ($nested) => $nested->where('menu_items.recipe_id', $recipe->id)->orWhereIn('menu_items.recipe_version_id', $versionIds));
        $prepScope = static fn ($query) => $query->where('prep_items.workspace_id', $recipe->workspace_id)
            ->where(fn ($nested) => $nested->where('prep_items.recipe_id', $recipe->id)->orWhereIn('prep_items.recipe_version_id', $versionIds));
        $menuItems = MenuItem::query()->where($menuScope)->count();
        $prepItems = PrepItem::query()->where($prepScope)->count();
        $eventsFromMenus = MenuItem::query()
            ->join('menu_sections', 'menu_sections.id', '=', 'menu_items.menu_section_id')
            ->join('event_menus', 'event_menus.menu_version_id', '=', 'menu_sections.menu_version_id')
            ->where($menuScope)
            ->distinct()
            ->pluck('event_menus.event_id');
        $eventsFromPrep = PrepItem::query()
            ->join('prep_sections', 'prep_sections.id', '=', 'prep_items.prep_section_id')
            ->join('prep_list_versions', 'prep_list_versions.id', '=', 'prep_sections.prep_list_version_id')
            ->join('prep_lists', 'prep_lists.id', '=', 'prep_list_versions.prep_list_id')
            ->where($prepScope)
            ->distinct()
            ->pluck('prep_lists.event_id');
        $events = $eventsFromMenus->merge($eventsFromPrep)->unique()->count();
        return ['menu_items' => $menuItems, 'prep_items' => $prepItems, 'events' => $events, 'total' => $menuItems + $prepItems];
    }
}
