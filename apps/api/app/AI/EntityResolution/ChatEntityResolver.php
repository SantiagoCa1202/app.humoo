<?php

namespace App\AI\EntityResolution;

use App\Application\Actions\ChatTools\ListTasksForTool;
use App\Application\Actions\ChatTools\ListWorkspaceMembersForTool;
use App\Models\Menu;
use Illuminate\Support\Str;

/**
 * Single entry point for resolving entities referenced by chat tools.
 *
 * The module resolvers remain the owners of their query, relation and
 * workspace-scoping rules. This class only normalizes the dispatch and the
 * shared chat context so ToolExecutor does not choose a different resolver in
 * every module-specific branch.
 */
class ChatEntityResolver
{
    public function __construct(
        private ListTasksForTool $tasks,
        private ListWorkspaceMembersForTool $members,
        private DirectoryEntityResolver $directory,
        private RecipeEntityResolver $recipes,
        private MenuEntityResolver $menus,
        private PrepEntityResolver $prep,
        private TeamStaffEntityResolver $teamStaff,
    ) {
    }

    public function resolve(
        string $workspaceId,
        string $type,
        array $input = [],
        array $references = [],
        ?string $actionKey = null,
        ?string $originalMessage = null,
        ?string $actorId = null,
    ): array {
        // Strict function schemas expose both stable IDs and natural-language
        // search fields. The model can legally put a person's name in the ID
        // slot; repair that shape at the shared resolver boundary instead of
        // making every module implement its own validation workaround.
        $input = $this->repairNaturalLanguageReference($type, $input);

        return match ($type) {
            'task' => $this->tasks->find(
                $workspaceId,
                $input['task_id'] ?? null,
                $input['task_search'] ?? ($input['search'] ?? null),
                $references,
                $actorId,
                $actionKey,
            ),
            'membership' => $this->members->find(
                $workspaceId,
                $input['membership_id'] ?? null,
                $input['member_search'] ?? ($input['search'] ?? null),
                $references,
            ),
            'client', 'contact', 'event', 'venue' => $this->directory->resolve(
                $workspaceId,
                $type,
                $input['entity_id'] ?? ($input[$type.'_id'] ?? null),
                $input['entity_search'] ?? ($input[$type.'_search'] ?? ($input['search'] ?? null)),
                $references,
            ),
            'recipe' => $this->recipes->resolve(
                $workspaceId,
                $references,
                $input['recipe_id'] ?? null,
                $input['recipe_search'] ?? ($input['search'] ?? null),
                $input['recipe_version_id'] ?? null,
                $actionKey,
                null,
            ),
            'menu' => $this->menus->resolveMenu(
                $workspaceId,
                $references,
                $input['menu_id'] ?? null,
                $input['menu_search'] ?? ($input['search'] ?? null),
            ),
            'prep_list' => $this->prep->resolveList(
                $workspaceId,
                $references,
                $input['prep_list_id'] ?? null,
                $input['prep_list_search'] ?? ($input['search'] ?? null),
                $input['event_id'] ?? null,
            ),
            'prep_item' => $this->prep->resolveItem(
                $workspaceId,
                $references,
                $input['prep_item_id'] ?? null,
                $input['prep_item_search'] ?? ($input['search'] ?? null),
                $input['prep_list_id'] ?? null,
            ),
            'team', 'station', 'shift' => $this->teamStaff->resolve(
                $workspaceId,
                $type,
                $input[$type.'_id'] ?? null,
                $input[$type.'_search'] ?? ($input['search'] ?? null),
                $references,
            ),
            default => ['status' => 'unsupported', 'candidates' => []],
        };
    }

    /**
     * Normalize the shape of model arguments before module validation. This
     * keeps human references out of ULID fields even when a module validates
     * its payload before it invokes the resolver.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function normalizeInputReferences(array $input): array
    {
        foreach ([
            ['task_id', 'task_search'], ['membership_id', 'member_search'],
            ['client_id', 'client_search'], ['contact_id', 'contact_search'],
            ['event_id', 'event_search'], ['venue_id', 'venue_search'],
            ['recipe_id', 'recipe_search'], ['menu_id', 'menu_search'],
            ['prep_list_id', 'prep_list_search'], ['prep_item_id', 'prep_item_search'],
            ['team_id', 'team_search'], ['station_id', 'station_search'],
            ['shift_id', 'shift_search'], ['document_id', 'document_search'],
            ['beo_id', 'beo_search'],
        ] as [$idKey, $searchKey]) {
            $id = $input[$idKey] ?? null;
            if (blank($id) || filled($input[$searchKey] ?? null) || Str::isUlid((string) $id)) {
                continue;
            }

            $input[$searchKey] = (string) $id;
            $input[$idKey] = null;
        }

        if (is_array($input['tasks'] ?? null)) {
            $input['tasks'] = collect($input['tasks'])
                ->map(fn (mixed $task): mixed => is_array($task)
                    ? $this->normalizeInputReferences($task)
                    : $task)
                ->values()
                ->all();
        }

        return $input;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function repairNaturalLanguageReference(string $type, array $input): array
    {
        $pairs = match ($type) {
            'task' => [['task_id', 'task_search']],
            'membership' => [['membership_id', 'member_search']],
            'client', 'contact', 'event', 'venue' => [
                ['entity_id', $type.'_search'],
                [$type.'_id', $type.'_search'],
            ],
            'recipe' => [['recipe_id', 'recipe_search']],
            'menu' => [['menu_id', 'menu_search']],
            'prep_list' => [['prep_list_id', 'prep_list_search']],
            'prep_item' => [['prep_item_id', 'prep_item_search']],
            'team', 'station', 'shift' => [[$type.'_id', $type.'_search']],
            default => [],
        };

        foreach ($pairs as [$idKey, $searchKey]) {
            $id = $input[$idKey] ?? null;
            if (blank($id) || filled($input[$searchKey] ?? null) || Str::isUlid((string) $id)) {
                continue;
            }

            $input[$searchKey] = (string) $id;
            $input[$idKey] = null;
        }

        return $input;
    }

    public function resolveMenuItem(Menu $menu, ?string $itemId, ?string $search): array
    {
        return $this->menus->resolveItem($menu, $itemId, $search);
    }

    public function resolveMenuSection(Menu $menu, ?string $sectionId, ?string $search): array
    {
        return $this->menus->resolveSection($menu, $sectionId, $search);
    }

    public function resolvePrepMembership(
        string $workspaceId,
        array $references,
        ?string $membershipId = null,
        ?string $search = null,
    ): array {
        $result = $this->resolve(
            $workspaceId,
            'membership',
            ['membership_id' => $membershipId, 'member_search' => $search],
            $references,
            'prep.membership.resolve',
            $search,
        );

        if (($result['status'] ?? null) === 'resolved' && isset($result['entity'])) {
            return [...$result, 'membership' => $result['entity']];
        }

        return $result;
    }
}
