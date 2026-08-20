<?php

namespace App\Application\Actions\Prep;

use App\Models\Event;
use App\Models\EventMenu;
use App\Models\MenuItem;
use App\Models\MenuVersion;
use App\Models\PrepItem;
use App\Models\PrepItemAssignment;
use App\Models\PrepList;
use App\Models\PrepListVersion;
use App\Models\PrepSection;
use App\Models\RecipeVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GeneratePrepList
{
    public function execute(
        PrepList $prepList,
        string $workspaceId,
        ?string $userId,
        array $attributes,
        bool $persist = true
    ): array {
        $event = Event::query()
            ->where('workspace_id', $workspaceId)
            ->findOrFail($prepList->event_id);
        $currentVersion = $this->loadCurrentVersion($prepList, $workspaceId);
        $menuVersion = $this->resolveMenuVersion(
            $workspaceId,
            $event,
            $attributes['menu_version_id'] ?? null
        );

        if (!$menuVersion) {
            throw ValidationException::withMessages([
                'menu_version_id' => ['Prep generation requires an event menu version.'],
            ]);
        }

        $guestCount = $this->resolveGuestCount(
            $event,
            $menuVersion,
            $attributes['guest_count'] ?? null
        );

        $generation = $this->buildDraft(
            $prepList,
            $event,
            $menuVersion,
            $currentVersion,
            $guestCount,
            $attributes
        );

        if (!$persist) {
            return $generation;
        }

        return DB::transaction(function () use (
            $attributes,
            $currentVersion,
            $event,
            $generation,
            $guestCount,
            $menuVersion,
            $prepList,
            $userId,
            $workspaceId
        ): array {
            if ($currentVersion) {
                $currentVersion->forceFill([
                    'status' => 'superseded',
                ])->save();
            }

            $nextVersionNumber = (int) $prepList->current_version + 1;
            $status = $generation['estimated_items'] > 0 ? 'active' : 'draft';
            $version = PrepListVersion::query()->create([
                'workspace_id' => $workspaceId,
                'prep_list_id' => $prepList->id,
                'menu_version_id' => $menuVersion->id,
                'beo_version_id' => $attributes['beo_version_id'] ?? null,
                'version' => $nextVersionNumber,
                'status' => 'approved',
                'source' => $attributes['source'] ?? ($currentVersion ? 'regeneration' : 'manual'),
                'generation_metadata' => $generation['generation_metadata'],
                'guest_count_snapshot' => $guestCount,
                'event_starts_at_snapshot' => $event->starts_at,
                'approved_at' => now(),
                'approved_by' => $userId,
                'change_summary' => $attributes['change_summary']
                    ?? $attributes['notes']
                    ?? ($currentVersion ? 'Regenerated from current event menu.' : 'Generated from current event menu.'),
                'revision' => 1,
                'created_by' => $userId,
            ]);

            foreach ($generation['sections'] as $sectionPayload) {
                $section = PrepSection::query()->create([
                    'workspace_id' => $workspaceId,
                    'prep_list_version_id' => $version->id,
                    'name' => $sectionPayload['name'],
                    'type' => $sectionPayload['type'],
                    'production_date' => $sectionPayload['production_date'],
                    'starts_at' => $sectionPayload['starts_at'],
                    'due_at' => $sectionPayload['due_at'],
                    'position' => $sectionPayload['position'],
                    'notes' => $sectionPayload['notes'],
                ]);

                foreach ($sectionPayload['items'] as $itemPayload) {
                    $assignments = $itemPayload['assignments'] ?? [];
                    unset($itemPayload['assignments']);

                    $item = PrepItem::query()->create([
                        ...$itemPayload,
                        'workspace_id' => $workspaceId,
                        'prep_section_id' => $section->id,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    foreach ($assignments as $assignment) {
                        PrepItemAssignment::query()->create([
                            'workspace_id' => $workspaceId,
                            'prep_item_id' => $item->id,
                            'membership_id' => $assignment['membership_id'],
                            'status' => $assignment['status'] ?? 'assigned',
                            'is_primary' => $assignment['is_primary'] ?? true,
                            'assigned_at' => $assignment['assigned_at'] ?? now(),
                            'accepted_at' => $assignment['accepted_at'] ?? null,
                            'completed_at' => $assignment['completed_at'] ?? null,
                            'assigned_by' => $assignment['assigned_by'] ?? $userId,
                            'notes' => $assignment['notes'] ?? null,
                        ]);
                    }
                }
            }

            $prepList->forceFill([
                'blocked_items' => $generation['progress']['blocked'],
                'completed_items' => $generation['progress']['completed'],
                'current_version' => $nextVersionNumber,
                'event_id' => $event->id,
                'production_ends_at' => $attributes['due_at'] ?? $prepList->production_ends_at ?? $event->starts_at,
                'status' => $status,
                'total_items' => $generation['progress']['total'],
                'updated_by' => $userId,
            ])->save();

            $loadedPrepList = $this->loadPrepList($prepList, $workspaceId);
            $loadedVersion = $loadedPrepList->currentVersionRecord;

            return [
                'current_version' => $currentVersion,
                'event' => $event,
                'estimated_assignments' => $generation['estimated_assignments'],
                'estimated_items' => $generation['estimated_items'],
                'items' => $generation['items'],
                'menu_label' => $generation['menu_label'],
                'prep_list' => $loadedPrepList,
                'progress' => $generation['progress'],
                'summary' => $generation['summary'],
                'version' => $loadedVersion,
                'warnings' => $generation['warnings'],
            ];
        });
    }

    private function buildDraft(
        PrepList $prepList,
        Event $event,
        MenuVersion $menuVersion,
        ?PrepListVersion $currentVersion,
        ?int $guestCount,
        array $attributes
    ): array {
        $warnings = [];
        $dueAt = $attributes['due_at'] ?? $prepList->production_ends_at ?? $event->starts_at;
        $preserveAssignments = (bool) ($attributes['preserve_assignments'] ?? false);
        $preserveCompletedItems = (bool) ($attributes['preserve_completed_items'] ?? false);
        $currentItems = $currentVersion
            ? $currentVersion->sections
                ->flatMap(fn ($section) => $section->items ?? collect())
                ->keyBy(fn (PrepItem $item) => $this->buildItemKey(
                    $item->menu_item_id,
                    $item->recipe_version_id,
                    $item->title
                ))
            : collect();
        $matchedKeys = [];
        $sections = [];
        $flattenedItems = [];

        foreach ($menuVersion->sections as $sectionIndex => $menuSection) {
            $draftItems = [];

            foreach ($menuSection->items as $itemIndex => $menuItem) {
                $recipeVersion = $this->resolveRecipeVersion($menuItem);

                if (!$recipeVersion) {
                    $warnings[] = [
                        'id' => sprintf('prep-missing-recipe-%s', $menuItem->id),
                        'title' => sprintf('Skipped "%s" because it has no recipe version.', $menuItem->name),
                        'description' => 'Link the menu item to a recipe version before generating prep.',
                        'tone' => 'warning',
                    ];
                    continue;
                }

                $draftItem = $this->buildDraftItem(
                    $event,
                    $menuItem,
                    $recipeVersion,
                    $guestCount,
                    $dueAt,
                    $attributes,
                    $itemIndex + 1
                );
                $itemKey = $this->buildItemKey(
                    $menuItem->id,
                    $recipeVersion->id,
                    $draftItem['title']
                );
                $existingItem = $currentItems->get($itemKey);

                if ($existingItem) {
                    $matchedKeys[] = $itemKey;
                    $draftItem = $this->mergeExistingItem(
                        $draftItem,
                        $existingItem,
                        $preserveAssignments,
                        $preserveCompletedItems
                    );
                }

                $draftItems[] = $draftItem;
                $flattenedItems[] = $draftItem;
            }

            if (!$draftItems) {
                continue;
            }

            $sections[] = [
                'due_at' => $dueAt,
                'items' => $draftItems,
                'name' => $menuSection->name,
                'notes' => $menuSection->description,
                'position' => $menuSection->position ?? ($sectionIndex + 1),
                'production_date' => $event->starts_at?->toDateString(),
                'starts_at' => $event->production_starts_at ?? $prepList->production_starts_at ?? null,
                'type' => $menuSection->type ?? 'category',
            ];
        }

        foreach ($currentItems as $key => $currentItem) {
            if (in_array($key, $matchedKeys, true)) {
                continue;
            }

            if (!$this->itemHasManualWork($currentItem)) {
                continue;
            }

            $warnings[] = [
                'id' => sprintf('prep-unmatched-%s', $currentItem->id),
                'title' => sprintf('Manual work found on "%s".', $currentItem->title),
                'description' => 'The current version has assigned, started, completed, or edited work that could not be mapped into the regenerated version.',
                'tone' => 'danger',
            ];
        }

        if (!$sections) {
            $warnings[] = [
                'id' => 'prep-empty-generation',
                'title' => 'No prep items were generated.',
                'description' => 'The selected menu does not have menu items linked to recipe versions that can produce prep output.',
                'tone' => 'warning',
            ];
        }

        $progress = $this->summarizeItems($flattenedItems);
        $nextVersionNumber = (int) $prepList->current_version + 1;

        return [
            'current_version' => $currentVersion,
            'estimated_assignments' => $progress['assigned_staff_count'],
            'estimated_items' => count($flattenedItems),
            'event' => $event,
            'generation_metadata' => [
                'generated_at' => now()->toIso8601String(),
                'guest_count' => $guestCount,
                'menu_version_id' => $menuVersion->id,
                'menu_version_number' => $menuVersion->version,
                'preserve_assignments' => $preserveAssignments,
                'preserve_completed_items' => $preserveCompletedItems,
                'warning_count' => count($warnings),
            ],
            'items' => $flattenedItems,
            'menu_label' => trim(implode(' ', array_filter([
                $menuVersion->menu?->name,
                sprintf('v%s', $menuVersion->version),
            ]))),
            'prep_list' => $prepList,
            'progress' => $progress,
            'sections' => $sections,
            'summary' => sprintf(
                'Generated %d prep items across %d sections for %s.',
                count($flattenedItems),
                count($sections),
                $event->name
            ),
            'version_preview' => [
                'change_summary' => $attributes['change_summary']
                    ?? $attributes['notes']
                    ?? ($currentVersion ? 'Regenerated from current event menu.' : 'Generated from current event menu.'),
                'event_starts_at_snapshot' => $event->starts_at?->toIso8601String(),
                'generation_metadata' => [
                    'guest_count' => $guestCount,
                    'menu_version_id' => $menuVersion->id,
                    'menu_version_number' => $menuVersion->version,
                ],
                'guest_count_snapshot' => $guestCount,
                'menu_version' => [
                    'id' => $menuVersion->id,
                    'menu_id' => $menuVersion->menu_id,
                    'name' => $menuVersion->name,
                    'version' => $menuVersion->version,
                ],
                'menu_version_id' => $menuVersion->id,
                'prep_list_id' => $prepList->id,
                'revision' => 1,
                'sections' => $sections,
                'source' => $attributes['source'] ?? ($currentVersion ? 'regeneration' : 'manual'),
                'status' => $currentVersion ? 'review' : 'approved',
                'version' => $nextVersionNumber,
            ],
            'warnings' => $warnings,
        ];
    }

    private function buildDraftItem(
        Event $event,
        MenuItem $menuItem,
        RecipeVersion $recipeVersion,
        ?int $guestCount,
        $dueAt,
        array $attributes,
        int $position
    ): array {
        $quantity = $this->resolveQuantity($menuItem, $recipeVersion, $guestCount);
        $assignments = [];

        if (($attributes['include_assignments'] ?? false) && filled($attributes['assignment_membership_id'] ?? null)) {
            $assignments[] = [
                'assigned_at' => now(),
                'assigned_by' => null,
                'is_primary' => true,
                'membership_id' => $attributes['assignment_membership_id'],
                'status' => 'assigned',
            ];
        }

        return [
            'actual_quantity' => null,
            'actual_unit_id' => null,
            'assignments' => $assignments,
            'blocked_reason' => null,
            'completed_at' => null,
            'completed_by' => null,
            'description' => $this->trimOrNull($menuItem->description ?? $recipeVersion->description),
            'due_at' => $dueAt,
            'generated' => true,
            'menu_item_id' => $menuItem->id,
            'metadata' => [
                'event_id' => $event->id,
                'guest_count' => $guestCount,
                'menu_item_name' => $menuItem->name,
                'menu_section_name' => $menuItem->menuSection?->name,
            ],
            'notes' => $this->trimOrNull($menuItem->notes),
            'portions' => $guestCount,
            'position' => $menuItem->position ?? $position,
            'priority' => 'normal',
            'quantity' => $quantity,
            'recipe_id' => $recipeVersion->recipe_id,
            'recipe_version_id' => $recipeVersion->id,
            'requires_confirmation' => false,
            'scale_factor' => $this->resolveScaleFactor($quantity, $recipeVersion),
            'source' => 'menu',
            'source_id' => $menuItem->id,
            'source_type' => 'menu_item',
            'started_at' => null,
            'starts_at' => $event->production_starts_at ?? null,
            'status' => 'todo',
            'title' => $recipeVersion->name ?: $menuItem->name,
            'unit_id' => $recipeVersion->yield_unit_id,
            'version' => 1,
            'yield_quantity' => $recipeVersion->base_yield,
            'yield_unit_id' => $recipeVersion->yield_unit_id,
        ];
    }

    private function mergeExistingItem(
        array $draftItem,
        PrepItem $existingItem,
        bool $preserveAssignments,
        bool $preserveCompletedItems
    ): array {
        if ($preserveAssignments) {
            $draftItem['assignments'] = $existingItem->assignments
                ->map(fn (PrepItemAssignment $assignment) => [
                    'accepted_at' => $assignment->accepted_at,
                    'assigned_at' => $assignment->assigned_at,
                    'assigned_by' => $assignment->assigned_by,
                    'completed_at' => $assignment->completed_at,
                    'is_primary' => $assignment->is_primary,
                    'membership_id' => $assignment->membership_id,
                    'notes' => $assignment->notes,
                    'status' => $assignment->status,
                ])->values()->all();
        }

        if ($preserveCompletedItems && $this->itemHasManualWork($existingItem)) {
            $draftItem['actual_quantity'] = $existingItem->actual_quantity;
            $draftItem['actual_unit_id'] = $existingItem->actual_unit_id;
            $draftItem['blocked_reason'] = $existingItem->blocked_reason;
            $draftItem['completed_at'] = $existingItem->completed_at;
            $draftItem['completed_by'] = $existingItem->completed_by;
            $draftItem['notes'] = $existingItem->notes ?: $draftItem['notes'];
            $draftItem['started_at'] = $existingItem->started_at;
            $draftItem['status'] = $existingItem->status;
        }

        return $draftItem;
    }

    private function itemHasManualWork(PrepItem $item): bool
    {
        return ($item->assignments?->count() ?? 0) > 0
            || filled($item->notes)
            || filled($item->blocked_reason)
            || in_array($item->status, ['in_progress', 'blocked', 'done', 'skipped'], true)
            || filled($item->started_at)
            || filled($item->completed_at);
    }

    private function summarizeItems(array $items): array
    {
        $collection = collect($items);
        $assignedMemberships = $collection
            ->flatMap(fn (array $item) => collect($item['assignments'] ?? [])->pluck('membership_id'))
            ->filter()
            ->unique();

        $completed = $collection->where('status', 'done')->count();
        $blocked = $collection->where('status', 'blocked')->count();
        $inProgress = $collection->where('status', 'in_progress')->count();
        $skipped = $collection->where('status', 'skipped')->count();
        $total = $collection->count();

        return [
            'assigned_staff_count' => $assignedMemberships->count(),
            'blocked' => $blocked,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'remaining' => $total - $completed - $skipped,
            'skipped' => $skipped,
            'total' => $total,
            'unassigned' => $collection->filter(
                fn (array $item) => empty($item['assignments'])
            )->count(),
        ];
    }

    private function resolveMenuVersion(
        string $workspaceId,
        Event $event,
        ?string $menuVersionId = null
    ): ?MenuVersion {
        $query = MenuVersion::query()
            ->where('workspace_id', $workspaceId)
            ->with([
                'menu',
                'sections.items.menuSection',
                'sections.items.recipe.currentVersionRecord',
                'sections.items.recipeVersion',
            ]);

        if ($menuVersionId) {
            return $query->find($menuVersionId);
        }

        $eventMenu = EventMenu::query()
            ->where('workspace_id', $workspaceId)
            ->where('event_id', $event->id)
            ->where('type', 'primary')
            ->whereIn('status', ['draft', 'approved'])
            ->latest('assigned_at')
            ->first();

        if (!$eventMenu?->menu_version_id) {
            return null;
        }

        return $query->find($eventMenu->menu_version_id);
    }

    private function resolveGuestCount(
        Event $event,
        MenuVersion $menuVersion,
        ?int $requestedGuestCount = null
    ): ?int {
        if ($requestedGuestCount) {
            return $requestedGuestCount;
        }

        $eventMenu = EventMenu::query()
            ->where('workspace_id', $event->workspace_id)
            ->where('event_id', $event->id)
            ->where('menu_version_id', $menuVersion->id)
            ->latest('assigned_at')
            ->first();

        return $eventMenu?->guest_count
            ?? $event->guest_count_confirmed
            ?? $event->guest_count_expected
            ?? $menuVersion->menu?->default_guest_count;
    }

    private function resolveRecipeVersion(MenuItem $menuItem): ?RecipeVersion
    {
        if ($menuItem->relationLoaded('recipeVersion') && $menuItem->recipeVersion) {
            return $menuItem->recipeVersion;
        }

        if ($menuItem->relationLoaded('recipe') && $menuItem->recipe) {
            return $menuItem->recipe->currentVersionRecord;
        }

        return null;
    }

    private function resolveQuantity(
        MenuItem $menuItem,
        RecipeVersion $recipeVersion,
        ?int $guestCount
    ): ?float {
        if ($menuItem->planned_quantity !== null) {
            return (float) $menuItem->planned_quantity;
        }

        if ($menuItem->quantity_per_guest !== null && $guestCount !== null) {
            return round((float) $menuItem->quantity_per_guest * $guestCount, 4);
        }

        if ($recipeVersion->base_yield !== null) {
            return (float) $recipeVersion->base_yield;
        }

        return null;
    }

    private function resolveScaleFactor(
        ?float $quantity,
        RecipeVersion $recipeVersion
    ): ?float {
        $baseYield = $recipeVersion->base_yield !== null
            ? (float) $recipeVersion->base_yield
            : null;

        if (!$quantity || !$baseYield || $baseYield <= 0) {
            return null;
        }

        return round($quantity / $baseYield, 6);
    }

    private function buildItemKey(
        ?string $menuItemId,
        ?string $recipeVersionId,
        string $title
    ): string {
        return implode(':', [
            $menuItemId ?: 'no-menu-item',
            $recipeVersionId ?: 'no-recipe-version',
            trim($title),
        ]);
    }

    private function loadCurrentVersion(
        PrepList $prepList,
        string $workspaceId
    ): ?PrepListVersion {
        if ((int) $prepList->current_version < 1) {
            return null;
        }

        return PrepListVersion::query()
            ->where('workspace_id', $workspaceId)
            ->where('prep_list_id', $prepList->id)
            ->where('version', $prepList->current_version)
            ->with([
                'sections.items.assignments',
            ])
            ->first();
    }

    private function loadPrepList(
        PrepList $prepList,
        string $workspaceId
    ): PrepList {
        return PrepList::query()
            ->where('workspace_id', $workspaceId)
            ->whereKey($prepList->id)
            ->with([
                'completedBy',
                'createdBy',
                'currentVersionRecord.approvedBy',
                'currentVersionRecord.createdBy',
                'currentVersionRecord.lockedBy',
                'currentVersionRecord.menuVersion',
                'currentVersionRecord.sections.items.actualUnit',
                'currentVersionRecord.sections.items.assignments.assignedBy',
                'currentVersionRecord.sections.items.assignments.membership.role',
                'currentVersionRecord.sections.items.assignments.membership.teams',
                'currentVersionRecord.sections.items.assignments.membership.user',
                'currentVersionRecord.sections.items.completedBy',
                'currentVersionRecord.sections.items.createdBy',
                'currentVersionRecord.sections.items.recipe',
                'currentVersionRecord.sections.items.recipeVersion',
                'currentVersionRecord.sections.items.unit',
                'currentVersionRecord.sections.items.updatedBy',
                'currentVersionRecord.sections.items.yieldUnit',
                'event',
                'updatedBy',
                'versions.createdBy',
                'versions.menuVersion',
            ])
            ->firstOrFail();
    }

    private function trimOrNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
