<?php

namespace App\Http\Resources;

use App\Models\EventMenu;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentVersion = $this->relationLoaded('currentVersionRecord')
            ? $this->currentVersionRecord
            : null;
        $sections = $currentVersion && $currentVersion->relationLoaded('sections')
            ? $currentVersion->sections
            : collect();
        $items = $sections instanceof Collection
            ? $sections->flatMap(fn ($section) => $section->items ?? collect())
            : collect();
        $currentEventLink = $currentVersion
            ? $this->resolveCurrentEventLink($currentVersion->eventAssignments ?? collect())
            : null;
        $aggregatedAllergens = $this->aggregateAllergens($items);

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'default_guest_count' => $this->default_guest_count,
            'guest_count' => $currentEventLink?->guest_count ?? $this->default_guest_count,
            'current_version' => $this->current_version,
            'current_version_id' => $currentVersion?->id,
            'metadata' => $this->metadata,
            'sections' => $currentVersion
                ? (new MenuVersionResource($currentVersion))->resolve()['sections'] ?? []
                : [],
            'section_count' => $sections->count(),
            'item_count' => $items->count(),
            'recipe_count' => $items
                ->filter(fn (MenuItem $item) => filled($item->recipe_id))
                ->pluck('recipe_id')
                ->unique()
                ->count(),
            'allergen_count' => count($aggregatedAllergens['allergens']),
            'allergens' => array_values($aggregatedAllergens['allergens']),
            'unknown_allergen_item_count' => $aggregatedAllergens['unknown_items_count'],
            'event' => $currentEventLink && $currentEventLink->relationLoaded('event') && $currentEventLink->event
                ? (new MenuEventReferenceResource($currentEventLink->event))->resolve()
                : null,
            'current_version_record' => $currentVersion
                ? (new MenuVersionResource($currentVersion))->resolve()
                : null,
            'version' => $currentVersion
                ? [
                    'id' => $currentVersion->id,
                    'version_number' => $currentVersion->version,
                    'version_label' => 'Version '.$currentVersion->version,
                    'change_summary' => $currentVersion->change_summary,
                    'created_by' => $currentVersion->relationLoaded('createdBy') && $currentVersion->createdBy
                        ? (new UserReferenceResource($currentVersion->createdBy))->resolve()
                        : null,
                    'created_at' => $currentVersion->created_at?->toIso8601String(),
                    'is_current' => true,
                    'notes' => null,
                    'revision' => $currentVersion->revision,
                    'status' => $currentVersion->status,
                  ]
                : null,
            'created_by' => $this->relationLoaded('createdBy') && $this->createdBy
                ? (new UserReferenceResource($this->createdBy))->resolve()
                : null,
            'updated_by' => $this->relationLoaded('updatedBy') && $this->updatedBy
                ? (new UserReferenceResource($this->updatedBy))->resolve()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveCurrentEventLink(Collection $links): ?EventMenu
    {
        return $links
            ->filter(
                fn (EventMenu $link) => $link->type === 'primary' && $link->status !== 'superseded'
            )
            ->sortByDesc(fn (EventMenu $link) => $link->assigned_at?->getTimestamp() ?? 0)
            ->first();
    }

    private function aggregateAllergens(Collection $items): array
    {
        $allergens = [];
        $unknownItemsCount = 0;

        foreach ($items as $item) {
            $recipeVersion = $item->recipeVersion;

            if (!$recipeVersion || !$recipeVersion->relationLoaded('allergens')) {
                if (!filled($item->recipe_version_id) || !filled($item->recipe_id)) {
                    $unknownItemsCount++;
                }

                continue;
            }

            foreach ($recipeVersion->allergens as $allergen) {
                $allergenId = $allergen->id;
                $severity = match ($allergen->pivot?->presence) {
                    'contains' => 'danger',
                    'cross_contact', 'may_contain' => 'warning',
                    default => 'neutral',
                };
                $metadata = trim(implode(
                    ', ',
                    array_values(array_filter([
                        $allergens[$allergenId]['metadata'] ?? null,
                        $item->name,
                    ]))
                ));

                $allergens[$allergenId] = [
                    'id' => $allergenId,
                    'code' => $allergen->key,
                    'name' => $allergen->name,
                    'translation_key' => $allergen->key
                        ? 'menus.allergens.catalog.'.$allergen->key
                        : null,
                    'metadata' => $metadata !== '' ? $metadata : null,
                    'severity' => $this->maxSeverity(
                        $allergens[$allergenId]['severity'] ?? null,
                        $severity
                    ),
                ];
            }
        }

        return [
            'allergens' => $allergens,
            'unknown_items_count' => $unknownItemsCount,
        ];
    }

    private function maxSeverity(?string $current, string $next): string
    {
        $weights = [
            'neutral' => 0,
            'info' => 1,
            'warning' => 2,
            'danger' => 3,
        ];

        return ($weights[$next] ?? 0) >= ($weights[$current] ?? -1)
            ? $next
            : (string) $current;
    }
}
