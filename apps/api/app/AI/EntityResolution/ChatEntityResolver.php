<?php

namespace App\AI\EntityResolution;

use App\Application\Actions\ChatTools\ListTasksForTool;
use App\Application\Actions\ChatTools\ListWorkspaceMembersForTool;
use App\Models\Menu;

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
                $originalMessage,
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
