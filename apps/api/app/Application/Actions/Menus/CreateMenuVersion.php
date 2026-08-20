<?php

namespace App\Application\Actions\Menus;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\MenuVersion;
use App\Models\Recipe;
use App\Models\RecipeVersion;

class CreateMenuVersion
{
    public function execute(
        Menu $menu,
        string $workspaceId,
        string $userId,
        array $payload,
        ?MenuVersion $baseVersion = null,
        string $source = 'manual'
    ): MenuVersion {
        $version = MenuVersion::query()->create([
            'workspace_id' => $workspaceId,
            'menu_id' => $menu->id,
            'version' => (int) $menu->current_version + 1,
            'name' => trim((string) ($payload['name'] ?? $menu->name)),
            'description' => $this->trimOrNull($payload['description'] ?? $menu->description),
            'status' => $this->resolveVersionStatus(
                $payload['status'] ?? $menu->status,
                $baseVersion?->status
            ),
            'locked' => false,
            'change_summary' => $this->trimOrNull($payload['change_summary'] ?? null),
            'source' => $source,
            'revision' => 1,
            'metadata' => $payload['metadata'] ?? $baseVersion?->metadata,
            'created_by' => $userId,
        ]);

        $baseSections = $baseVersion
            ? $baseVersion->sections()
                ->with(['items.dietaryTags', 'items.recipeVersion'])
                ->get()
                ->keyBy('id')
            : collect();

        foreach (array_values($payload['sections'] ?? []) as $sectionIndex => $sectionPayload) {
            $baseSection = filled($sectionPayload['id'] ?? null)
                ? $baseSections->get($sectionPayload['id'])
                : null;

            $section = MenuSection::query()->create([
                'workspace_id' => $workspaceId,
                'menu_version_id' => $version->id,
                'name' => trim((string) $sectionPayload['name']),
                'description' => $this->trimOrNull(
                    $sectionPayload['description'] ?? $baseSection?->description
                ),
                'type' => $this->trimOrNull($sectionPayload['type'] ?? $baseSection?->type),
                'position' => $sectionPayload['position'] ?? ($sectionIndex + 1),
                'service_at' => $sectionPayload['service_at'] ?? $baseSection?->service_at,
                'metadata' => $sectionPayload['metadata'] ?? $baseSection?->metadata,
            ]);

            $baseItems = $baseSection?->relationLoaded('items')
                ? $baseSection->items->keyBy('id')
                : collect();

            foreach (array_values($sectionPayload['items'] ?? []) as $itemIndex => $itemPayload) {
                $baseItem = filled($itemPayload['id'] ?? null)
                    ? $baseItems->get($itemPayload['id'])
                    : null;
                $recipeSnapshot = $this->resolveRecipeSnapshot(
                    $workspaceId,
                    $itemPayload,
                    $baseItem
                );

                $menuItem = MenuItem::query()->create([
                    'workspace_id' => $workspaceId,
                    'menu_section_id' => $section->id,
                    'recipe_id' => $recipeSnapshot['recipe_id'],
                    'recipe_version_id' => $recipeSnapshot['recipe_version_id'],
                    'name' => trim((string) $itemPayload['name']),
                    'description' => $this->trimOrNull(
                        $itemPayload['description']
                            ?? $baseItem?->description
                            ?? $recipeSnapshot['recipe_version']?->description
                    ),
                    'type' => $this->trimOrNull($itemPayload['type'] ?? $baseItem?->type) ?? 'dish',
                    'course' => $this->trimOrNull($itemPayload['course'] ?? $baseItem?->course),
                    'quantity_per_guest' => $itemPayload['quantity_per_guest']
                        ?? $baseItem?->quantity_per_guest,
                    'serving_unit' => $this->trimOrNull(
                        $itemPayload['serving_unit'] ?? $baseItem?->serving_unit
                    ),
                    'planned_quantity' => $itemPayload['planned_quantity']
                        ?? $baseItem?->planned_quantity,
                    'estimated_unit_cost' => $itemPayload['estimated_unit_cost']
                        ?? $baseItem?->estimated_unit_cost
                        ?? $recipeSnapshot['recipe_version']?->estimated_cost_per_yield,
                    'cost_currency' => $this->normalizeCurrency(
                        $itemPayload['cost_currency']
                            ?? $baseItem?->cost_currency
                            ?? $recipeSnapshot['recipe_version']?->cost_currency
                    ),
                    'optional' => array_key_exists('optional', $itemPayload)
                        ? (bool) $itemPayload['optional']
                        : (bool) ($baseItem?->optional ?? false),
                    'active' => array_key_exists('active', $itemPayload)
                        ? (bool) $itemPayload['active']
                        : (bool) ($baseItem?->active ?? true),
                    'position' => $itemPayload['position'] ?? ($itemIndex + 1),
                    'notes' => $this->trimOrNull($itemPayload['notes'] ?? $baseItem?->notes),
                    'metadata' => $itemPayload['metadata'] ?? $baseItem?->metadata,
                ]);

                if ($baseItem && $baseItem->relationLoaded('dietaryTags')) {
                    $menuItem->dietaryTags()->sync(
                        $baseItem->dietaryTags
                            ->mapWithKeys(fn ($tag) => [
                                $tag->id => [
                                    'source' => $tag->pivot?->source ?? 'manual',
                                ],
                            ])
                            ->all()
                    );
                }
            }
        }

        return $version->fresh([
            'createdBy',
            'approvedBy',
            'sections.items.recipe.currentVersionRecord',
            'sections.items.recipeVersion.allergens',
            'eventAssignments.event.venue',
        ]);
    }

    private function resolveRecipeSnapshot(
        string $workspaceId,
        array $payload,
        ?MenuItem $baseItem = null
    ): array {
        $recipeId = $payload['recipe_id'] ?? $baseItem?->recipe_id;
        $recipeVersionId = $payload['recipe_version_id'] ?? $baseItem?->recipe_version_id;
        $recipeVersion = null;

        if ($recipeVersionId) {
            $recipeVersion = RecipeVersion::query()
                ->where('workspace_id', $workspaceId)
                ->with('allergens')
                ->find($recipeVersionId);

            if ($recipeVersion) {
                $recipeId = $recipeVersion->recipe_id;
            }
        }

        if ($recipeId && !$recipeVersion) {
            $recipe = Recipe::query()
                ->where('workspace_id', $workspaceId)
                ->with('currentVersionRecord')
                ->find($recipeId);

            $recipeVersion = $recipe?->currentVersionRecord;
            $recipeVersionId = $recipeVersion?->id;
        }

        return [
            'recipe_id' => $recipeId,
            'recipe_version' => $recipeVersion,
            'recipe_version_id' => $recipeVersionId,
        ];
    }

    private function resolveVersionStatus(?string $menuStatus, ?string $baseStatus): string
    {
        if ($menuStatus === 'archived') {
            return 'archived';
        }

        if ($menuStatus === 'active') {
            return 'approved';
        }

        return $baseStatus ?: 'draft';
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeCurrency(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? strtoupper($trimmed) : null;
    }
}
