<?php

namespace App\AI\Tools;

use App\AI\EntityResolution\MenuEntityResolver;
use App\AI\EntityResolution\DirectoryEntityResolver;
use App\AI\EntityResolution\RecipeEntityResolver;
use App\AI\EntityResolution\PrepEntityResolver;
use App\AI\EntityResolution\TeamStaffEntityResolver;
use App\AI\Recipes\RecipeInputIngestionPipeline;
use App\AI\Recipes\UnitRegistry;
use App\Application\Actions\ChatTools\ListDirectoryEntitiesForTool;
use App\Application\Actions\Menus\CreateMenu;
use App\Application\Actions\Menus\UpdateMenuFromChat;
use App\Application\Actions\ChatTools\ListEventsForTool;
use App\Application\Actions\ChatTools\ListMenusForTool;
use App\Application\Actions\ChatTools\ListRecipesForTool;
use App\Application\Actions\ChatTools\ListMyTasksForTool;
use App\Application\Actions\ChatTools\ListTasksForTool;
use App\Application\Actions\ChatTools\ListDocumentsForTool;
use App\Application\Actions\ChatTools\ListBeosForTool;
use App\Application\Actions\Documents\RetryDocumentExtraction;
use App\Application\Actions\Documents\LinkDocumentToEvent;
use App\Application\Actions\ChatTools\ListNotificationsForTool;
use App\Application\Actions\ChatTools\MarkNotificationsRead;
use App\Application\Actions\ChatTools\UpdateNotificationPreference;
use App\Application\Actions\ChatTools\ListWorkspaceMembersForTool;
use App\Application\Actions\Team\UpdateWorkspace;
use App\Application\Actions\Team\UpdateWorkspaceMembership;
use App\Application\Actions\Team\RemoveWorkspaceMembership;
use App\Application\Actions\Team\InviteWorkspaceMember;
use App\Application\Actions\ChatTools\ListTeamStaffEntitiesForTool;
use App\Application\Actions\ChatTools\ListPrepListsForTool;
use App\Application\Actions\ChatTools\ListPrepItemsForTool;
use App\Application\Actions\Prep\CreatePrepList;
use App\Application\Actions\Prep\GeneratePrepList;
use App\Application\Actions\Prep\UpdatePrepList;
use App\Application\Actions\Prep\UpdatePrepItem;
use App\Application\Actions\Recipes\CreateRecipe;
use App\Application\Actions\Recipes\ScaleRecipe;
use App\Application\Actions\Recipes\UpdateRecipe;
use App\Application\Actions\Tasks\CreateTask;
use App\Application\Actions\Tasks\UpdateTask;
use App\Application\Actions\Tasks\DeleteTask;
use App\Application\Actions\TeamStaff\CreateTeam;
use App\Application\Actions\TeamStaff\UpdateTeam;
use App\Application\Actions\TeamStaff\CreateStation;
use App\Application\Actions\TeamStaff\UpdateStation;
use App\Application\Actions\TeamStaff\CreateShift;
use App\Application\Actions\TeamStaff\UpdateShift;
use App\Application\Actions\TeamStaff\SyncAvailability;
use App\Application\Actions\TeamStaff\SyncTeamMembers;
use App\Application\Actions\TeamStaff\DeleteTeamStaffEntity;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PrepItemResource;
use App\Http\Resources\PrepListResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeVersionResource;
use App\Http\Resources\MenuResource;
use App\Http\Resources\TaskResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\BeoResource;
use App\Http\Resources\BeoVersionResource;
use App\Http\Resources\VenueResource;
use App\Models\ActionConfirmation;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Message;
use App\Models\MessageBlock;
use App\Models\Menu;
use App\Models\PrepItem;
use App\Models\PrepList;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\Task;
use App\Models\Unit;
use App\Models\Venue;
use App\Models\Document;
use App\Models\Beo;
use App\Models\BeoVersion;
use App\Models\Notification;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use App\Models\Team;
use App\Models\Station;
use App\Models\Shift;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToolExecutor
{
    private const EXECUTABLE_ACTIONS = [
        'menus.rename', 'menus.items.add', 'menus.items.move_section',
        'prep.generate', 'prep.regenerate', 'prep.update', 'prep.items.update', 'prep_items.update',
        'prep.items.complete', 'prep.items.reopen', 'prep.items.assign', 'prep.items.unassign',
        'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign', 'tasks.status.update', 'tasks.complete',
        'teams.create', 'teams.update', 'teams.delete', 'teams.members.sync',
        'stations.create', 'stations.update', 'stations.delete', 'shifts.create', 'shifts.update', 'shifts.delete', 'availability.sync',
        'menus.create', 'menus.update', 'menus.items.update', 'menus.items.delete',
        'recipes.create', 'recipes.update',
        'events.create', 'events.update', 'events.cancel', 'events.delete',
        'clients.create', 'clients.update', 'clients.delete', 'contacts.create', 'contacts.update', 'contacts.delete', 'venues.create', 'venues.update', 'venues.delete',
        'documents.retry_extraction', 'documents.link_event', 'notification_preferences.update',
        'notifications.read_all', 'workspace.update', 'members.invite', 'members.update', 'members.remove',
    ];

    public static function supportsAction(ToolRegistry $registry, string $actionKey): bool
    {
        $tool = $registry->resolve($actionKey);
        return $tool['mode'] === 'read' || in_array($tool['key'], self::EXECUTABLE_ACTIONS, true);
    }
    public function __construct(
        private ToolRegistry $toolRegistry,
        private ListEventsForTool $listEventsForTool,
        private ListMenusForTool $listMenusForTool,
        private ListPrepListsForTool $listPrepListsForTool,
        private ListPrepItemsForTool $listPrepItemsForTool,
        private ListMyTasksForTool $listMyTasksForTool,
        private ListTasksForTool $listTasksForTool,
        private ListDocumentsForTool $listDocumentsForTool,
        private ListBeosForTool $listBeosForTool,
        private CreatePrepList $createPrepList,
        private GeneratePrepList $generatePrepList,
        private UpdatePrepList $updatePrepList,
        private UpdatePrepItem $updatePrepItem,
        private CreateTask $createTask,
        private UpdateTask $updateTask,
        private DeleteTask $deleteTask,
        private RetryDocumentExtraction $retryDocumentExtraction,
        private LinkDocumentToEvent $linkDocumentToEvent,
        private ListNotificationsForTool $listNotificationsForTool,
        private MarkNotificationsRead $markNotificationsRead,
        private UpdateNotificationPreference $updateNotificationPreference,
        private ListWorkspaceMembersForTool $listWorkspaceMembersForTool,
        private UpdateWorkspace $updateWorkspace,
        private UpdateWorkspaceMembership $updateWorkspaceMembership,
        private RemoveWorkspaceMembership $removeWorkspaceMembership,
        private InviteWorkspaceMember $inviteWorkspaceMember,
        private CreateMenu $createMenu,
        private UpdateMenuFromChat $updateMenuFromChat,
        private MenuEntityResolver $menuEntityResolver,
        private PrepEntityResolver $prepEntityResolver,
        private DirectoryEntityResolver $directoryEntityResolver,
        private ListDirectoryEntitiesForTool $listDirectoryEntitiesForTool,
        private ListRecipesForTool $listRecipesForTool,
        private RecipeEntityResolver $recipeEntityResolver,
        private CreateRecipe $createRecipe,
        private UpdateRecipe $updateRecipe,
        private ScaleRecipe $scaleRecipe,
        private ListTeamStaffEntitiesForTool $listTeamStaffEntitiesForTool,
        private TeamStaffEntityResolver $teamStaffEntityResolver,
        private CreateTeam $createTeam,
        private UpdateTeam $updateTeam,
        private CreateStation $createStation,
        private UpdateStation $updateStation,
        private CreateShift $createShift,
        private UpdateShift $updateShift,
        private SyncAvailability $syncAvailability,
        private SyncTeamMembers $syncTeamMembers,
        private DeleteTeamStaffEntity $deleteTeamStaffEntity,
        private RecipeInputIngestionPipeline $recipeInputIngestionPipeline
    ) {
    }

    public function request(array $context, array $payload): array
    {
        $tool = $this->toolRegistry->resolve(
            (string) ($payload['action_id'] ?? '')
        );

        if ($tool['mode'] === 'read') {
            return $this->executeReadTool($tool, $context, $payload);
        }

        return $tool['requires_confirmation']
            ? $this->previewWriteTool($tool, $context, $payload)
            : $this->executeImmediateTool($tool, $context, $payload);
    }

    public function confirm(
        ActionConfirmation $confirmation,
        array $context,
        ?array $overrideInput = null
    ): array {
        $draft = is_array($confirmation->draft_json)
            ? $confirmation->draft_json
            : [];
        if ($overrideInput !== null) {
            $draft['input'] = $overrideInput;
        }
        $tool = $this->toolRegistry->resolve(
            (string) ($draft['tool_key'] ?? '')
        );

        return match ($tool['key']) {
            'prep.generate', 'prep.regenerate' => $this->executePrepGeneration($tool, $context, $draft),
            'prep.update' => $this->executePrepListUpdate($tool, $context, $draft),
            'prep.items.update', 'prep_items.update', 'prep.items.complete', 'prep.items.reopen', 'prep.items.assign', 'prep.items.unassign'
                => $this->executePrepItemUpdate($tool, $context, $draft),
            'tasks.create' => $this->executeTaskCreate($tool, $context, $draft),
            'tasks.update', 'tasks.status.update', 'tasks.complete' => $this->executeTaskUpdate($tool, $context, $draft),
            'tasks.assign' => $this->executeTaskAssignment($tool, $context, $draft),
            'tasks.delete' => $this->executeTaskDelete($tool, $context, $draft),
            'documents.retry_extraction', 'documents.link_event' => $this->executeDocumentWrite($tool, $context, $draft),
            'notification_preferences.update' => $this->executeNotificationPreferenceUpdate($tool, $context, $draft),
            'workspace.update', 'members.invite', 'members.update', 'members.remove' => $this->executeWorkspaceWrite($tool, $context, $draft),
            'teams.create', 'teams.update', 'teams.delete', 'teams.members.sync',
            'stations.create', 'stations.update', 'stations.delete',
            'shifts.create', 'shifts.update', 'shifts.delete', 'availability.sync'
                => $this->executeTeamStaffWrite($tool, $context, $draft),
            'menus.create' => $this->executeMenuCreate($tool, $context, $draft),
            'menus.rename', 'menus.items.add', 'menus.items.move_section' => $this->executeImmediateTool($tool, $context, $draft),
            'menus.update', 'menus.items.update', 'menus.items.delete' => $this->executeMenuWrite($tool, $context, $draft),
            'recipes.create', 'recipes.update' => $this->executeRecipeWrite($tool, $context, $draft),
            'events.create', 'events.update', 'events.cancel', 'events.delete',
            'clients.create', 'clients.update', 'clients.delete',
            'contacts.create', 'contacts.update', 'contacts.delete',
            'venues.create', 'venues.update', 'venues.delete'
                => $this->executeDirectoryWrite($tool, $context, $draft),
            default => throw ValidationException::withMessages([
                'confirmation' => ['The confirmation tool is not executable.'],
            ]),
        };
    }

    private function executeReadTool(
        array $tool,
        array $context,
        array $payload
    ): array {
        $workspaceId = $context['workspace']->id;
        $membershipId = $context['membership']->id;
        $filters = is_array($payload['input'] ?? null)
            ? $payload['input']
            : [];

        if (in_array($tool['key'], ['workspace.detail', 'members.list', 'members.detail'], true)) {
            if ($tool['key'] === 'workspace.detail') {
                Gate::forUser($context['user'])->authorize('view', $context['workspace']);
            } else {
                abort_unless($context['user']->hasWorkspacePermission($workspaceId, 'members.view'), 403);
            }
        }
        if ($tool['key'] === 'workspace.detail') {
            return $this->genericReadResult($tool, $context, [$context['workspace']->toArray()], $context['workspace']->name, $this->genericEntityRef($context['workspace']->id, 'workspace', $context['workspace']->toArray()));
        }
        if (in_array($tool['key'], ['members.list', 'members.detail'], true)) {
            if ($tool['key'] === 'members.detail') {
                $resolution = $this->listWorkspaceMembersForTool->find($workspaceId, $filters['membership_id'] ?? null, $filters['member_search'] ?? null, $context['entity_refs'] ?? []);
                if (($resolution['status'] ?? null) !== 'resolved') {
                    $clarification = $this->entityResolutionClarificationResult(
                        $tool,
                        $context,
                        $resolution,
                        ['input' => $filters],
                        'member',
                        'membership_id',
                        (string) ($filters['member_search'] ?? '')
                    );
                    return $clarification ?? $this->genericResolutionResult($tool, $context, $resolution, 'member');
                }
                $resource = $this->listWorkspaceMembersForTool->serialize($resolution['entity']);
                return $this->genericReadResult($tool, $context, [$resource], $resource['user']['name'] ?? 'member', $this->genericEntityRef($resolution['entity']->id, 'membership', $resource));
            }
            $result = $this->listWorkspaceMembersForTool->execute($workspaceId, $filters);
            return $this->genericReadResult($tool, $context, $result['items'], 'workspace members', $this->genericEntityRef((string) ($result['items'][0]['id'] ?? $context['workspace']->id), 'membership', $result['items'][0] ?? []));
        }

        if (in_array($tool['key'], ['menus.search', 'menus.show'], true)) {
            Gate::forUser($context['user'])->authorize('viewAny', Menu::class);
        }

        if (in_array($tool['key'], ['recipes.list', 'recipes.detail', 'recipes.versions', 'recipes.scale'], true)) {
            Gate::forUser($context['user'])->authorize('viewAny', Recipe::class);
        }

        if (str_starts_with($tool['key'], 'prep.')) {
            Gate::forUser($context['user'])->authorize('viewAny', \App\Models\PrepList::class);
        }

        if (in_array($tool['key'], ['clients.list', 'contacts.list', 'venues.list'], true)) {
            $model = match ($tool['entity_type']) {
                'client' => Client::class,
                'contact' => Contact::class,
                'venue' => Venue::class,
            };
            Gate::forUser($context['user'])->authorize('viewAny', $model);
        }

        if (in_array($tool['key'], ['tasks.list', 'tasks.search', 'tasks.detail', 'tasks.read'], true)) {
            Gate::forUser($context['user'])->authorize('viewAny', Task::class);
        }

        if (in_array($tool['key'], ['documents.list', 'documents.detail', 'beos.list', 'beos.detail', 'beos.versions'], true)) {
            $model = str_starts_with($tool['key'], 'documents.') ? Document::class : Beo::class;
            Gate::forUser($context['user'])->authorize('viewAny', $model);
        }

        if ($tool['key'] === 'notifications.unread_count') {
            return $this->genericReadResult($tool, $context, [['count' => $this->listNotificationsForTool->unreadCount($context['workspace']->id, $context['user']->id)]], 'notifications', ['id' => $context['user']->id, 'role' => 'active', 'snapshot' => [], 'type' => 'notification']);
        }

        if ($tool['key'] === 'notification_preferences.list') {
            $items = $this->listNotificationsForTool->preferences($context['workspace']->id, $context['user']->id);
            return $this->genericReadResult($tool, $context, $items, 'notification preferences', ['id' => $context['user']->id, 'role' => 'active', 'snapshot' => [], 'type' => 'notification_preference']);
        }

        if (in_array($tool['entity_type'], ['team', 'station', 'shift', 'availability'], true)) {
            $ability = $tool['entity_type'] === 'availability' ? 'viewAny' : 'viewAny';
            $model = match ($tool['entity_type']) {
                'team' => Team::class, 'station' => Station::class, 'shift' => Shift::class,
                default => \App\Models\Availability::class,
            };
            Gate::forUser($context['user'])->authorize($ability, $model);
        }

        if (in_array($tool['key'], ['menus.show', 'recipes.detail', 'recipes.versions', 'recipes.scale'], true)
            && empty($filters['menu_id']) && empty($filters['menu_search'])
            && empty($filters['recipe_id']) && empty($filters['recipe_search'])) {
            $activeReference = collect($context['entity_refs'] ?? [])
                ->first(fn (array $reference): bool => ($reference['type'] ?? null) === 'menu'
                    && ($reference['role'] ?? null) === 'active');
            $filters['menu_id'] = is_array($activeReference)
                ? ($activeReference['id'] ?? null)
                : null;
        }

        if ($tool['key'] === 'menus.show'
            && empty($filters['menu_id'])
            && empty($filters['menu_search'])) {
            return [
                'blocks' => [['text' => trans('chat.menu.not_found', [], $context['locale']), 'type' => 'text']],
                'entity_refs' => [], 'result_ref_json' => ['count' => 0, 'items' => []],
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        }

        if ($tool['key'] === 'menus.show'
            && empty($filters['menu_id'])
            && filled($filters['menu_search'])) {
            $resolution = $this->menuEntityResolver->resolveMenu(
                $workspaceId,
                $context['entity_refs'] ?? [],
                null,
                (string) $filters['menu_search']
            );

            if (($resolution['status'] ?? null) !== 'resolved') {
                $clarification = $this->entityResolutionClarificationResult(
                    $tool,
                    $context,
                    $resolution,
                    ['input' => $filters],
                    'menu',
                    'menu_id',
                    (string) $filters['menu_search'],
                    'menu'
                );

                return $clarification ?? $this->menuResolutionResult($tool, $context, $resolution);
            }

            $resolvedMenu = $resolution['menu'] ?? null;
            if (!$resolvedMenu instanceof Menu) {
                return $this->menuResolutionResult($tool, $context, ['status' => 'missing']);
            }

            $filters['menu_id'] = $resolvedMenu->id;
        }

        if (in_array($tool['key'], ['recipes.detail', 'recipes.versions', 'recipes.scale'], true)) {
            return $this->executeRecipeRead($tool, $context, $filters);
        }

        if (in_array($tool['key'], ['events.detail', 'clients.detail', 'contacts.detail', 'venues.detail'], true)) {
            return $this->executeDirectoryDetailRead($tool, $context, $filters);
        }

        if (in_array($tool['key'], ['tasks.detail', 'tasks.read'], true)) {
            return $this->executeTaskDetailRead($tool, $context, $filters);
        }

        if (in_array($tool['key'], ['documents.detail', 'beos.detail', 'beos.versions'], true)) {
            return $this->executeDocumentOrBeoDetailRead($tool, $context, $filters);
        }

        if (in_array($tool['key'], ['prep.detail', 'prep.items.list', 'prep.items.detail'], true)) {
            return $this->executePrepRead($tool, $context, $filters);
        }

        if (in_array($tool['key'], ['teams.detail', 'stations.detail', 'shifts.detail'], true)) {
            return $this->executeTeamStaffDetailRead($tool, $context, $filters);
        }

        $result = match ($tool['key']) {
            'events.list' => $this->listEventsForTool->execute($workspaceId, $filters),
            'clients.list', 'contacts.list', 'venues.list' => $this->listDirectoryEntitiesForTool->execute(
                $workspaceId,
                $tool['entity_type'],
                $filters
            ),
            'menus.search', 'menus.show' => $this->listMenusForTool->execute($workspaceId, $filters),
            'recipes.list' => $this->listRecipesForTool->execute($workspaceId, $filters),
            'prep.list' => $this->listPrepListsForTool->execute($workspaceId, $filters),
            'prep.items.list' => $this->listPrepItemsForTool->execute($workspaceId, $filters),
            'tasks.mine' => $this->listMyTasksForTool->execute($workspaceId, $membershipId, $filters),
            'tasks.list', 'tasks.search' => $this->listTasksForTool->execute($workspaceId, $filters),
            'documents.list' => $this->listDocumentsForTool->execute($workspaceId, $filters),
            'beos.list' => $this->listBeosForTool->execute($workspaceId, $filters),
            'notifications.list' => $this->listNotificationsForTool->execute($workspaceId, $context['user']->id, $filters),
            'teams.list', 'teams.detail' => $this->listTeamStaffEntitiesForTool->execute($workspaceId, 'team', $filters),
            'stations.list', 'stations.detail' => $this->listTeamStaffEntitiesForTool->execute($workspaceId, 'station', $filters),
            'shifts.list', 'shifts.detail' => $this->listTeamStaffEntitiesForTool->execute($workspaceId, 'shift', $filters),
            'availability.list' => $this->listTeamStaffEntitiesForTool->execute($workspaceId, 'availability', $filters),
            default => throw ValidationException::withMessages([
                'action_id' => ['The selected action is not a readable tool.'],
            ]),
        };

        return [
            'blocks' => [
                [
                    'text' => $this->readSummaryText($tool['key'], (int) ($result['count'] ?? 0), (string) ($context['locale'] ?? 'en')),
                    'type' => 'text',
                ],
                [
                    'component' => $tool['component'],
                    'data' => $this->readComponentPayload($tool['key'], $result, (string) ($context['locale'] ?? 'en')),
                    'schema_version' => $tool['schema_version'],
                    'type' => 'component',
                ],
            ],
            'result_ref_json' => [
                'count' => $result['count'] ?? 0,
                'items' => $result['items'] ?? [],
            ],
            'entity_refs' => [
                ...$this->menuEntityRefs($tool['key'], $result['items'] ?? []),
                ...$this->directoryEntityRefs($tool['key'], $result['items'] ?? []),
                ...$this->recipeEntityRefs($tool['key'], $result['items'] ?? []),
                ...($tool['key'] === 'prep.list' ? $this->prepListEntityRefsFromEntries($result['items'] ?? []) : []),
                ...(in_array($tool['key'], ['tasks.list', 'tasks.search'], true) ? $this->taskEntityRefs($result['items'] ?? []) : []),
                ...($tool['key'] === 'documents.list' ? $this->genericEntityRefs($result['items'] ?? [], 'document') : []),
                ...($tool['key'] === 'beos.list' ? $this->genericEntityRefs($result['items'] ?? [], 'beo') : []),
                ...($this->teamStaffEntityRefs($tool['key'], $result['items'] ?? [])),
            ],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executeDirectoryDetailRead(array $tool, array $context, array $input): array
    {
        $type = (string) $tool['entity_type'];
        $resolution = $this->directoryEntityResolver->resolve(
            $context['workspace']->id,
            $type,
            $input['entity_id'] ?? null,
            $input['entity_search'] ?? null,
            $context['entity_refs'] ?? []
        );

        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                (string) ($tool['entity_type'] ?? 'record'),
                'entity_id',
                (string) ($input['entity_search'] ?? '')
            );
            return $clarification ?? $this->directoryResolutionResult($tool, $context, $resolution);
        }

        $entity = $this->loadDirectoryEntity($resolution['entity'], $type);
        Gate::forUser($context['user'])->authorize('view', $entity);
        $resource = $this->directoryResource($entity, $type);

        return [
            'blocks' => [
                [
                    'text' => trans('chat.directory.detail_summary', ['name' => $this->directoryEntityResolver->label($entity, $type)], $context['locale']),
                    'type' => 'text',
                ],
                [
                    'component' => $tool['component'],
                    'data' => [
                        $type === 'event' ? 'event' : 'entity' => $resource,
                        'title' => trans('chat.directory.'.$type.'_detail_title', [], $context['locale']),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => [$this->directoryEntityRef($entity, $type, 'active')],
            'result_ref_json' => ['count' => 1, 'items' => [$resource]],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executeTaskDetailRead(array $tool, array $context, array $input): array
    {
        $resolution = $this->listTasksForTool->find(
            $context['workspace']->id,
            $input['task_id'] ?? null,
            $input['task_search'] ?? ($input['search'] ?? null),
            $context['entity_refs'] ?? []
        );

        if (($resolution['status'] ?? null) !== 'resolved') {
            $locale = (string) ($context['locale'] ?? 'en');
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                'task',
                'task_id',
                (string) ($input['task_search'] ?? ($input['search'] ?? ''))
            );
            if ($clarification !== null) {
                return $clarification;
            }
            $text = ($resolution['status'] ?? null) === 'ambiguous'
                ? trans('chat.tasks.ambiguous', [], $locale)
                : trans('chat.tasks.not_found', [], $locale);
            return [
                'blocks' => [['text' => $text, 'type' => 'text']],
                'entity_refs' => [],
                'result_ref_json' => ['candidates' => $resolution['candidates'] ?? []],
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        }

        /** @var Task $task */
        $task = $resolution['entity'];
        Gate::forUser($context['user'])->authorize('view', $task);
        $resource = (new TaskResource($task))->resolve();
        $locale = (string) ($context['locale'] ?? 'en');

        return [
            'blocks' => [
                ['text' => trans('chat.tasks.detail_summary', ['title' => $task->title], $locale), 'type' => 'text'],
                ['component' => $tool['component'], 'data' => [
                    'tasks' => [$resource],
                    'title' => trans('chat.tasks.detail_title', [], $locale),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [$this->taskEntityRef($resource, 'active')],
            'result_ref_json' => ['count' => 1, 'items' => [$resource]],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executeDocumentOrBeoDetailRead(array $tool, array $context, array $input): array
    {
        $locale = (string) ($context['locale'] ?? 'en');
        if (str_starts_with($tool['key'], 'documents.')) {
            $resolution = $this->listDocumentsForTool->find($context['workspace']->id, $input['document_id'] ?? null, $input['document_search'] ?? null, $context['entity_refs'] ?? []);
            if (($resolution['status'] ?? null) !== 'resolved') {
                $clarification = $this->entityResolutionClarificationResult(
                    $tool,
                    $context,
                    $resolution,
                    ['input' => $input],
                    'document',
                    'document_id',
                    (string) ($input['document_search'] ?? '')
                );
                return $clarification ?? $this->genericResolutionResult($tool, $context, $resolution, 'document');
            }
            /** @var Document $document */
            $document = $resolution['entity'];
            Gate::forUser($context['user'])->authorize('view', $document);
            if ($tool['key'] === 'documents.detail') {
                $items = [(new DocumentResource($document))->resolve()];
            } else {
                $items = $document->latestBeoVersion
                    ? BeoVersionResource::collection(BeoVersion::query()->where('workspace_id', $context['workspace']->id)->where('beo_id', $document->latestBeoVersion->beo_id)->orderByDesc('version')->get())->resolve()
                    : [];
            }
            return $this->genericReadResult($tool, $context, $items, $document->name, $this->genericEntityRef($document->id, 'document', $items[0] ?? []));
        }

        $resolution = $this->listBeosForTool->find($context['workspace']->id, $input['beo_id'] ?? null, $input['beo_search'] ?? null);
        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                'BEO',
                'beo_id',
                (string) ($input['beo_search'] ?? '')
            );
            return $clarification ?? $this->genericResolutionResult($tool, $context, $resolution, 'BEO');
        }
        /** @var Beo $beo */
        $beo = $resolution['entity'];
        Gate::forUser($context['user'])->authorize('view', $beo);
        $items = $tool['key'] === 'beos.versions'
            ? BeoVersionResource::collection($beo->versions()->with(['document', 'functions', 'references'])->orderByDesc('version')->get())->resolve()
            : [(new BeoResource($beo))->resolve()];
        return $this->genericReadResult($tool, $context, $items, $beo->event_order_number ?: ($beo->event?->name ?? $beo->id), $this->genericEntityRef($beo->id, 'beo', $items[0] ?? []));
    }

    private function genericReadResult(array $tool, array $context, array $items, string $label, array $ref): array
    {
        $locale = (string) ($context['locale'] ?? 'en');
        return [
            'blocks' => [
                ['text' => trans('chat.capabilities.detail_summary', ['name' => $label], $locale), 'type' => 'text'],
                ['component' => 'action.result', 'data' => [
                    'details' => [['label' => trans('chat.capabilities.records_label', [], $locale), 'value' => (string) count($items)]],
                    'items' => $items,
                    'status' => 'success',
                    'title' => trans('chat.capabilities.result_title', [], $locale),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [$ref],
            'result_ref_json' => ['count' => count($items), 'items' => $items],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function genericResolutionResult(array $tool, array $context, array $resolution, string $entity): array
    {
        if (($resolution['status'] ?? null) === 'system_failure') {
            return $this->semanticFallbackFailureResult($tool, $context);
        }
        if (($resolution['status'] ?? null) === 'clarification_required') {
            return $this->semanticFallbackClarificationResult($tool, $context, $entity);
        }
        $locale = (string) ($context['locale'] ?? 'en');
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.capabilities.ambiguous', ['entity' => $entity], $locale)
            : trans('chat.capabilities.not_found', ['entity' => $entity], $locale);
        return ['status' => ($resolution['status'] ?? null) === 'ambiguous' ? 'clarification_required' : 'final_not_found', 'blocks' => [['text' => $text, 'type' => 'text']], 'entity_refs' => [], 'result_ref_json' => ['candidates' => $resolution['candidates'] ?? []], 'tool' => $this->toolRegistry->metadata($tool)];
    }

    private function entityResolutionClarificationResult(
        array $tool,
        array $context,
        array $resolution,
        array $payload,
        string $entityLabel,
        string $field,
        string $reference,
        ?string $entityType = null
    ): ?array {
        $status = $resolution['status'] ?? null;
        if (!in_array($status, ['ambiguous', 'suggested_match'], true)) {
            return null;
        }

        $candidates = $resolution['candidates'] ?? [];
        if (!is_array($candidates) || $candidates === []) {
            return null;
        }

        $resolvedEntityType = $entityType ?? (string) ($tool['entity_type'] ?? 'record');
        if (!in_array($resolvedEntityType, [
            'client', 'contact', 'event', 'venue', 'document', 'beo', 'menu', 'recipe',
            'prep_list', 'prep_item', 'task', 'team', 'station', 'shift', 'membership',
        ], true)) {
            return null;
        }

        return $this->entityDisambiguationResult(
            $tool,
            $context,
            $payload,
            $field,
            $reference,
            $candidates,
            $status === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate',
            $resolvedEntityType,
            $entityLabel
        );
    }

    private function semanticFallbackFailureResult(array $tool, array $context): array
    {
        $locale = (string) ($context['locale'] ?? 'en');

        return [
            'status' => 'failed',
            'blocks' => [[
                'component' => 'error.recovery',
                'data' => [
                    'description' => trans('chat.fallback.retryable_description', [], $locale),
                    'error_code' => 'AI_FALLBACK_UNAVAILABLE',
                    'safe_detail' => trans('chat.fallback.retryable_description', [], $locale),
                    'title' => trans('chat.fallback.retryable_title', [], $locale),
                ],
                'schema_version' => 1,
                'type' => 'component',
            ]],
            'entity_refs' => [],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function semanticFallbackClarificationResult(array $tool, array $context, string $entity): array
    {
        return [
            'status' => 'clarification_required',
            'blocks' => [[
                'text' => trans('chat.fallback.clarification', ['entity' => $entity], $context['locale']),
                'type' => 'text',
            ]],
            'entity_refs' => [],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function genericEntityRef(string $id, string $type, array $snapshot): array
    {
        return ['id' => $id, 'role' => 'active', 'snapshot' => $snapshot, 'type' => $type];
    }

    private function listWorkspaceMemberDetail(array $context, array $input): array
    {
        $resolution = $this->listWorkspaceMembersForTool->find($context['workspace']->id, $input['membership_id'] ?? null, $input['member_search'] ?? null, $context['entity_refs'] ?? []);
        if (($resolution['status'] ?? null) !== 'resolved') {
            return ['items' => [], 'ref' => ['id' => null, 'role' => 'active', 'snapshot' => ['candidates' => $resolution['candidates'] ?? []], 'type' => 'membership']];
        }
        $resource = $this->listWorkspaceMembersForTool->serialize($resolution['entity']);
        return ['items' => [$resource], 'ref' => $this->genericEntityRef($resolution['entity']->id, 'membership', $resource)];
    }

    private function taskEntityRefs(array $items): array
    {
        return collect($items)->map(fn (array $item, int $index): array => [
            ...$this->taskEntityRef($item, $index === 0 ? 'active' : 'recent'),
            'ordinal' => $index + 1,
        ])->filter(fn (array $ref): bool => filled($ref['id'] ?? null))->values()->all();
    }

    private function taskEntityRef(array $task, string $role): array
    {
        return [
            'id' => $task['id'] ?? null,
            'role' => $role,
            'snapshot' => $task,
            'type' => 'task',
            'version' => $task['version'] ?? 1,
        ];
    }

    private function executeRecipeRead(array $tool, array $context, array $input): array
    {
        $resolution = $this->recipeEntityResolver->resolve(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $input['recipe_id'] ?? null,
            $input['recipe_search'] ?? null,
            $input['recipe_version_id'] ?? null,
            $tool['key'],
            (string) ($context['user_message']->content_text ?? '')
        );

        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                'recipe',
                'recipe_id',
                (string) ($input['recipe_search'] ?? '')
            );
            return $clarification ?? $this->recipeResolutionResult($tool, $context, $resolution);
        }

        /** @var Recipe $recipe */
        $recipe = $resolution['recipe'];
        Gate::forUser($context['user'])->authorize('view', $recipe);
        $version = $resolution['version'] instanceof RecipeVersion
            ? $resolution['version']
            : $recipe->currentVersionRecord;

        if ($tool['key'] === 'recipes.versions') {
            $versions = RecipeVersion::query()
                ->where('workspace_id', $context['workspace']->id)
                ->where('recipe_id', $recipe->id)
                ->with(['ingredients.unit', 'steps.temperatureUnit', 'yields.unit', 'allergens'])
                ->orderByDesc('version')
                ->get();
            $items = RecipeVersionResource::collection($versions)->resolve();
        } elseif ($tool['key'] === 'recipes.scale') {
            if (!$version) {
                return $this->recipeResolutionResult($tool, $context, ['status' => 'missing']);
            }
            $targetYield = is_array($input['target_yield'] ?? null)
                ? $input['target_yield']
                : ['quantity' => $input['target_quantity'] ?? null, 'unit_id' => $input['target_unit_id'] ?? null];
            $scaled = $this->scaleRecipe->execute($version, $targetYield);
            return [
                'blocks' => [
                    ['text' => trans('chat.recipe.scale_summary', ['name' => $recipe->name], $context['locale']), 'type' => 'text'],
                    ['component' => $tool['component'], 'data' => [
                        'recipe' => (new RecipeResource($recipe))->resolve(),
                        'version' => (new RecipeVersionResource($version))->resolve(),
                        'scale_factor' => $scaled['scale_factor'],
                        'scaled_ingredients' => $scaled['scaled_ingredients'],
                        'title' => trans('chat.recipe.scale_title', [], $context['locale']),
                    ], 'schema_version' => 1, 'type' => 'component'],
                ],
                'entity_refs' => [$this->recipeEntityRef($recipe, 'active')],
                'result_ref_json' => $scaled,
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        } else {
            $items = [(new RecipeResource($recipe))->resolve()];
        }

        $data = $tool['key'] === 'recipes.versions'
            ? ['recipe' => (new RecipeResource($recipe))->resolve(), 'versions' => $items]
            : ['recipe' => $items[0] ?? null];

        return [
            'blocks' => [
                ['text' => trans('chat.recipe.detail_summary', ['name' => $recipe->name], $context['locale']), 'type' => 'text'],
                ['component' => $tool['component'], 'data' => [
                    ...$data,
                    'title' => trans(
                        $tool['key'] === 'recipes.versions'
                            ? 'chat.recipe.versions_title'
                            : 'chat.recipe.detail_title',
                        [],
                        $context['locale']
                    ),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [$this->recipeEntityRef($recipe, 'active')],
            'result_ref_json' => ['count' => count($items), 'items' => $items],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executeTeamStaffDetailRead(array $tool, array $context, array $input): array
    {
        $type = (string) $tool['entity_type'];
        $resolution = $this->teamStaffEntityResolver->resolve(
            $context['workspace']->id, $type, $input[$type.'_id'] ?? null,
            $input[$type.'_search'] ?? ($type === 'shift' ? ($input['member_search'] ?? null) : null),
            $context['entity_refs'] ?? []
        );
        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                $type,
                $type.'_id',
                (string) ($input[$type.'_search'] ?? ($type === 'shift' ? ($input['member_search'] ?? '') : ''))
            );
            return $clarification ?? $this->teamStaffResolutionResult($tool, $context, $resolution, $type);
        }
        $entity = $resolution['entity'];
        Gate::forUser($context['user'])->authorize('view', $entity);
        $resource = match ($type) {
            'team' => (new \App\Http\Resources\TeamResource($entity))->resolve(),
            'station' => (new \App\Http\Resources\StationResource($entity))->resolve(),
            default => (new \App\Http\Resources\ShiftResource($entity))->resolve(),
        };
        return [
            'blocks' => [
                ['text' => $this->teamStaffEntityResolver->label($entity, $type), 'type' => 'text'],
                ['component' => $tool['component'], 'data' => ['items' => [$resource], 'title' => trans('chat.team_staff.'.$type.'s', [], $context['locale'])], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [['id' => $entity->id, 'role' => 'active', 'snapshot' => $resource, 'type' => $type]],
            'result_ref_json' => ['count' => 1, 'items' => [$resource]],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executePrepRead(array $tool, array $context, array $input): array
    {
        $workspaceId = $context['workspace']->id;
        $locale = (string) ($context['locale'] ?? 'en');

        if ($tool['key'] === 'prep.items.list') {
            $result = $this->listPrepItemsForTool->execute($workspaceId, $input);

            return [
                'blocks' => [
                    ['text' => trans('chat.prep.items_summary', ['count' => $result['count']], $locale), 'type' => 'text'],
                    ['component' => 'prep.detail', 'data' => [
                        'items' => $result['items'],
                        'title' => trans('chat.prep.items_title', [], $locale),
                    ], 'schema_version' => 1, 'type' => 'component'],
                ],
                'entity_refs' => $this->prepItemEntityRefs($result['items']),
                'result_ref_json' => $result,
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        }

        if ($tool['key'] === 'prep.items.detail') {
            $resolution = $this->prepEntityResolver->resolveItem(
                $workspaceId,
                $context['entity_refs'] ?? [],
                $input['prep_item_id'] ?? null,
                $input['prep_item_search'] ?? null,
                $input['prep_list_id'] ?? null
            );
            if (($resolution['status'] ?? null) !== 'resolved') {
                $clarification = $this->entityResolutionClarificationResult(
                    $tool,
                    $context,
                    $resolution,
                    ['input' => $input],
                    'item',
                    'prep_item_id',
                    (string) ($input['prep_item_search'] ?? '')
                );
                return $clarification ?? $this->prepResolutionResult($tool, $context, $resolution, 'item');
            }
            $item = $resolution['item'];
            Gate::forUser($context['user'])->authorize('view', $item);
            $resource = (new PrepItemResource($item))->resolve();

            return [
                'blocks' => [
                    ['text' => trans('chat.prep.item_detail_summary', ['name' => $item->title], $locale), 'type' => 'text'],
                    ['component' => 'prep.detail', 'data' => [
                        'item' => $resource,
                        'title' => trans('chat.prep.item_title', [], $locale),
                    ], 'schema_version' => 1, 'type' => 'component'],
                ],
                'entity_refs' => [$this->prepItemEntityRef($resource, 'active')],
                'result_ref_json' => ['count' => 1, 'items' => [$resource]],
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        }

        $eventId = $input['event_id'] ?? null;
        if (!$eventId && !empty($input['event_search'])) {
            $eventResolution = $this->directoryEntityResolver->resolve(
                $workspaceId,
                'event',
                null,
                $input['event_search'],
                $context['entity_refs'] ?? []
            );
            if (($eventResolution['status'] ?? null) !== 'resolved') {
                $resolution = [
                    'status' => $eventResolution['status'] ?? 'missing',
                    'candidates' => collect($eventResolution['matches'] ?? [])->map(fn ($event): array => ['id' => $event->id, 'name' => $event->name])->values()->all(),
                ];
                $clarification = $this->entityResolutionClarificationResult(
                    $tool,
                    $context,
                    $resolution,
                    ['input' => $input],
                    'event',
                    'event_id',
                    (string) ($input['event_search'] ?? ''),
                    'event'
                );
                return $clarification ?? $this->prepResolutionResult($tool, $context, $resolution, 'event');
            }
            $eventId = $eventResolution['entity']->id;
        }

        $resolution = $this->prepEntityResolver->resolveList(
            $workspaceId,
            $context['entity_refs'] ?? [],
            $input['prep_list_id'] ?? null,
            $input['prep_list_search'] ?? null,
            $eventId
        );
        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                'list',
                'prep_list_id',
                (string) ($input['prep_list_search'] ?? '')
            );
            return $clarification ?? $this->prepResolutionResult($tool, $context, $resolution, 'list');
        }
        $prepList = $resolution['prep_list'];
        Gate::forUser($context['user'])->authorize('view', $prepList);
        $resource = (new PrepListResource($prepList))->resolve();

        return [
            'blocks' => [
                ['text' => trans('chat.prep.detail_summary', ['name' => $prepList->name], $locale), 'type' => 'text'],
                ['component' => 'prep.detail', 'data' => [
                    'prep_list' => $resource,
                    'title' => trans('chat.prep.detail_title', [], $locale),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [$this->prepListEntityRef($resource, 'active')],
            'result_ref_json' => ['count' => 1, 'items' => [$resource]],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function prepResolutionResult(array $tool, array $context, array $resolution, string $entity): array
    {
        if (($resolution['status'] ?? null) === 'system_failure') {
            return $this->semanticFallbackFailureResult($tool, $context);
        }
        if (($resolution['status'] ?? null) === 'clarification_required') {
            return $this->semanticFallbackClarificationResult($tool, $context, $entity);
        }
        $locale = (string) ($context['locale'] ?? 'en');
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.prep.ambiguous', ['entity' => $entity], $locale)
            : trans('chat.prep.not_found', ['entity' => $entity], $locale);

        return [
            'status' => ($resolution['status'] ?? null) === 'ambiguous' ? 'clarification_required' : 'final_not_found',
            'blocks' => [['text' => $text, 'type' => 'text']],
            'entity_refs' => [],
            'result_ref_json' => ['candidates' => $resolution['candidates'] ?? []],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function recipeEntityRefs(string $toolKey, array $items): array
    {
        if ($toolKey !== 'recipes.list') {
            return [];
        }
        return collect($items)->map(fn (array $item, int $index): array => [
            'id' => $item['id'] ?? null,
            'ordinal' => $index + 1,
            'role' => $index === 0 ? 'active' : 'recent',
            'snapshot' => $item,
            'type' => 'recipe',
            'version' => $item['current_version'] ?? null,
        ])->filter(fn (array $ref): bool => filled($ref['id'] ?? null))->values()->all();
    }

    private function recipeEntityRef(Recipe $recipe, string $role): array
    {
        return [
            'id' => $recipe->id,
            'role' => $role,
            'snapshot' => (new RecipeResource($recipe))->resolve(),
            'type' => 'recipe',
            'version' => $recipe->current_version,
        ];
    }

    private function prepListEntityRef(array $prepList, string $role): array
    {
        return [
            'id' => $prepList['id'] ?? null,
            'role' => $role,
            'snapshot' => $prepList,
            'type' => 'prep_list',
            'version' => $prepList['current_version'] ?? null,
        ];
    }

    private function prepItemEntityRef(array $item, string $role): array
    {
        return [
            'id' => $item['id'] ?? null,
            'role' => $role,
            'snapshot' => $item,
            'type' => 'prep_item',
            'version' => $item['version'] ?? null,
        ];
    }

    private function prepItemEntityRefs(array $items): array
    {
        return collect($items)->map(fn (array $item, int $index): array => [
            ...$this->prepItemEntityRef($item, $index === 0 ? 'active' : 'recent'),
            'ordinal' => $index + 1,
        ])->filter(fn (array $ref): bool => filled($ref['id'] ?? null))->values()->all();
    }

    private function prepListEntityRefsFromEntries(array $entries): array
    {
        return collect($entries)->map(function (array $entry, int $index): ?array {
            $prepList = is_array($entry['prep_list'] ?? null) ? $entry['prep_list'] : null;
            if ($prepList === null) {
                return null;
            }

            return [
                ...$this->prepListEntityRef($prepList, $index === 0 ? 'active' : 'recent'),
                'ordinal' => $index + 1,
            ];
        })->filter()->values()->all();
    }

    private function recipeResolutionResult(array $tool, array $context, array $resolution): array
    {
        if (($resolution['status'] ?? null) === 'system_failure') {
            return $this->semanticFallbackFailureResult($tool, $context);
        }
        if (($resolution['status'] ?? null) === 'clarification_required') {
            return $this->semanticFallbackClarificationResult($tool, $context, trans('chat.recipe.name_label', [], $context['locale']));
        }
        $candidates = $resolution['candidates'] ?? [];
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.recipe.ambiguous', [], $context['locale'])
            : trans('chat.recipe.not_found', [], $context['locale']);
        return [
            'status' => ($resolution['status'] ?? null) === 'ambiguous' ? 'clarification_required' : 'final_not_found',
            'blocks' => [['text' => $text, 'type' => 'text']],
            'entity_refs' => [],
            'result_ref_json' => ['candidates' => $candidates],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function entityDisambiguationResult(
        array $tool,
        array $context,
        array $payload,
        string $field,
        string $reference,
        array $candidates,
        string $mode = 'choose_candidate',
        ?string $entityType = null,
        ?string $entityLabel = null
    ): array
    {
        $conversation = $context['conversation'] ?? null;
        $resolvedEntityType = $entityType ?? (string) ($tool['entity_type'] ?? 'record');
        $resolvedEntityLabel = $entityLabel ?? $resolvedEntityType;
        if (!$conversation) {
            $locale = (string) ($context['locale'] ?? 'en');
            return [
                'status' => 'clarification_required',
                'blocks' => [['text' => trans('chat.capabilities.matches_description', ['count' => count($candidates)], $locale), 'type' => 'text']],
                'entity_refs' => [],
                'result_ref_json' => ['candidates' => $candidates],
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        }
        $clarificationId = (string) Str::ulid();
        $expiresAt = now()->addMinutes(15);
        $snapshot = collect($candidates)->map(fn (array $candidate): array => [
            'entity_id' => (string) ($candidate['id'] ?? ''),
            'display_name' => (string) ($candidate['name'] ?? ''),
            'safe_metadata' => is_array($candidate['safe_metadata'] ?? null) ? $candidate['safe_metadata'] : [],
        ])->filter(fn (array $candidate): bool => $candidate['entity_id'] !== '')->values()->all();
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['pending_clarifications'] = [...(is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : []), [
            'action_key' => $tool['key'], 'actor_id' => $context['user']->id, 'candidate_snapshot' => $snapshot,
            'clarification_id' => $clarificationId, 'conversation_id' => $conversation->id, 'entity_type' => $resolvedEntityType,
            'expires_at' => $expiresAt->toIso8601String(), 'original_payload' => [...$payload, 'action_id' => $tool['key']],
            'risk_level' => $tool['policy']['risk'] ?? 'impactful_write', 'status' => 'pending',
            'type' => 'entity.disambiguation', 'mode' => $mode, 'unresolved_field' => $field, 'workspace_id' => $context['workspace']->id,
            'original_reference' => $reference, 'locale' => $context['locale'],
        ]];
        $conversation->forceFill(['metadata' => $metadata])->save();

        return ['status' => 'clarification_required', 'blocks' => [[
            'actions' => $mode === 'confirm_suggestion' ? [['id' => 'entity.disambiguation.resolve'], ['id' => 'entity.disambiguation.reject']] : [['id' => 'entity.disambiguation.resolve']], 'component' => 'entity.disambiguation',
                'data' => ['clarification_id' => $clarificationId, 'description' => trans('chat.capabilities.matches_description', ['count' => count($snapshot)], $context['locale']), 'entity_type' => $resolvedEntityType, 'expires_at' => $expiresAt->toIso8601String(), 'mode' => $mode,
                'options' => collect($snapshot)->map(fn (array $candidate): array => ['id' => $candidate['entity_id'], 'label' => $candidate['display_name'], 'value' => $candidate['entity_id'], 'metadata' => $candidate['safe_metadata']])->all(),
                'original_reference' => $reference, 'interpreted_reference' => $snapshot[0]['display_name'] ?? null, 'selection_mode' => 'single', 'title' => $mode === 'confirm_suggestion' ? trans('chat.fallback.suggestion_title', ['entity' => $resolvedEntityLabel, 'name' => $snapshot[0]['display_name'] ?? ''], $context['locale']) : trans('chat.capabilities.ambiguous', ['entity' => $resolvedEntityLabel], $context['locale'])],
            'schema_version' => 1, 'type' => 'component']], 'entity_refs' => [], 'tool' => $this->toolRegistry->metadata($tool)];
    }

    private function menuResolutionResult(array $tool, array $context, array $resolution, string $entity = 'menu'): array
    {
        if (($resolution['status'] ?? null) === 'system_failure') {
            return $this->semanticFallbackFailureResult($tool, $context);
        }
        if (($resolution['status'] ?? null) === 'clarification_required') {
            return $this->semanticFallbackClarificationResult($tool, $context, $entity);
        }
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.menu.ambiguous', ['entity' => $entity], $context['locale'])
            : trans('chat.menu.not_found', [], $context['locale']);
        return [
            'status' => ($resolution['status'] ?? null) === 'ambiguous' ? 'clarification_required' : 'final_not_found',
            'blocks' => [['text' => $text, 'type' => 'text']],
            'entity_refs' => [],
            'result_ref_json' => ['candidates' => $resolution['candidates'] ?? []],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function menuEntityRefs(string $toolKey, array $items): array
    {
        if (!in_array($toolKey, ['menus.search', 'menus.show'], true)) {
            return [];
        }

        $role = $toolKey === 'menus.show' ? 'active' : 'recent';

        return collect($items)->map(fn (array $menu, int $index): array => [
            'id' => $menu['id'] ?? null,
            'ordinal' => $index + 1,
            'role' => $role,
            'snapshot' => $menu,
            'type' => 'menu',
            'version' => $menu['current_version'] ?? null,
        ])->filter(fn (array $reference): bool => filled($reference['id'] ?? null))->values()->all();
    }

    private function directoryEntityRefs(string $toolKey, array $items): array
    {
        $type = match ($toolKey) {
            'events.list' => 'event',
            'clients.list' => 'client',
            'contacts.list' => 'contact',
            'venues.list' => 'venue',
            default => null,
        };

        if ($type === null) {
            return [];
        }

        return collect($items)->map(function (array $item, int $index) use ($type): array {
            $label = $type === 'contact'
                ? (string) (($item['display_name'] ?? null) ?: ($item['full_name'] ?? null) ?: trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? '')))
                : (string) ($item['name'] ?? $item['id'] ?? '');

            return [
                'id' => $item['id'] ?? null,
                'ordinal' => $index + 1,
                'role' => $index === 0 ? 'active' : 'recent',
                'snapshot' => ['id' => $item['id'] ?? null, 'label' => $label, 'name' => $label, 'type' => $type],
                'type' => $type,
                'version' => $item['version'] ?? null,
            ];
        })->filter(fn (array $reference): bool => filled($reference['id'] ?? null))->values()->all();
    }

    private function executeImmediateTool(
        array $tool,
        array $context,
        array $payload
    ): array {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        if ($tool['key'] === 'notifications.read_all') {
            $updated = $this->markNotificationsRead->execute($context['workspace']->id, $context['user']->id);
            return $this->completedActionResult($tool, $context, ['updated' => $updated], (string) $updated);
        }

        $menuResolution = $this->menuEntityResolver->resolveMenu(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $input['menu_id'] ?? null,
            $input['menu_search'] ?? null
        );

        if (in_array($menuResolution['status'] ?? null, ['ambiguous', 'suggested_match'], true)) {
            return $this->entityDisambiguationResult(
                $tool,
                $context,
                ['input' => $input],
                'menu_id',
                (string) ($input['menu_search'] ?? ''),
                $menuResolution['candidates'] ?? [],
                ($menuResolution['status'] ?? null) === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate',
                'menu',
                'menu'
            );
        }

        if (($menuResolution['status'] ?? null) !== 'resolved') {
            throw ValidationException::withMessages([
                'menu' => ['A menu is required for this action.'],
            ]);
        }

        $menu = $menuResolution['menu'] ?? null;
        if (!$menu instanceof Menu) {
            throw ValidationException::withMessages([
                'menu' => ['A menu is required for this action.'],
            ]);
        }

        Gate::forUser($context['user'])->authorize('update', $menu);

        $updated = match ($tool['key']) {
            'menus.rename' => $this->updateMenuFromChat->rename(
                $menu,
                $context['workspace']->id,
                $context['user']->id,
                (string) ($input['name'] ?? '')
            ),
            'menus.items.add' => $this->addMenuItem($menu, $context, $input),
            'menus.items.move_section' => $this->moveMenuItem($menu, $context, $input),
            default => throw ValidationException::withMessages([
                'action_id' => ['The selected immediate tool is not executable.'],
            ]),
        };

        $resource = (new MenuResource($this->loadMenuForTool($context['workspace']->id, $updated->id)))->resolve();

        return [
            'blocks' => [
                [
                    'text' => trans('chat.menu.action_completed', ['name' => $resource['name']], $context['locale']),
                    'type' => 'text',
                ],
                [
                    'component' => 'action.result',
                    'data' => [
                        'description' => trans('chat.menu.action_description', [], $context['locale']),
                        'details' => [
                            ['label' => trans('chat.menu.menu_label', [], $context['locale']), 'value' => $resource['name']],
                            ['label' => trans('chat.menu.items_label', [], $context['locale']), 'value' => (string) ($resource['item_count'] ?? 0)],
                        ],
                        'status' => 'success',
                        'title' => trans('chat.menu.action_title', [], $context['locale']),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => [[
                'id' => $resource['id'],
                'role' => 'active',
                'snapshot' => $resource,
                'type' => 'menu',
            ]],
            'result_ref_json' => $resource,
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function addMenuItem(Menu $menu, array $context, array $input): Menu
    {
        $section = $this->menuEntityResolver->resolveSection(
            $menu,
            $input['section_id'] ?? null,
            $input['section_search'] ?? null
        );

        if (($section['status'] ?? null) !== 'resolved') {
            throw ValidationException::withMessages(['section' => ['The target menu section is missing or ambiguous.']]);
        }

        return $this->updateMenuFromChat->addItem(
            $menu,
            $context['workspace']->id,
            $context['user']->id,
            $section['section']->id,
            ['name' => $input['item_name'] ?? null]
        );
    }

    private function moveMenuItem(Menu $menu, array $context, array $input): Menu
    {
        $item = $this->menuEntityResolver->resolveItem(
            $menu,
            $input['item_id'] ?? null,
            $input['item_search'] ?? null
        );
        $section = $this->menuEntityResolver->resolveSection(
            $menu,
            $input['target_section_id'] ?? null,
            $input['target_section_search'] ?? null
        );

        if (($item['status'] ?? null) !== 'resolved' || ($section['status'] ?? null) !== 'resolved') {
            throw ValidationException::withMessages(['menu' => ['The menu item or target section is missing or ambiguous.']]);
        }

        return $this->updateMenuFromChat->moveItem(
            $menu,
            $context['workspace']->id,
            $context['user']->id,
            $item['item']->id,
            $section['section']->id
        );
    }

    private function previewWriteTool(
        array $tool,
        array $context,
        array $payload
    ): array {
        $source = $this->resolveConfirmationSource($context);

        return match ($tool['key']) {
            'prep.generate', 'prep.regenerate' => $this->previewPrepGeneration($tool, $context, $payload, $source),
            'prep.update' => $this->previewPrepListUpdate($tool, $context, $payload, $source),
            'prep.items.update', 'prep_items.update', 'prep.items.complete', 'prep.items.reopen', 'prep.items.assign', 'prep.items.unassign'
                => $this->previewPrepItemUpdate($tool, $context, $payload, $source),
            'tasks.create' => $this->previewTaskCreate($tool, $context, $payload, $source),
            'tasks.update', 'tasks.status.update', 'tasks.complete' => $this->previewTaskUpdate($tool, $context, $payload, $source),
            'tasks.assign' => $this->previewTaskAssignment($tool, $context, $payload, $source),
            'tasks.delete' => $this->previewTaskDelete($tool, $context, $payload, $source),
            'documents.retry_extraction', 'documents.link_event' => $this->previewDocumentWrite($tool, $context, $payload, $source),
            'notification_preferences.update' => $this->previewNotificationPreferenceUpdate($tool, $context, $payload, $source),
            'workspace.update', 'members.invite', 'members.update', 'members.remove' => $this->previewWorkspaceWrite($tool, $context, $payload, $source),
            'menus.create' => $this->previewMenuCreate($tool, $context, $payload, $source),
            'menus.rename', 'menus.items.add', 'menus.items.move_section' => $this->previewMenuAction($tool, $context, $payload, $source),
            'menus.update', 'menus.items.update', 'menus.items.delete' => $this->previewMenuWrite($tool, $context, $payload, $source),
            'recipes.create', 'recipes.update' => $this->previewRecipeWrite($tool, $context, $payload, $source),
            'teams.create', 'teams.update', 'teams.delete', 'teams.members.sync',
            'stations.create', 'stations.update', 'stations.delete',
            'shifts.create', 'shifts.update', 'shifts.delete', 'availability.sync'
                => $this->previewTeamStaffWrite($tool, $context, $payload, $source),
            'events.create', 'events.update', 'events.cancel', 'events.delete',
            'clients.create', 'clients.update', 'clients.delete',
            'contacts.create', 'contacts.update', 'contacts.delete',
            'venues.create', 'venues.update', 'venues.delete'
                => $this->previewDirectoryWrite($tool, $context, $payload, $source),
            default => throw ValidationException::withMessages([
                'action_id' => ['The selected action is not a writable tool.'],
            ]),
        };
    }

    private function previewMenuWrite(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $resolution = $this->menuEntityResolver->resolveMenu(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $input['menu_id'] ?? null,
            $input['menu_search'] ?? null
        );
        if (($resolution['status'] ?? null) !== 'resolved') {
            if (in_array($resolution['status'] ?? null, ['ambiguous', 'suggested_match'], true)) {
                return $this->entityDisambiguationResult(
                    $tool,
                    $context,
                    $payload,
                    'menu_id',
                    (string) ($input['menu_search'] ?? ''),
                    $resolution['candidates'] ?? [],
                    ($resolution['status'] ?? null) === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate'
                );
            }

            return $this->menuResolutionResult($tool, $context, $resolution);
        }
        /** @var Menu $menu */
        $menu = $resolution['menu'];
        Gate::forUser($context['user'])->authorize('update', $menu);
        $entity = [
            'id' => $menu->id,
            'type' => 'menu',
            'version' => $menu->currentVersionRecord?->revision ?? 1,
            'current_version_id' => $menu->currentVersionRecord?->id,
        ];
        $changes = [];
        $draftInput = $input;

        if (in_array($tool['key'], ['menus.items.update', 'menus.items.delete'], true)) {
            $itemResolution = $this->menuEntityResolver->resolveItem($menu, $input['item_id'] ?? null, $input['item_search'] ?? null);
            if (($itemResolution['status'] ?? null) !== 'resolved') {
                return $this->menuResolutionResult($tool, $context, $itemResolution, 'item');
            }
            $item = $itemResolution['item'];
            $draftInput['item_id'] = $item->id;
            $changes = $tool['key'] === 'menus.items.delete'
                ? [['label' => trans('chat.menu.item_label', [], $context['locale']), 'before' => $item->name, 'after' => trans('chat.menu.removed', [], $context['locale'])]]
                : collect($input)->only(['name', 'description', 'notes', 'quantity_per_guest', 'serving_unit', 'recipe_id', 'recipe_version_id', 'active', 'optional'])
                    ->map(fn ($value, $key): array => ['label' => $key, 'before' => (string) ($item->{$key} ?? ''), 'after' => is_scalar($value) ? (string) $value : json_encode($value)])
                    ->values()->all();
        } else {
            $changes = collect($input)->only(['name', 'description', 'type', 'status', 'default_guest_count', 'event_id', 'sections'])
                ->map(fn ($value, $key): array => ['label' => $key, 'before' => (string) ($menu->{$key} ?? ''), 'after' => is_scalar($value) ? (string) $value : json_encode($value)])
                ->values()->all();
        }
        $this->assertHasChanges($changes);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $menu->name,
                'changes' => $changes,
                'destructive' => $tool['key'] === 'menus.items.delete',
                'description' => trans('chat.menu.write_preview_description', [], $context['locale']),
                'metadata' => [['label' => trans('chat.menu.menu_label', [], $context['locale']), 'value' => $menu->name]],
                'title' => trans('chat.menu.write_preview_title', [], $context['locale']),
                'type' => trans('chat.menu.write_preview_type', [], $context['locale']),
            ],
            [['label' => trans('chat.menu.menu_label', [], $context['locale']), 'value' => $menu->name]],
            ['entity' => $entity, 'input' => $draftInput, 'tool_key' => $tool['key']]
        );
    }

    /**
     * Moves the historical menu action tools onto the same preview boundary
     * as every other write. The actual mutation remains in the existing
     * executeImmediateTool implementation and only runs from confirm().
     */
    private function previewMenuAction(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $menuId = trim((string) ($input['menu_id'] ?? ''));
        if ($menuId === '') {
            throw ValidationException::withMessages(['menu_id' => ['An exact menu ID is required.']]);
        }

        $menu = Menu::query()
            ->where('workspace_id', $context['workspace']->id)
            ->whereKey($menuId)
            ->firstOrFail();
        Gate::forUser($context['user'])->authorize('update', $menu);

        $details = match ($tool['key']) {
            'menus.rename' => [
                ['label' => trans('chat.menu.menu_label', [], $context['locale']), 'before' => (string) $menu->name, 'after' => (string) ($input['name'] ?? '')],
            ],
            'menus.items.add' => [
                ['label' => trans('chat.menu.menu_label', [], $context['locale']), 'value' => (string) $menu->name],
                ['label' => trans('chat.menu.item_label', [], $context['locale']), 'after' => (string) ($input['item_name'] ?? '')],
            ],
            default => [
                ['label' => trans('chat.menu.menu_label', [], $context['locale']), 'value' => (string) $menu->name],
                ['label' => trans('chat.menu.item_label', [], $context['locale']), 'value' => (string) ($input['item_id'] ?? '')],
                ['label' => trans('chat.menu.section_label', [], $context['locale']), 'after' => (string) ($input['target_section_id'] ?? '')],
            ],
        };

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            ['action_id' => $tool['key'], 'input' => $input],
            [
                'description' => trans('chat.menu.write_preview_description', [], $context['locale']),
                'details' => $details,
                'status' => 'pending',
                'title' => trans('chat.menu.write_preview_title', [], $context['locale']),
                'type' => trans('chat.menu.write_preview_type', [], $context['locale']),
            ],
            $details,
            [
                'entity' => ['id' => $menu->id, 'type' => 'menu', 'version' => (int) ($menu->current_version ?? 1)],
                'input' => $input,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function executeMenuWrite(array $tool, array $context, array $draft): array
    {
        $input = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        $entity = is_array($draft['entity'] ?? null) ? $draft['entity'] : [];
        $menu = $this->loadMenuForTool($context['workspace']->id, (string) ($entity['id'] ?? ''));
        Gate::forUser($context['user'])->authorize('update', $menu);

        if ($tool['key'] === 'menus.items.update' || $tool['key'] === 'menus.items.delete') {
            $item = $this->menuEntityResolver->resolveItem($menu, $input['item_id'] ?? null, $input['item_search'] ?? null);
            if (($item['status'] ?? null) !== 'resolved') {
                throw ValidationException::withMessages(['item' => ['The menu item is no longer available.']]);
            }
            $updated = $tool['key'] === 'menus.items.delete'
                ? $this->updateMenuFromChat->deleteItem($menu, $context['workspace']->id, $context['user']->id, $item['item']->id)
                : $this->updateMenuFromChat->updateItem($menu, $context['workspace']->id, $context['user']->id, $item['item']->id, $this->menuItemChanges($input));
        } else {
            $payload = $this->updateMenuFromChat->payload($menu);
            foreach (['name', 'description', 'type', 'status', 'default_guest_count', 'event_id', 'sections'] as $field) {
                if (array_key_exists($field, $input)) {
                    $payload[$field] = $input[$field];
                }
            }
            $updated = $this->updateMenuFromChat->updatePayload($menu, $context['workspace']->id, $context['user']->id, $payload);
        }

        $resource = (new MenuResource($this->loadMenuForTool($context['workspace']->id, $updated->id)))->resolve();
        return $this->completedActionResult($tool, $context, $resource, $resource['name'] ?? '');
    }

    private function menuItemChanges(array $input): array
    {
        return collect($input)->only(['name', 'description', 'notes', 'quantity_per_guest', 'serving_unit', 'recipe_id', 'recipe_version_id', 'active', 'optional'])->all();
    }

    private function previewRecipeWrite(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $draft = is_array($input['recipe_draft'] ?? null) ? $input['recipe_draft'] : $input;
        if ($tool['key'] === 'recipes.update') {
            $resolution = $this->recipeEntityResolver->resolve(
                $context['workspace']->id,
                $context['entity_refs'] ?? [],
                $input['recipe_id'] ?? null,
                $input['recipe_search'] ?? null,
                null,
                $tool['key'],
                (string) ($context['user_message']->content_text ?? '')
            );
            if (($resolution['status'] ?? null) !== 'resolved') {
                if (in_array($resolution['status'] ?? null, ['ambiguous', 'suggested_match'], true)) {
                    return $this->entityDisambiguationResult($tool, $context, $payload, 'recipe_id', (string) ($input['recipe_search'] ?? ''), $resolution['candidates'] ?? [], ($resolution['status'] ?? null) === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate');
                }
                return $this->recipeResolutionResult($tool, $context, $resolution);
            }
            if (!is_array($draft['version'] ?? null)) {
                return $this->recipeUpdateClarificationResult($tool, $context, $resolution['recipe']->name);
            }
            $draft['recipe_id'] = $resolution['recipe']->id;
            $draft['current_version_id'] = $resolution['version']?->id;
            $draft['expected_revision'] = $resolution['version']?->revision;
            Gate::forUser($context['user'])->authorize('update', $resolution['recipe']);
        } else {
            Gate::forUser($context['user'])->authorize('create', Recipe::class);
            Log::info('ai.recipe_draft.domain_validation_started', [
                'stage' => 'recipe_draft_domain_validation',
                'validator' => 'RecipeInputIngestionPipeline',
                'action_key' => 'recipes.create',
                'correlation_id' => $context['correlation_id'] ?? null,
                'workspace_id' => $context['workspace']->id,
            ]);
            try {
                $ingestion = $this->recipeInputIngestionPipeline->ingest(
                    $input,
                    is_string($input['raw_recipe_text'] ?? null) ? $input['raw_recipe_text'] : null,
                    (string) ($context['locale'] ?? 'en')
                );
            } catch (\Throwable $exception) {
                Log::warning('ai.recipe_draft.domain_validation_failed', [
                    'stage' => 'recipe_draft_domain_validation',
                    'validator' => 'RecipeInputIngestionPipeline',
                    'action_key' => 'recipes.create',
                    'error_code' => 'internal_failure',
                    'field_path' => 'recipe_draft',
                    'reason_code' => 'domain_validation_failed',
                    'correlation_id' => $context['correlation_id'] ?? null,
                    'exception_class' => class_basename($exception),
                    'workspace_id' => $context['workspace']->id,
                ]);
                throw $exception;
            }
            Log::info('ai.recipe_draft.domain_validation_passed', [
                'stage' => 'recipe_draft_domain_validation',
                'validator' => 'RecipeCreatePayloadBuilder',
                'action_key' => 'recipes.create',
                'status' => ($ingestion['status'] ?? null) === 'ready' ? 'ready' : 'needs_clarification',
                'issue_codes' => array_values(array_unique(array_column($ingestion['issues'] ?? [], 'code'))),
                'field_paths' => array_values(array_filter(array_column($ingestion['issues'] ?? [], 'field_path'))),
                'correlation_id' => $context['correlation_id'] ?? null,
                'workspace_id' => $context['workspace']->id,
            ]);
            $this->rememberRecipeIngestionDraft($context, $ingestion);
            if (($ingestion['status'] ?? null) !== 'ready') {
                return $this->recipeIngestionClarificationResult($context, $ingestion);
            }
            $draft = $ingestion['payload'];
        }
        $normalized = $this->validateRecipeInput($draft, $tool['key'] === 'recipes.update');
        $conversationMetadata = is_array($context['conversation']->metadata)
            ? $context['conversation']->metadata
            : [];
        $draftState = is_array($conversationMetadata['active_recipe_draft_state'] ?? null)
            ? $conversationMetadata['active_recipe_draft_state']
            : [];
        $yield = $tool['key'] === 'recipes.create'
            ? (is_array($draft['yield'] ?? null) ? $draft['yield'] : [])
            : [];
        $ingredientCount = $tool['key'] === 'recipes.create'
            ? count($draft['ingredients'] ?? [])
            : count($normalized['version']['ingredients'] ?? []);
        $stepCount = $tool['key'] === 'recipes.create'
            ? count($draft['steps'] ?? [])
            : count($normalized['version']['steps'] ?? []);
        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $normalized['name'],
                'changes' => [['label' => trans('chat.recipe.name_label', [], $context['locale']), 'after' => $normalized['name']]],
                'description' => trans('chat.recipe.write_preview_description', [], $context['locale']),
                'metadata' => [
                    ['label' => trans('chat.recipe.name_label', [], $context['locale']), 'value' => $normalized['name']],
                    ...($tool['key'] === 'recipes.create' ? [
                        ['label' => trans('chat.recipe.yield_label', [], $context['locale']), 'value' => trim((string) ($yield['quantity'] ?? '').' '.trans('chat.recipe.portions_label', [], $context['locale']))],
                        ['label' => trans('chat.recipe.ingredients_label', [], $context['locale']), 'value' => (string) $ingredientCount],
                        ['label' => trans('chat.recipe.steps_label', [], $context['locale']), 'value' => (string) $stepCount],
                    ] : []),
                ],
                'title' => trans('chat.recipe.write_preview_title', [], $context['locale']),
                'type' => trans('chat.recipe.write_preview_type', [], $context['locale']),
                'draft_id' => $draftState['draft_id'] ?? null,
                'revision' => $draftState['revision'] ?? null,
                'yield' => $yield,
                'ingredient_count' => $ingredientCount,
                'step_count' => $stepCount,
                'actions' => ['confirm', 'edit', 'cancel'],
            ],
            [['label' => trans('chat.recipe.name_label', [], $context['locale']), 'value' => $normalized['name']]],
            ['entity' => $tool['key'] === 'recipes.update' ? ['id' => $normalized['recipe_id'], 'type' => 'recipe', 'version' => $normalized['expected_revision']] : null, 'input' => $normalized, 'tool_key' => $tool['key'], 'draft_state' => $draftState]
        );
    }

    private function recipeDraftFromCurrentVersion(Recipe $recipe, ?RecipeVersion $version): array
    {
        if (!$version) {
            return [];
        }

        $version->loadMissing(['ingredients.unit', 'steps', 'yields', 'allergens']);

        return [
            'category' => $recipe->category,
            'description' => $recipe->description,
            'metadata' => $recipe->metadata,
            'name' => $recipe->name,
            'recipe_code' => $recipe->recipe_code,
            'status' => $recipe->status,
            'tags' => $recipe->relationLoaded('tags') ? $recipe->tags->pluck('id')->all() : [],
            'type' => $recipe->type,
            'version' => [
                'allergens' => $version->allergens->map(fn ($allergen): array => [
                    'id' => $allergen->id,
                    'presence' => $allergen->pivot->presence ?? 'contains',
                    'source' => $allergen->pivot->source ?? 'manual',
                ])->all(),
                'category' => $version->category,
                'description' => $version->description,
                'ingredients' => $version->ingredients->map(fn ($ingredient): array => [
                    'component_recipe_id' => $ingredient->component_recipe_id,
                    'component_recipe_version_id' => $ingredient->component_recipe_version_id,
                    'conversion_factor' => $ingredient->conversion_factor,
                    'cost_currency' => $ingredient->cost_currency,
                    'extended_cost' => $ingredient->extended_cost,
                    'ingredient_name' => $ingredient->ingredient_name,
                    'inventory_item_id' => $ingredient->inventory_item_id,
                    'notes' => $ingredient->notes,
                    'optional' => $ingredient->optional,
                    'preparation' => $ingredient->preparation,
                    'quantity' => (float) $ingredient->quantity,
                    'scalable' => $ingredient->scalable,
                    'unit_id' => $ingredient->unit_id,
                    'unit_label' => $ingredient->unit?->symbol ?? $ingredient->unit?->name,
                    'unit_cost' => $ingredient->unit_cost,
                    'waste_percentage' => $ingredient->waste_percentage,
                    'yield_percentage' => $ingredient->yield_percentage,
                ])->all(),
                'name' => $version->name,
                'status' => $version->status,
                'steps' => $version->steps->map(fn ($step): array => [
                    'critical' => $step->critical,
                    'duration_minutes' => $step->duration_minutes,
                    'instruction' => $step->instruction,
                    'notes' => $step->notes,
                    'station_id' => $step->station_id,
                    'temperature' => $step->temperature,
                    'temperature_unit_id' => $step->temperature_unit_id,
                    'title' => $step->title,
                    'type' => $step->type,
                ])->all(),
                'yields' => $version->yields->map(fn ($yield): array => [
                    'factor_to_base' => $yield->factor_to_base,
                    'is_default' => $yield->is_default,
                    'label' => $yield->label,
                    'quantity' => (float) $yield->quantity,
                    'unit_id' => $yield->unit_id,
                ])->all(),
            ],
        ];
    }

    private function recipeUpdateClarificationResult(array $tool, array $context, string $recipeName): array
    {
        $locale = (string) ($context['locale'] ?? 'en');
        $text = $locale === 'es'
            ? "Encontré {$recipeName}, pero la actualización requiere un borrador estructurado completo. No se aceptan cambios en lenguaje natural."
            : "I found {$recipeName}, but a complete structured draft is required for the update. Natural-language patches are not accepted.";

        return [
            'blocks' => [['text' => $text, 'type' => 'text']],
            'entity_refs' => [],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executeRecipeWrite(array $tool, array $context, array $draft): array
    {
        $input = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        $workspaceId = $context['workspace']->id;
        if ($tool['key'] === 'recipes.create') {
            Gate::forUser($context['user'])->authorize('create', Recipe::class);
            $recipe = $this->createRecipe->execute($workspaceId, $context['user']->id, $this->validateRecipeInput($input, false));
        } else {
            $entity = is_array($draft['entity'] ?? null) ? $draft['entity'] : [];
            $recipe = Recipe::query()->where('workspace_id', $workspaceId)->whereKey($entity['id'] ?? null)->with($this->recipeEntityResolver->relations())->firstOrFail();
            Gate::forUser($context['user'])->authorize('update', $recipe);
            $updated = $this->updateRecipe->execute($recipe, $workspaceId, $context['user']->id, (string) $input['current_version_id'], (int) $input['expected_revision'], $this->validateRecipeInput($input, true));
            if (!$updated) {
                throw ValidationException::withMessages(['version' => [trans('chat.recipe.conflict', [], $context['locale'])]]);
            }
            $recipe = $updated;
        }
        $recipe = Recipe::query()->where('workspace_id', $workspaceId)->whereKey($recipe->id)->with($this->recipeEntityResolver->relations())->firstOrFail();
        Log::info('ai.capability.executed', [
            'action_key' => $tool['key'],
            'correlation_id' => $context['correlation_id'] ?? $draft['orchestration_correlation_id'] ?? null,
            'recipe_id' => $recipe->id,
            'workspace_id' => $workspaceId,
        ]);
        return $this->completedActionResult($tool, $context, (new RecipeResource($recipe))->resolve(), $recipe->name);
    }

    private function validateRecipeInput(array $input, bool $update): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'], 'category' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:64'], 'status' => ['nullable', 'in:draft,active,archived'],
            'recipe_code' => ['nullable', 'string', 'max:64'], 'tags' => ['nullable', 'array'],
            'version' => ['required', 'array'], 'version.name' => ['required', 'string', 'max:180'],
            'version.ingredients' => ['nullable', 'array'], 'version.steps' => ['nullable', 'array'],
            'version.yields' => ['nullable', 'array'],
            'version.yields.*.quantity' => ['required', 'numeric', 'gt:0'], 'version.yields.*.unit_id' => ['required', 'string'],
            'version.ingredients.*.ingredient_name' => ['required', 'string', 'max:180'],
            'version.ingredients.*.quantity' => ['required', 'numeric', 'gt:0'], 'version.ingredients.*.unit_id' => ['required', 'string'],
            'version.steps.*.instruction' => ['required', 'string'],
        ];
        if ($update) {
            $rules['recipe_id'] = ['required', 'ulid'];
            $rules['current_version_id'] = ['required', 'ulid'];
            $rules['expected_revision'] = ['required', 'integer', 'min:1'];
        }
        return Validator::make($input, $rules)->validate();
    }

    private function recipeIngestionClarificationResult(array $context, array $ingestion): array
    {
        $locale = (string) ($context['locale'] ?? 'en');
        $issues = $ingestion['issues'] ?? [];
        $missingName = collect($issues)->contains(fn (array $issue): bool => ($issue['code'] ?? null) === 'missing_name');
        if ($missingName) {
            $this->createRecipeNameClarification($context);

            return [
                'status' => 'clarification_required',
                'blocks' => [[
                    'text' => trans('chat.recipe.missing_name_question', [], $locale),
                    'type' => 'text',
                ]],
                'entity_refs' => [],
                'tool' => $this->toolRegistry->metadata($this->toolRegistry->resolve('recipes.create')),
            ];
        }
        $range = collect($issues)->firstWhere('code', 'quantity_range');
        $range ??= collect($issues)->firstWhere('code', 'yield_range');
        if (is_array($range)) {
            if (($range['code'] ?? null) === 'yield_range') {
                $range['field_path'] = 'yield.quantity';
                $range['ingredient'] = trans('chat.recipe.ingestion.missing_yield', [], $locale);
            }
            $clarificationId = $this->createRecipeRangeClarification($context, $range);
            return [
                'status' => 'clarification_required',
                'blocks' => [[
                    'actions' => [
                        ['id' => 'clarification.resolve'],
                        ['id' => 'clarification.cancel'],
                    ],
                    'component' => 'clarification.options',
                    'data' => [
                        'allow_custom' => true,
                        'clarification_id' => $clarificationId,
                        'custom_input' => ['min' => 0, 'type' => 'number', 'unit' => $range['unit'] ?? null],
                        'description' => trans('chat.recipe.ingestion.quantity_range', ['ingredient' => $range['ingredient'] ?: trans('chat.recipe.ingestion.ingredient', [], $locale), 'min' => $range['min'], 'max' => $range['max'], 'unit' => $range['unit'] ?? ''], $locale),
                        'expected_type' => 'number',
                        'options' => [
                            ['id' => 'min', 'label' => trim($range['min'].' '.($range['unit'] ?? '')), 'value' => $range['min']],
                            ['id' => 'max', 'label' => trim($range['max'].' '.($range['unit'] ?? '')), 'value' => $range['max']],
                            ['id' => 'custom', 'label' => trans('chat.clarification.custom', [], $locale), 'value' => null],
                        ],
                        'selection_mode' => 'single',
                        'title' => trans('chat.clarification.choose_quantity_title', ['ingredient' => $range['ingredient'] ?: trans('chat.recipe.ingestion.ingredient', [], $locale)], $locale),
                    ],
                    'schema_version' => 2,
                    'type' => 'component',
                ]],
                'entity_refs' => [],
                'tool' => $this->toolRegistry->metadata($this->toolRegistry->resolve('recipes.create')),
            ];
        }

        $missingIngredientField = collect($issues)->first(fn (array $issue): bool => in_array($issue['code'] ?? null, [
            'ingredient_quantity_missing',
            'ingredient_unit_missing',
        ], true));
        if (is_array($missingIngredientField)) {
            $clarificationId = $this->createRecipeFieldClarification($context, $missingIngredientField);
            $isUnitField = ($missingIngredientField['code'] ?? null) === 'ingredient_unit_missing';
            $clarificationOptions = $isUnitField ? $this->recipeUnitClarificationOptions() : [];
            $fieldLabel = trans('chat.recipe.ingestion.'.$missingIngredientField['code'], [
                'ingredient' => $missingIngredientField['ingredient'] ?? trans('chat.recipe.ingestion.ingredient', [], $locale),
            ], $locale);

            return [
                'status' => 'clarification_required',
                'blocks' => [[
                    'actions' => [
                        ['id' => 'clarification.resolve'],
                        ['id' => 'clarification.cancel'],
                    ],
                    'component' => 'clarification.options',
                    'data' => [
                        'allow_custom' => !$isUnitField,
                        'clarification_id' => $clarificationId,
                        'custom_input' => [
                            'min' => $isUnitField ? null : 0.0001,
                            'type' => $isUnitField ? 'select' : 'number',
                        ],
                        'description' => trans('chat.recipe.ingestion.missing_fields', ['fields' => $fieldLabel], $locale),
                        'expected_type' => $isUnitField ? 'string' : 'number',
                        'input_control' => $isUnitField ? 'select' : 'custom',
                        'options' => $clarificationOptions,
                        'selection_mode' => 'single',
                        'title' => trans('chat.recipe.ingestion.field_title', [], $locale),
                    ],
                    'schema_version' => 2,
                    'type' => 'component',
                ]],
                'entity_refs' => [],
                'tool' => $this->toolRegistry->metadata($this->toolRegistry->resolve('recipes.create')),
            ];
        }

        $labels = collect($issues)->map(function (array $issue) use ($locale): string {
            return trans('chat.recipe.ingestion.'.$issue['code'], [
                'ingredient' => $issue['ingredient'] ?? trans('chat.recipe.ingestion.ingredient', [], $locale),
            ], $locale);
        })->filter()->unique()->values()->all();
        return [
            'status' => 'clarification_required',
            'blocks' => [[
                'text' => trans('chat.recipe.ingestion.missing_fields', ['fields' => implode(', ', $labels)], $locale),
                'type' => 'text',
            ]],
            'entity_refs' => [],
            'tool' => $this->toolRegistry->metadata($this->toolRegistry->resolve('recipes.create')),
        ];
    }

    private function rememberRecipeIngestionDraft(array $context, array $ingestion): void
    {
        $conversation = $context['conversation'] ?? null;
        if (!$conversation || !is_array($ingestion['draft'] ?? null)) {
            return;
        }
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $previousState = is_array($metadata['active_recipe_draft_state'] ?? null)
            ? $metadata['active_recipe_draft_state']
            : [];
        $continuationId = (string) ($previousState['draft_id'] ?? $metadata['active_recipe_draft_continuation_id'] ?? Str::ulid());
        $revision = max(1, (int) ($previousState['revision'] ?? 0) + 1);
        $issues = is_array($ingestion['issues'] ?? null) ? $ingestion['issues'] : [];
        $draft = $ingestion['draft'];
        $status = ($ingestion['status'] ?? null) === 'ready' ? 'ready' : 'needs_clarification';
        $expiresAt = $previousState['expires_at'] ?? now()->addMinutes(30)->toIso8601String();
        $metadata['active_recipe_draft'] = $draft;
        $metadata['active_recipe_draft_continuation_id'] = $continuationId;
        $metadata['active_recipe_ingestion_issues'] = $issues;
        $metadata['active_recipe_draft_state'] = [
            'draft_id' => $continuationId,
            'conversation_id' => $conversation->id,
            'workspace_id' => $context['workspace']->id,
            'actor_id' => $context['user']->id,
            'action_key' => 'recipes.create',
            'payload' => $draft,
            'missing_fields' => collect($issues)->map(fn (array $issue): string => match ($issue['code'] ?? '') {
            'missing_name' => 'name',
            'missing_yield', 'yield_range', 'unknown_yield_unit' => 'yield.quantity',
                'missing_ingredients' => 'ingredients',
                'invalid_ingredient', 'ingredient_quantity_missing', 'ingredient_unit_missing', 'quantity_range' => (string) ($issue['field_path'] ?? 'ingredients'),
                'missing_steps' => 'steps',
                default => (string) ($issue['field_path'] ?? $issue['code'] ?? 'unknown'),
            })->unique()->values()->all(),
            'issues' => $issues,
            'status' => $status,
            'revision' => $revision,
            'expires_at' => $expiresAt,
        ];
        $metadata['pending_continuations'] = collect($metadata['pending_continuations'] ?? [])
            ->map(function (mixed $item): mixed {
                if (is_array($item) && ($item['kind'] ?? null) === 'draft' && ($item['entity_type'] ?? null) === 'recipe' && ($item['status'] ?? null) === 'pending') {
                    $item['status'] = 'superseded';
                }
                return $item;
            })
            ->reject(fn (mixed $item): bool => is_array($item)
                && ($item['kind'] ?? null) === 'draft'
                && ($item['action_key'] ?? null) === 'recipes.create'
                && ($item['continuation_id'] ?? null) === $continuationId)
            ->push([
                'action_key' => 'recipes.create',
                'actor_id' => $context['user']->id,
                'continuation_id' => $continuationId,
                'conversation_id' => $conversation->id,
                'entity_type' => 'recipe',
                'kind' => 'draft',
                'label' => $ingestion['draft']['name'] ?? data_get($ingestion['draft'], 'version.name') ?? 'Recipe draft',
                'payload' => $ingestion['draft'],
                'status' => 'pending',
                'draft_id' => $continuationId,
                'revision' => $revision,
                'target_type' => 'recipe_draft',
                'workspace_id' => $context['workspace']->id,
            ])->values()->all();
        $conversation->forceFill(['metadata' => $metadata])->save();
        Log::info('ai.draft.created', [
            'action_key' => 'recipes.create',
            'draft_id' => $continuationId,
            'conversation_id' => $conversation->id,
            'status' => $status,
            'revision' => $revision,
            'issue_codes' => array_values(array_unique(array_column($issues, 'code'))),
            'correlation_id' => $context['correlation_id'] ?? null,
            'workspace_id' => $context['workspace']->id,
        ]);
        Log::info('ai.draft.validation', [
            'action_key' => 'recipes.create',
            'draft_id' => $continuationId,
            'status' => $status,
            'issue_codes' => array_values(array_unique(array_column($issues, 'code'))),
            'revision' => $revision,
            'correlation_id' => $context['correlation_id'] ?? null,
            'workspace_id' => $context['workspace']->id,
        ]);
    }

    private function createRecipeRangeClarification(array $context, array $range): string
    {
        $conversation = $context['conversation'] ?? null;
        if (!$conversation) {
            throw ValidationException::withMessages(['clarification' => ['A conversation is required to resolve this recipe field.']]);
        }
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $draft = is_array($metadata['active_recipe_draft'] ?? null) ? $metadata['active_recipe_draft'] : [];
        $continuationId = (string) ($metadata['active_recipe_draft_continuation_id'] ?? 'legacy-recipe-draft');
        $fieldPath = (string) ($range['field_path'] ?? '');
        $ingredientIndex = $fieldPath === 'yield.quantity'
            ? null
            : collect($draft['ingredients'] ?? [])->search(fn (mixed $ingredient): bool => is_array($ingredient) && ($ingredient['ingredient_name'] ?? $ingredient['name'] ?? null) === ($range['ingredient'] ?? null) && isset($ingredient['quantity_min'], $ingredient['quantity_max']));
        if ($fieldPath === 'yield.quantity' && !isset($draft['yield']['quantity_min'], $draft['yield']['quantity_max'])) {
            throw ValidationException::withMessages(['clarification' => ['The recipe yield is no longer available.']]);
        }
        if ($fieldPath !== 'yield.quantity' && $ingredientIndex === false) {
            throw ValidationException::withMessages(['clarification' => ['The recipe field is no longer available.']]);
        }
        $id = (string) Str::ulid();
        $metadata['pending_clarifications'] = [...(is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : []), [
            'allow_custom' => true,
            'action_key' => 'recipes.create',
            'actor_id' => $context['user']->id,
            'clarification_id' => $id,
            'constraints' => ['min' => $range['min'], 'max' => $range['max']],
            'conversation_id' => $conversation->id,
            'continuation_id' => $continuationId,
            'draft_id' => $metadata['active_recipe_draft_state']['draft_id'] ?? $continuationId,
            'draft_revision' => $metadata['active_recipe_draft_state']['revision'] ?? 1,
            'draft_reference' => 'active_recipe_draft',
            'expected_type' => 'number',
            'field_path' => $fieldPath !== '' ? $fieldPath : "ingredients.{$ingredientIndex}.quantity",
            'ingredient_index' => $ingredientIndex,
            'options' => [['id' => 'min', 'value' => $range['min']], ['id' => 'max', 'value' => $range['max']]],
            'original_payload' => ['action_id' => 'recipes.create', 'input' => ['recipe_draft' => $draft]],
            'quantity_max' => $range['max'],
            'quantity_min' => $range['min'],
            'selection_mode' => 'single',
            'status' => 'pending',
            'type' => 'recipe_draft.field_resolution',
            'unit' => $range['unit'] ?? null,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'workflow' => 'recipes.create',
            'workspace_id' => $context['workspace']->id,
        ]];
        $conversation->forceFill(['metadata' => $metadata])->save();
        Log::info('ai.clarification.created', ['workflow' => 'recipes.create', 'action_key' => 'recipes.create', 'clarification_type' => 'recipe_draft.field_resolution', 'expected_type' => 'number', 'selection_mode' => 'single', 'conversation_id' => $conversation->id, 'draft_id' => $metadata['active_recipe_draft_state']['draft_id'] ?? $continuationId, 'workspace_id' => $context['workspace']->id, 'router_bypassed' => true, 'ai_bypassed' => true]);
        Log::info('ai.workflow.clarification_required', ['workflow' => 'recipes.create', 'action_key' => 'recipes.create', 'clarification_type' => 'recipe_draft.field_resolution', 'conversation_id' => $conversation->id, 'workspace_id' => $context['workspace']->id]);
        return $id;
    }

    private function createRecipeFieldClarification(array $context, array $issue): string
    {
        $conversation = $context['conversation'] ?? null;
        if (!$conversation) {
            throw ValidationException::withMessages(['clarification' => ['A conversation is required to resolve this recipe field.']]);
        }
        $fieldPath = (string) ($issue['field_path'] ?? '');
        if ($fieldPath === '') {
            throw ValidationException::withMessages(['clarification' => ['The recipe field path is missing.']]);
        }
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = is_array($metadata['active_recipe_draft_state'] ?? null) ? $metadata['active_recipe_draft_state'] : [];
        $continuationId = (string) ($state['draft_id'] ?? $metadata['active_recipe_draft_continuation_id'] ?? Str::ulid());
        $isUnitField = ($issue['code'] ?? null) === 'ingredient_unit_missing';
        $expectedType = $isUnitField ? 'string' : 'number';
        $clarificationOptions = $isUnitField ? $this->recipeUnitClarificationOptions() : [];
        $id = (string) Str::ulid();
        $metadata['pending_clarifications'] = collect($metadata['pending_clarifications'] ?? [])
            ->reject(fn (mixed $item): bool => is_array($item)
                && ($item['workflow'] ?? null) === 'recipes.create'
                && ($item['field_path'] ?? null) === $fieldPath
                && ($item['status'] ?? null) === 'pending')
            ->push([
                'allow_custom' => !$isUnitField,
                'action_key' => 'recipes.create',
                'actor_id' => $context['user']->id,
                'clarification_id' => $id,
                'conversation_id' => $conversation->id,
                'continuation_id' => $continuationId,
                'draft_id' => $state['draft_id'] ?? $continuationId,
                'draft_reference' => 'active_recipe_draft',
                'expected_type' => $expectedType,
                'field_path' => $fieldPath,
                'ingredient_index' => $issue['index'] ?? null,
                'options' => $clarificationOptions,
                'input_control' => $isUnitField ? 'select' : 'custom',
                'selection_mode' => 'single',
                'status' => 'pending',
                'type' => 'recipe_draft.field_resolution',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'workflow' => 'recipes.create',
                'workspace_id' => $context['workspace']->id,
            ])->values()->all();
        $conversation->forceFill(['metadata' => $metadata])->save();
        Log::info('ai.clarification.created', [
            'workflow' => 'recipes.create',
            'action_key' => 'recipes.create',
            'clarification_type' => 'recipe_draft.field_resolution',
            'draft_id' => $state['draft_id'] ?? $continuationId,
            'expected_type' => $expectedType,
            'field_path' => $fieldPath,
            'conversation_id' => $conversation->id,
            'workspace_id' => $context['workspace']->id,
            'router_bypassed' => true,
            'ai_bypassed' => true,
        ]);

        return $id;
    }

    /** @return array<int, array{id: string, label: string, value: string}> */
    private function recipeUnitClarificationOptions(): array
    {
        return collect((new UnitRegistry())->keys())
            ->map(fn (string $key): array => [
                'id' => $key,
                'label' => $key === 'fl_oz' ? 'fl oz' : $key,
                'value' => $key,
            ])
            ->values()
            ->all();
    }

    private function createRecipeNameClarification(array $context): string
    {
        $conversation = $context['conversation'] ?? null;
        if (!$conversation) {
            throw ValidationException::withMessages(['clarification' => ['A conversation is required to resolve this recipe field.']]);
        }
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = is_array($metadata['active_recipe_draft_state'] ?? null) ? $metadata['active_recipe_draft_state'] : [];
        $continuationId = (string) ($state['draft_id'] ?? $metadata['active_recipe_draft_continuation_id'] ?? Str::ulid());
        $id = (string) Str::ulid();
        $metadata['pending_clarifications'] = collect($metadata['pending_clarifications'] ?? [])
            ->reject(fn (mixed $item): bool => is_array($item)
                && ($item['workflow'] ?? null) === 'recipes.create'
                && ($item['field_path'] ?? null) === 'name'
                && ($item['status'] ?? null) === 'pending')
            ->push([
                'allow_custom' => true,
                'action_key' => 'recipes.create',
                'actor_id' => $context['user']->id,
                'clarification_id' => $id,
                'conversation_id' => $conversation->id,
                'continuation_id' => $continuationId,
                'draft_id' => $state['draft_id'] ?? $continuationId,
                'draft_reference' => 'active_recipe_draft',
                'expected_type' => 'string',
                'field_path' => 'name',
                'options' => [],
                'selection_mode' => 'single',
                'status' => 'pending',
                'type' => 'recipe_draft.field_resolution',
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
                'workflow' => 'recipes.create',
                'workspace_id' => $context['workspace']->id,
            ])->values()->all();
        $conversation->forceFill(['metadata' => $metadata])->save();
        Log::info('ai.clarification.created', [
            'workflow' => 'recipes.create',
            'action_key' => 'recipes.create',
            'clarification_type' => 'recipe_draft.field_resolution',
            'draft_id' => $state['draft_id'] ?? $continuationId,
            'expected_type' => 'string',
            'field_path' => 'name',
            'conversation_id' => $conversation->id,
            'workspace_id' => $context['workspace']->id,
            'router_bypassed' => true,
            'ai_bypassed' => true,
        ]);

        return $id;
    }

    private function completedActionResult(array $tool, array $context, array $resource, string $label): array
    {
        return [
            'blocks' => [
                ['text' => $tool['key'] === 'recipes.create'
                    ? trans('chat.recipe.created', ['name' => $label], $context['locale'])
                    : trans('chat.action.completed', [], $context['locale']), 'type' => 'text'],
                ['component' => $tool['result_component'] ?? 'action.result', 'data' => [
                    'action_key' => $tool['key'],
                    'description' => trans('chat.action.completed_description', [], $context['locale']),
                    'details' => [['label' => trans('chat.action.record_label', [], $context['locale']), 'value' => $label]],
                    'entity_id' => $resource['id'] ?? null,
                    'entity_type' => $tool['entity_type'],
                    'status' => 'success', 'title' => trans('chat.action.completed_title', [], $context['locale']),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [], 'result_ref_json' => $resource, 'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function previewDirectoryWrite(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $type = (string) $tool['entity_type'];
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $entity = null;

        if ($tool['operation_type'] !== 'create') {
            $entityPayload = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
            $resolution = $this->directoryEntityResolver->resolve(
                $context['workspace']->id,
                $type,
                $entityPayload['id'] ?? $input['entity_id'] ?? null,
                $input['entity_search'] ?? null,
                $context['entity_refs'] ?? []
            );

            if (($resolution['status'] ?? null) !== 'resolved') {
                if (in_array($resolution['status'] ?? null, ['ambiguous', 'suggested_match'], true)) {
                    return $this->entityDisambiguationResult(
                        $tool,
                        $context,
                        $payload,
                        'entity_id',
                        (string) ($input['entity_search'] ?? ''),
                        $resolution['candidates'] ?? [],
                        ($resolution['status'] ?? null) === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate'
                    );
                }

                return $this->directoryResolutionResult($tool, $context, $resolution);
            }

            $entity = $this->loadDirectoryEntity($resolution['entity'], $type);
            $payload['entity'] = [
                'id' => $entity->id,
                'type' => $type,
                'version' => $entity->version ?? 1,
            ];
        }

        $normalized = $this->normalizeDirectoryInput($type, $tool['operation_type'], $input, $context);
        if (!empty($normalized['_missing_fields'])) {
            return $this->directoryMissingFieldsResult($tool, $context, $normalized['_missing_fields']);
        }
        unset($normalized['_missing_fields']);
        $this->authorizeDirectoryTool($tool, $context, $entity);

        $label = $entity
            ? $this->directoryEntityResolver->label($entity, $type)
            : (string) ($normalized['name'] ?? $normalized['title'] ?? $type);
        $locale = (string) ($context['locale'] ?? 'en');
        $changes = collect($normalized)
            ->reject(fn (mixed $value, string $key): bool => in_array($key, ['entity_id', 'entity_search'], true) || $value === null || $value === '')
            ->map(fn (mixed $value, string $key): array => [
                'after' => is_scalar($value) ? (string) $value : json_encode($value),
                'before' => $entity && isset($entity->{$key}) ? (string) $entity->{$key} : null,
                'label' => $this->directoryFieldLabel($key, $locale),
            ])->values()->all();

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $label,
                'changes' => $changes,
                'description' => trans('chat.directory.write_preview_description', [], $locale),
                'metadata' => [['label' => trans('chat.directory.entity_label', [], $locale), 'value' => $label]],
                'title' => trans('chat.directory.write_preview_title', [], $locale),
                'type' => trans('chat.directory.write_preview_type', [], $locale),
            ],
            [
                ['label' => trans('chat.directory.entity_label', [], $locale), 'value' => $label],
                ['label' => trans('chat.directory.action_label', [], $locale), 'value' => trans('chat.directory.operations.'.$tool['operation_type'], [], $locale)],
            ],
            [
                'entity' => $payload['entity'] ?? null,
                'input' => $normalized,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function previewTaskUpdate(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $workspaceId = $context['workspace']->id;
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $input = $this->validateTaskInput(
            is_array($payload['input'] ?? null) ? $payload['input'] : [],
            $workspaceId
        );
        $resolution = $this->listTasksForTool->find(
            $workspaceId,
            $entity['id'] ?? $input['task_id'] ?? null,
            $input['task_search'] ?? null,
            $context['entity_refs'] ?? []
        );
        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['entity' => $entity, 'input' => $input, 'action_id' => $tool['key']],
                'task',
                'task_id',
                (string) ($input['task_search'] ?? ''),
                'task'
            );

            return $clarification ?? $this->taskResolutionResult($tool, $context, $resolution);
        }
        $task = $resolution['entity'];
        $entity = [
            'id' => $task->id,
            'type' => 'task',
            'version' => (int) ($entity['version'] ?? $task->version ?? 1),
        ];
        unset($input['task_id'], $input['task_search']);
        if ($tool['key'] === 'tasks.complete') {
            $input['status'] = 'done';
        }
        if ($tool['key'] === 'tasks.status.update' && empty($input['status'])) {
            return $this->taskStatusClarification($tool, $context, [
                'entity' => $entity,
                'input' => $input,
                'action_id' => $tool['key'],
            ]);
        }
        $input = $this->resolveTaskRelationships($context, $input);
        $task = $this->loadTaskForTool($workspaceId, $entity['id']);

        if (array_key_exists('time_hour', $input) && empty($input['time_period'])) {
            return $this->taskTimePeriodClarification($tool, $context, [
                'entity' => $entity,
                'input' => $input,
                'action_id' => $tool['key'],
            ]);
        }
        $input = $this->applyTaskTime($task, $input);

        Gate::forUser($context['user'])->authorize('update', $task);

        $changes = $this->buildTaskChanges($task, $input, $workspaceId);
        $this->assertHasChanges($changes);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $task->title,
                'changes' => $changes,
                'description' => 'Revisa la actualización propuesta para la tarea antes de ejecutarla.',
                'metadata' => [
                    [
                        'label' => 'Tarea',
                        'value' => $task->title,
                    ],
                ],
                'title' => 'Actualización propuesta de tarea',
                'type' => 'Task update',
            ],
            [
                [
                    'label' => 'Tarea',
                    'value' => $task->title,
                ],
                [
                    'label' => 'Acción',
                    'value' => 'Actualizar tarea',
                ],
            ],
            [
                'entity' => $entity,
                'input' => $input,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function previewTaskCreate(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $input = $this->validateTaskCreateInput(
            is_array($payload['input'] ?? null) ? $payload['input'] : []
        );
        $input = $this->resolveTaskRelationships($context, $input);
        $locale = (string) ($context['locale'] ?? 'en');

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $input['title'],
                'changes' => collect([
                    ['after' => $input['title'], 'label' => trans('chat.tasks.title_label', [], $locale)],
                    ['after' => $input['priority'], 'label' => trans('chat.tasks.priority_label', [], $locale)],
                    ['after' => $input['starts_at'] ?? null, 'label' => trans('chat.tasks.starts_at_label', [], $locale)],
                    ['after' => $input['due_at'] ?? null, 'label' => trans('chat.tasks.due_at_label', [], $locale)],
                ])->filter(fn (array $change): bool => $change['after'] !== null)->values()->all(),
                'description' => trans('chat.tasks.create_preview_description', [], $locale),
                'metadata' => [
                    [
                        'label' => trans('chat.tasks.title_label', [], $locale),
                        'value' => $input['title'],
                    ],
                ],
                'title' => trans('chat.tasks.create_preview_title', [], $locale),
                'type' => trans('chat.tasks.create_type', [], $locale),
            ],
            [
                [
                    'label' => trans('chat.tasks.title_label', [], $locale),
                    'value' => $input['title'],
                ],
                [
                    'label' => trans('chat.tasks.action_label', [], $locale),
                    'value' => trans('chat.tasks.create_action', [], $locale),
                ],
            ],
            [
                'input' => $input,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function taskStatusClarification(array $tool, array $context, array $payload): array
    {
        $conversation = $context['conversation'] ?? null;
        $locale = (string) ($context['locale'] ?? 'en');
        $options = collect([
            'todo' => trans('chat.tasks.statuses.todo', [], $locale),
            'in_progress' => trans('chat.tasks.statuses.in_progress', [], $locale),
            'blocked' => trans('chat.tasks.statuses.blocked', [], $locale),
            'done' => trans('chat.tasks.statuses.done', [], $locale),
            'cancelled' => trans('chat.tasks.statuses.cancelled', [], $locale),
        ])->map(fn (string $label, string $value): array => [
            'id' => $value,
            'label' => $label,
            'value' => $value,
        ])->values()->all();

        if (!$conversation) {
            return [
                'status' => 'clarification_required',
                'blocks' => [['text' => trans('chat.tasks.status_required', [], $locale), 'type' => 'text']],
                'entity_refs' => [],
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        }

        $clarificationId = (string) Str::ulid();
        $expiresAt = now()->addMinutes(30);
        $originalInput = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        if (filled($payload['entity']['id'] ?? null)) {
            $originalInput['task_id'] = $payload['entity']['id'];
        }
        $originalPayload = [...$payload, 'action_id' => $tool['key'], 'input' => $originalInput];
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['pending_clarifications'] = [
            ...(is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : []),
            [
                'action_key' => $tool['key'],
                'actor_id' => $context['user']->id,
                'allow_custom' => false,
                'clarification_id' => $clarificationId,
                'conversation_id' => $conversation->id,
                'draft_reference' => '',
                'entity_type' => 'task',
                'expected_type' => 'string',
                'expires_at' => $expiresAt->toIso8601String(),
                'field_path' => 'input.status',
                'input_control' => 'select',
                'options' => $options,
                'original_payload' => $originalPayload,
                'selection_mode' => 'single',
                'status' => 'pending',
                'type' => 'action.field_resolution',
                'workflow' => $tool['key'],
                'workspace_id' => $context['workspace']->id,
            ],
        ];
        $conversation->forceFill(['metadata' => $metadata])->save();

        return [
            'status' => 'clarification_required',
            'blocks' => [[
                'actions' => [
                    ['id' => 'clarification.resolve'],
                    ['id' => 'clarification.cancel'],
                ],
                'component' => 'clarification.options',
                'data' => [
                    'allow_custom' => false,
                    'clarification_id' => $clarificationId,
                    'description' => trans('chat.tasks.status_required', [], $locale),
                    'expected_type' => 'string',
                    'input_control' => 'select',
                    'options' => $options,
                    'selection_mode' => 'single',
                    'title' => trans('chat.tasks.status_title', [], $locale),
                ],
                'schema_version' => 2,
                'type' => 'component',
            ]],
            'entity_refs' => [],
            'suggestions' => [],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function taskTimePeriodClarification(array $tool, array $context, array $payload): array
    {
        $conversation = $context['conversation'] ?? null;
        $locale = (string) ($context['locale'] ?? 'en');
        $options = [
            ['id' => 'am', 'label' => 'AM', 'value' => 'am'],
            ['id' => 'pm', 'label' => 'PM', 'value' => 'pm'],
        ];
        if (!$conversation) {
            return [
                'status' => 'clarification_required',
                'blocks' => [['text' => trans('chat.tasks.time_period_required', ['hour' => (int) data_get($payload, 'input.time_hour', 0)], $locale), 'type' => 'text']],
                'entity_refs' => [],
                'tool' => $this->toolRegistry->metadata($tool),
            ];
        }

        $clarificationId = (string) Str::ulid();
        $expiresAt = now()->addMinutes(30);
        $originalInput = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        if (filled($payload['entity']['id'] ?? null)) {
            $originalInput['task_id'] = $payload['entity']['id'];
        }
        $originalPayload = [...$payload, 'action_id' => $tool['key'], 'input' => $originalInput];
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metadata['pending_clarifications'] = [
            ...(is_array($metadata['pending_clarifications'] ?? null) ? $metadata['pending_clarifications'] : []),
            [
                'action_key' => $tool['key'], 'actor_id' => $context['user']->id, 'allow_custom' => false,
                'clarification_id' => $clarificationId, 'conversation_id' => $conversation->id,
                'draft_reference' => '', 'entity_type' => 'task', 'expected_type' => 'string',
                'expires_at' => $expiresAt->toIso8601String(), 'field_path' => 'input.time_period',
                'input_control' => 'select', 'options' => $options, 'original_payload' => $originalPayload,
                'selection_mode' => 'single', 'status' => 'pending', 'type' => 'action.field_resolution',
                'workflow' => $tool['key'], 'workspace_id' => $context['workspace']->id,
            ],
        ];
        $conversation->forceFill(['metadata' => $metadata])->save();

        return [
            'status' => 'clarification_required',
            'blocks' => [[
                'actions' => [['id' => 'clarification.resolve'], ['id' => 'clarification.cancel']],
                'component' => 'clarification.options',
                'data' => [
                    'allow_custom' => false, 'clarification_id' => $clarificationId,
                    'description' => trans('chat.tasks.time_period_required', ['hour' => (int) data_get($payload, 'input.time_hour', 0)], $locale),
                    'expected_type' => 'string', 'input_control' => 'select', 'options' => $options,
                    'selection_mode' => 'single', 'title' => trans('chat.tasks.time_period_title', [], $locale),
                ],
                'schema_version' => 2, 'type' => 'component',
            ]],
            'entity_refs' => [], 'suggestions' => [], 'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function applyTaskTime(Task $task, array $input): array
    {
        if (!array_key_exists('time_hour', $input)) {
            return $input;
        }

        $hour = (int) $input['time_hour'];
        $minute = (int) ($input['time_minute'] ?? 0);
        $period = Str::lower((string) ($input['time_period'] ?? ''));
        if ($period === 'pm' && $hour < 12) {
            $hour += 12;
        } elseif ($period === 'am' && $hour === 12) {
            $hour = 0;
        }

        $base = $task->starts_at ?? $task->due_at ?? Carbon::now();
        $input['starts_at'] = $base->copy()->setTime($hour, $minute)->toIso8601String();
        unset($input['time_hour'], $input['time_minute'], $input['time_period']);

        return $input;
    }

    private function previewTaskAssignment(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $workspaceId = $context['workspace']->id;
        $input = $this->validateTaskAssignmentInput(
            is_array($payload['input'] ?? null) ? $payload['input'] : [],
            $workspaceId
        );
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        if ($this->isBulkTaskAssignment($entity, $input)) {
            return $this->previewBulkTaskAssignment($tool, $context, $input, $payload, $source);
        }
        $taskResolution = $this->listTasksForTool->find(
            $workspaceId,
            $entity['id'] ?? $input['task_id'] ?? null,
            $input['task_search'] ?? null,
            $context['entity_refs'] ?? []
        );
        if (($taskResolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $taskResolution,
                ['entity' => $entity, 'input' => $input, 'action_id' => $tool['key']],
                'task',
                'task_id',
                (string) ($input['task_search'] ?? ''),
                'task'
            );

            return $clarification ?? $this->taskResolutionResult($tool, $context, $taskResolution);
        }

        $task = $taskResolution['entity'];
        $entity = [
            'id' => $task->id,
            'type' => 'task',
            'version' => (int) ($entity['version'] ?? $task->version ?? 1),
        ];
        if ($this->isCurrentMemberReference($input['member_search'] ?? null, $context)) {
            $input['membership_id'] = $context['membership']->id;
            unset($input['member_search']);
        }
        $memberResolution = $this->listWorkspaceMembersForTool->find(
            $workspaceId,
            $input['membership_id'] ?? null,
            $input['member_search'] ?? null,
            $context['entity_refs'] ?? []
        );
        if (($memberResolution['status'] ?? null) !== 'resolved') {
            $candidates = $memberResolution['candidates'] ?? [];
            if (($memberResolution['status'] ?? null) === 'not_found' && empty($input['member_search'])) {
                $candidates = collect($this->listWorkspaceMembersForTool->execute($workspaceId, ['limit' => 100])['items'] ?? [])
                    ->map(fn (array $member): array => [
                        'id' => (string) ($member['id'] ?? ''),
                        'name' => (string) data_get($member, 'user.name', data_get($member, 'user.email', '')),
                    ])
                    ->filter(fn (array $candidate): bool => $candidate['id'] !== '' && $candidate['name'] !== '')
                    ->values()
                    ->all();
            }
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                ['status' => $memberResolution['status'], 'candidates' => $candidates],
                ['entity' => $entity, 'input' => $input, 'action_id' => $tool['key']],
                'member',
                'membership_id',
                (string) ($input['member_search'] ?? ''),
                'membership'
            );

            return $clarification ?? $this->genericResolutionResult($tool, $context, $memberResolution, 'member');
        }

        $input['membership_id'] = $memberResolution['entity']->id;
        unset($input['task_id'], $input['task_search'], $input['member_search']);
        $this->authorizeTaskUpdate($context, $task);
        $changes = $this->buildTaskChanges($task, $input, $workspaceId);
        $this->assertHasChanges($changes);
        $locale = (string) ($context['locale'] ?? 'en');

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $task->title,
                'changes' => $changes,
                'description' => trans('chat.tasks.assign_preview_description', [], $locale),
                'metadata' => [['label' => trans('chat.tasks.title_label', [], $locale), 'value' => $task->title]],
                'title' => trans('chat.tasks.assign_preview_title', [], $locale),
                'type' => trans('chat.tasks.assign_type', [], $locale),
            ],
            [['label' => trans('chat.tasks.title_label', [], $locale), 'value' => $task->title]],
            ['entity' => $entity, 'input' => $input, 'tool_key' => $tool['key']]
        );
    }

    private function authorizeTaskUpdate(array $context, Task $task): void
    {
        Gate::forUser($context['user'])->authorize('update', $task);
    }

    private function isBulkTaskAssignment(array $entity, array $input): bool
    {
        return empty($entity['id'])
            && empty($input['task_id'])
            && empty($input['task_search'])
            && (filled($input['task_ids'] ?? null)
                || filled($input['due_from'] ?? null)
                || filled($input['due_to'] ?? null)
                || filled($input['from_member_search'] ?? null)
                || filled($input['from_membership_id'] ?? null));
    }

    private function previewBulkTaskAssignment(
        array $tool,
        array $context,
        array $input,
        array $payload,
        array $source
    ): array {
        $workspaceId = $context['workspace']->id;
        if ($this->isCurrentMemberReference($input['member_search'] ?? null, $context)) {
            $input['membership_id'] = $context['membership']->id;
            unset($input['member_search']);
        }
        $memberResolution = $this->listWorkspaceMembersForTool->find(
            $workspaceId,
            $input['membership_id'] ?? null,
            $input['member_search'] ?? null,
            $context['entity_refs'] ?? []
        );
        if (($memberResolution['status'] ?? null) !== 'resolved') {
            $candidates = $memberResolution['candidates'] ?? [];
            if (($memberResolution['status'] ?? null) === 'not_found' && empty($input['member_search'])) {
                $candidates = collect($this->listWorkspaceMembersForTool->execute($workspaceId, ['limit' => 100])['items'] ?? [])
                    ->map(fn (array $member): array => ['id' => (string) ($member['id'] ?? ''), 'name' => (string) data_get($member, 'user.name', data_get($member, 'user.email', ''))])
                    ->filter(fn (array $candidate): bool => $candidate['id'] !== '' && $candidate['name'] !== '')
                    ->values()->all();
            }
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                ['status' => $memberResolution['status'], 'candidates' => $candidates],
                ['entity' => [], 'input' => $input, 'action_id' => $tool['key']],
                'member',
                'membership_id',
                (string) ($input['member_search'] ?? ''),
                'membership'
            );

            return $clarification ?? $this->genericResolutionResult($tool, $context, $memberResolution, 'member');
        }

        $sourceMember = null;
        if (filled($input['from_membership_id'] ?? null) || filled($input['from_member_search'] ?? null)) {
            $sourceResolution = $this->listWorkspaceMembersForTool->find(
                $workspaceId,
                $input['from_membership_id'] ?? null,
                $input['from_member_search'] ?? null,
                $context['entity_refs'] ?? []
            );
            if (($sourceResolution['status'] ?? null) !== 'resolved') {
                $clarification = $this->entityResolutionClarificationResult(
                    $tool,
                    $context,
                    $sourceResolution,
                    ['entity' => [], 'input' => $input, 'action_id' => $tool['key']],
                    'member',
                    'from_membership_id',
                    (string) ($input['from_member_search'] ?? ''),
                    'membership'
                );

                return $clarification ?? $this->genericResolutionResult($tool, $context, $sourceResolution, 'member');
            }
            $sourceMember = $sourceResolution['entity'];
        }

        $filters = [
            'due_from' => $input['due_from'] ?? null,
            'due_to' => $input['due_to'] ?? null,
            'status' => $input['status'] ?? null,
            'search' => $input['search'] ?? null,
            'membership_id' => $sourceMember?->id,
        ];
        if (is_array($input['task_ids'] ?? null) && $input['task_ids'] !== []) {
            $filters = ['search' => null];
        }
        $tasks = is_array($input['task_ids'] ?? null) && $input['task_ids'] !== []
            ? Task::query()->where('workspace_id', $workspaceId)->whereIn('id', $input['task_ids'])->with([
                'assignments.membership.user', 'team', 'station', 'event',
            ])->get()
            : $this->listTasksForTool->findMany($workspaceId, $filters);

        if ($tasks->isEmpty()) {
            return $this->taskResolutionResult($tool, $context, ['status' => 'not_found', 'candidates' => []]);
        }

        $targetMembershipId = $memberResolution['entity']->id;
        $changes = $tasks->flatMap(fn (Task $task): array => $this->buildTaskChanges($task, ['membership_id' => $targetMembershipId], $workspaceId))->values()->all();
        $this->assertHasChanges($changes);
        $entity = [
            'type' => 'task',
            'ids' => $tasks->pluck('id')->values()->all(),
            'versions' => $tasks->mapWithKeys(fn (Task $task): array => [$task->id => (int) $task->version])->all(),
        ];
        $input['membership_id'] = $targetMembershipId;
        unset($input['member_search'], $input['from_member_search']);
        $locale = (string) ($context['locale'] ?? 'en');

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => trans('chat.tasks.bulk_assign_action', ['count' => $tasks->count()], $locale),
                'changes' => $changes,
                'description' => trans('chat.tasks.assign_preview_description', [], $locale),
                'metadata' => [['label' => trans('chat.tasks.tasks_label', [], $locale), 'value' => (string) $tasks->count()]],
                'title' => trans('chat.tasks.assign_preview_title', [], $locale),
                'type' => trans('chat.tasks.assign_type', [], $locale),
            ],
            [['label' => trans('chat.tasks.tasks_label', [], $locale), 'value' => (string) $tasks->count()]],
            ['entity' => $entity, 'input' => $input, 'tool_key' => $tool['key']]
        );
    }

    private function previewPrepItemUpdate(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $workspaceId = $context['workspace']->id;
        $resolved = $this->resolvePrepItemPayload($tool, $context, $payload);
        if (isset($resolved['resolution'])) {
            $resolution = $resolved['resolution'];
            $entityType = (string) ($resolution['entity_type'] ?? 'prep_item');
            $field = $entityType === 'membership' ? 'assignment_membership_id' : 'prep_item_id';
            $reference = $entityType === 'membership'
                ? (string) (($payload['input']['assignee_search'] ?? ''))
                : (string) (($payload['input']['prep_item_search'] ?? ''));
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => is_array($payload['input'] ?? null) ? $payload['input'] : []],
                $entityType === 'membership' ? 'member' : 'item',
                $field,
                $reference,
                $entityType
            );
            return $clarification ?? $this->prepResolutionResult($tool, $context, $resolution, $entityType === 'membership' ? 'member' : 'item');
        }
        $entity = $resolved['entity'];
        $input = $this->validatePrepItemInput($resolved['input'], $workspaceId);
        $prepItem = $this->loadPrepItemForTool($workspaceId, $entity['id']);

        Gate::forUser($context['user'])->authorize('update', $prepItem);

        $changes = $this->buildPrepItemChanges($prepItem, $input, $workspaceId);
        $this->assertHasChanges($changes);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $prepItem->title,
                'changes' => $changes,
                'description' => 'Confirma los cambios propuestos para este ítem de producción.',
                'metadata' => [
                    [
                        'label' => 'Prep item',
                        'value' => $prepItem->title,
                    ],
                ],
                'title' => 'Actualización propuesta de prep item',
                'type' => trans('chat.prep.item_update_type', [], $context['locale']),
            ],
            [
                [
                    'label' => 'Prep item',
                    'value' => $prepItem->title,
                ],
                [
                    'label' => 'Acción',
                    'value' => 'Actualizar ítem',
                ],
            ],
            [
                'entity' => $entity,
                'input' => $input,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function previewPrepGeneration(array $tool, array $context, array $payload, array $source): array
    {
        $input = $this->validatePrepGenerationInput(
            is_array($payload['input'] ?? null) ? $payload['input'] : [],
            $context['workspace']->id
        );
        $target = $this->resolvePrepGenerationTarget($context, $input);
        if (($target['status'] ?? null) !== 'resolved') {
            $entityType = (string) ($target['entity_type'] ?? 'prep_list');
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $target,
                ['input' => $input],
                $entityType === 'event' ? 'event' : 'list',
                $entityType === 'event' ? 'event_id' : 'prep_list_id',
                $entityType === 'event'
                    ? (string) ($input['event_search'] ?? '')
                    : (string) ($input['prep_list_search'] ?? ''),
                $entityType
            );
            return $clarification ?? $this->prepResolutionResult($tool, $context, $target, $entityType === 'event' ? 'event' : 'list');
        }

        $event = $target['event'];
        $prepList = $target['prep_list'];
        if ($prepList instanceof PrepList) {
            Gate::forUser($context['user'])->authorize('update', $prepList);
        } else {
            Gate::forUser($context['user'])->authorize('create', PrepList::class);
            $prepList = (new PrepList)->forceFill([
                'workspace_id' => $context['workspace']->id,
                'event_id' => $event->id,
                'name' => $input['name'] ?? ($event->name.' Prep'),
                'current_version' => 0,
                'status' => 'draft',
            ]);
        }

        $generation = $this->generatePrepList->execute(
            $prepList,
            $context['workspace']->id,
            $context['user']->id,
            $this->generationAttributes($input, $tool),
            false
        );
        $locale = (string) ($context['locale'] ?? 'en');
        $previewData = [
            'action' => $tool['key'] === 'prep.regenerate'
                ? trans('chat.prep.regeneration_action', [], $locale)
                : trans('chat.prep.generation_action', [], $locale),
            'changes' => [
                ['label' => trans('chat.prep.event_label', [], $locale), 'after' => $event->name],
                ['label' => trans('chat.prep.items_label', [], $locale), 'after' => (string) $generation['estimated_items']],
                ['label' => trans('chat.prep.version_label', [], $locale), 'after' => (string) ($generation['version_preview']['version'] ?? 1)],
            ],
            'description' => trans('chat.prep.generation_preview_description', [], $locale),
            'impact' => trans('chat.prep.generation_preview_impact', [], $locale),
            'metadata' => [
                ['label' => trans('chat.prep.event_label', [], $locale), 'value' => $event->name],
                ['label' => trans('chat.prep.guest_count_label', [], $locale), 'value' => (string) ($generation['generation_metadata']['guest_count'] ?? trans('chat.prep.not_available', [], $locale))],
            ],
            'title' => $tool['key'] === 'prep.regenerate'
                ? trans('chat.prep.regeneration_preview_title', [], $locale)
                : trans('chat.prep.generation_preview_title', [], $locale),
            'type' => trans('chat.prep.generation_type', [], $locale),
            'generation' => [
                'event' => ['id' => $event->id, 'name' => $event->name],
                'guest_count' => $generation['generation_metadata']['guest_count'] ?? null,
                'items' => $generation['items'],
                'menu_label' => $generation['menu_label'],
                'summary' => $generation['summary'],
                'version_preview' => $generation['version_preview'],
                'warnings' => $generation['warnings'],
            ],
        ];

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            $previewData,
            [
                ['label' => trans('chat.prep.event_label', [], $locale), 'value' => $event->name],
                ['label' => trans('chat.prep.items_label', [], $locale), 'value' => (string) $generation['estimated_items']],
            ],
            [
                'entity' => $target['prep_list'] instanceof PrepList
                    ? ['id' => $prepList->id, 'type' => 'prep_list', 'version' => max(1, (int) $prepList->current_version)]
                    : null,
                'input' => $input,
                'tool_key' => $tool['key'],
                'generation_context' => [
                    'event_id' => $event->id,
                    'current_version' => (int) $prepList->current_version,
                ],
            ]
        );
    }

    private function previewPrepListUpdate(array $tool, array $context, array $payload, array $source): array
    {
        $input = $this->validatePrepListInput(is_array($payload['input'] ?? null) ? $payload['input'] : []);
        $resolution = $this->prepEntityResolver->resolveList(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $input['prep_list_id'] ?? null,
            $input['prep_list_search'] ?? null
        );
        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                'list',
                'prep_list_id',
                (string) ($input['prep_list_search'] ?? ''),
                'prep_list'
            );
            return $clarification ?? $this->prepResolutionResult($tool, $context, $resolution, 'list');
        }
        $prepList = $resolution['prep_list'];
        Gate::forUser($context['user'])->authorize('update', $prepList);
        $changes = collect(['name', 'event_id', 'production_starts_at', 'production_ends_at', 'timezone', 'status'])
            ->filter(fn (string $field): bool => array_key_exists($field, $input))
            ->map(fn (string $field): array => [
                'label' => trans('chat.prep.'.$field.'_label', [], $context['locale']),
                'before' => (string) ($prepList->{$field} ?? ''),
                'after' => (string) ($input[$field] ?? ''),
            ])->values()->all();
        $this->assertHasChanges($changes);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $prepList->name,
                'changes' => $changes,
                'description' => trans('chat.prep.list_update_preview_description', [], $context['locale']),
                'title' => trans('chat.prep.list_update_preview_title', [], $context['locale']),
                'type' => trans('chat.prep.list_update_type', [], $context['locale']),
            ],
            [['label' => trans('chat.prep.list_label', [], $context['locale']), 'value' => $prepList->name]],
            [
                'entity' => ['id' => $prepList->id, 'type' => 'prep_list', 'version' => max(1, (int) $prepList->current_version)],
                'input' => $input,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function executeTaskUpdate(
        array $tool,
        array $context,
        array $draft
    ): array {
        $workspaceId = $context['workspace']->id;
        $entity = $this->validateEntityPayload(
            is_array($draft['entity'] ?? null) ? $draft['entity'] : [],
            'task'
        );
        $input = $this->validateTaskInput(
            is_array($draft['input'] ?? null) ? $draft['input'] : [],
            $workspaceId
        );
        $task = $this->loadTaskForTool($workspaceId, $entity['id']);

        Gate::forUser($context['user'])->authorize('update', $task);

        $updated = $this->updateTask->execute(
            $task,
            (int) $entity['version'],
            $this->mapTaskUpdateAttributes($input),
            $context['user']->id
        );

        if (!$updated) {
            throw ValidationException::withMessages([
                'version' => ['The task changed before this confirmation was executed.'],
            ]);
        }

        $updated = $this->loadTaskForTool($workspaceId, $updated->id);

        return [
            'blocks' => [
                [
                    'text' => 'La tarea se actualizó correctamente.',
                    'type' => 'text',
                ],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'description' => 'La actualización confirmada ya se aplicó en el workspace.',
                        'details' => [
                            [
                                'label' => 'Tarea',
                                'value' => $updated->title,
                            ],
                            [
                                'label' => 'Estado',
                                'value' => $updated->status,
                            ],
                        ],
                        'status' => 'success',
                        'title' => 'Tarea actualizada',
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'result_ref_json' => (new TaskResource($updated))->resolve(),
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executeTaskAssignment(
        array $tool,
        array $context,
        array $draft
    ): array {
        $workspaceId = $context['workspace']->id;
        $entity = is_array($draft['entity'] ?? null) ? $draft['entity'] : [];
        $input = $this->validateTaskAssignmentInput(
            is_array($draft['input'] ?? null) ? $draft['input'] : [],
            $workspaceId
        );
        $taskIds = collect($entity['ids'] ?? [$entity['id'] ?? $input['task_id'] ?? null])
            ->filter()
            ->values();
        $versions = is_array($entity['versions'] ?? null) ? $entity['versions'] : [];
        $updatedTasks = collect();
        foreach ($taskIds as $taskId) {
            $task = $this->loadTaskForTool($workspaceId, (string) $taskId);
            $this->authorizeTaskUpdate($context, $task);
            $updated = $this->updateTask->execute(
                $task,
                (int) ($versions[$task->id] ?? $entity['version'] ?? $task->version),
                ['assignments' => [[
                    'membership_id' => $input['membership_id'],
                    'is_primary' => true,
                    'status' => 'assigned',
                ]]],
                $context['user']->id
            );

            if (!$updated) {
                throw ValidationException::withMessages([
                    'version' => ['The task changed before this confirmation was executed.'],
                ]);
            }
            $updatedTasks->push($this->loadTaskForTool($workspaceId, $updated->id));
        }

        $updated = $updatedTasks->first();
        $resource = (new TaskResource($updated))->resolve();
        $label = $updated->assignments->firstWhere('is_primary', true)?->membership?->user?->name
            ?? $input['membership_id'];
        $locale = (string) ($context['locale'] ?? 'en');
        $text = $updatedTasks->count() > 1
            ? trans('chat.tasks.bulk_assigned_text', ['count' => $updatedTasks->count(), 'name' => $label], $locale)
            : trans('chat.tasks.assigned_text', ['name' => $label], $locale);

        return [
            'blocks' => [
                ['text' => $text, 'type' => 'text'],
                ['component' => $tool['result_component'], 'data' => [
                    'description' => trans('chat.tasks.assigned_description', [], $locale),
                    'details' => [
                        ['label' => trans('chat.tasks.title_label', [], $locale), 'value' => $updated->title],
                        ['label' => trans('chat.tasks.assignee_label', [], $locale), 'value' => $label],
                    ],
                    'status' => 'success',
                    'title' => trans('chat.tasks.assigned_title', [], $locale),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => $updatedTasks->map(fn (Task $task): array => $this->taskEntityRef((new TaskResource($task))->resolve(), 'active'))->all(),
            'result_ref_json' => $updatedTasks->count() > 1
                ? ['count' => $updatedTasks->count(), 'items' => $updatedTasks->map(fn (Task $task): array => (new TaskResource($task))->resolve())->all()]
                : $resource,
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function previewTaskDelete(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $entity = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $resolution = $this->listTasksForTool->find(
            $context['workspace']->id,
            $entity['id'] ?? $input['task_id'] ?? null,
            $input['task_search'] ?? null,
            $context['entity_refs'] ?? []
        );
        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input, 'entity' => $entity],
                'task',
                'task_id',
                (string) ($input['task_search'] ?? ''),
                'task'
            );
            return $clarification ?? $this->taskResolutionResult($tool, $context, $resolution);
        }

        /** @var Task $task */
        $task = $resolution['entity'];
        Gate::forUser($context['user'])->authorize('delete', $task);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $task->title,
                'changes' => [[
                    'label' => trans('chat.tasks.title_label', [], $context['locale']),
                    'before' => $task->title,
                    'after' => trans('chat.tasks.removed', [], $context['locale']),
                ]],
                'destructive' => true,
                'description' => trans('chat.tasks.delete_preview_description', [], $context['locale']),
                'metadata' => [['label' => trans('chat.tasks.title_label', [], $context['locale']), 'value' => $task->title]],
                'title' => trans('chat.tasks.delete_preview_title', [], $context['locale']),
                'type' => trans('chat.tasks.delete_type', [], $context['locale']),
            ],
            [['label' => trans('chat.tasks.title_label', [], $context['locale']), 'value' => $task->title]],
            ['entity' => ['id' => $task->id, 'type' => 'task', 'version' => $task->version], 'input' => ['task_id' => $task->id], 'tool_key' => $tool['key']]
        );
    }

    private function previewDocumentWrite(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $resolution = $this->listDocumentsForTool->find($context['workspace']->id, $input['document_id'] ?? null, $input['document_search'] ?? null, $context['entity_refs'] ?? []);
        if (($resolution['status'] ?? null) !== 'resolved') {
            $clarification = $this->entityResolutionClarificationResult(
                $tool,
                $context,
                $resolution,
                ['input' => $input],
                'document',
                'document_id',
                (string) ($input['document_search'] ?? ''),
                'document'
            );
            return $clarification ?? $this->genericResolutionResult($tool, $context, $resolution, 'document');
        }
        /** @var Document $document */
        $document = $resolution['entity'];
        Gate::forUser($context['user'])->authorize('update', $document);
        if ($tool['key'] === 'documents.link_event') {
            $event = $this->directoryEntityResolver->resolve($context['workspace']->id, 'event', $input['event_id'] ?? null, $input['event_search'] ?? null, $context['entity_refs'] ?? []);
            if (($event['status'] ?? null) !== 'resolved') {
                $clarification = $this->entityResolutionClarificationResult(
                    $tool,
                    $context,
                    $event,
                    ['input' => $input],
                    'event',
                    'event_id',
                    (string) ($input['event_search'] ?? ''),
                    'event'
                );
                return $clarification ?? $this->genericResolutionResult($tool, $context, $event, 'event');
            }
            $input['document_id'] = $document->id;
            $input['event_id'] = $event['entity']->id;
            $after = $event['entity']->name;
        } else {
            $after = trans('chat.capabilities.retry_status', [], $context['locale']);
        }

        return $this->buildConfirmationPreview($tool, $source, $context, $payload, [
            'action' => $document->name,
            'changes' => [['label' => trans('chat.capabilities.document_label', [], $context['locale']), 'before' => $document->name, 'after' => $after]],
            'description' => trans('chat.capabilities.write_preview_description', [], $context['locale']),
            'metadata' => [['label' => trans('chat.capabilities.document_label', [], $context['locale']), 'value' => $document->name]],
            'title' => trans('chat.capabilities.write_preview_title', [], $context['locale']),
            'type' => trans('chat.capabilities.write_type', [], $context['locale']),
        ], [['label' => trans('chat.capabilities.document_label', [], $context['locale']), 'value' => $document->name]], [
            'entity' => ['id' => $document->id, 'type' => 'document', 'version' => $document->updated_at?->timestamp ?? 1],
            'input' => $input,
            'tool_key' => $tool['key'],
        ]);
    }

    private function executeDocumentWrite(array $tool, array $context, array $draft): array
    {
        $input = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        $document = Document::query()->where('workspace_id', $context['workspace']->id)->whereKey($draft['entity']['id'] ?? $input['document_id'] ?? null)->firstOrFail();
        Gate::forUser($context['user'])->authorize('update', $document);
        if ($tool['key'] === 'documents.retry_extraction') {
            $run = $this->retryDocumentExtraction->execute($document, $context['user']->id);
            return $this->completedActionResult($tool, $context, ['document_id' => $document->id, 'run_id' => $run->id, 'status' => $run->status], $document->name);
        }

        $event = $this->directoryEntityResolver->resolve($context['workspace']->id, 'event', $input['event_id'] ?? null, null, []);
        if (($event['status'] ?? null) !== 'resolved') {
            throw ValidationException::withMessages(['event_id' => ['The selected event is no longer available.']]);
        }
        $updated = $this->linkDocumentToEvent->execute($document, $event['entity'], $context['user']->id);
        return $this->completedActionResult($tool, $context, (new DocumentResource($updated->fresh(['links', 'latestBeoVersion', 'latestExtractionRun'])))->resolve(), $document->name);
    }

    private function previewNotificationPreferenceUpdate(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $eventKey = trim((string) ($input['event_key'] ?? ''));
        $changes = collect(['enabled', 'in_app', 'minimum_priority'])->filter(fn (string $key): bool => array_key_exists($key, $input))->map(fn (string $key): array => ['label' => Str::headline(str_replace('_', ' ', $key)), 'after' => (string) $input[$key]])->values()->all();
        $this->assertHasChanges($changes);
        return $this->buildConfirmationPreview($tool, $source, $context, $payload, [
            'action' => $eventKey,
            'changes' => $changes,
            'description' => trans('chat.capabilities.preference_preview_description', [], $context['locale']),
            'metadata' => [['label' => trans('chat.capabilities.preference_label', [], $context['locale']), 'value' => $eventKey]],
            'title' => trans('chat.capabilities.preference_preview_title', [], $context['locale']),
            'type' => trans('chat.capabilities.preference_type', [], $context['locale']),
        ], [['label' => trans('chat.capabilities.preference_label', [], $context['locale']), 'value' => $eventKey]], ['entity' => null, 'input' => $input, 'tool_key' => $tool['key']]);
    }

    private function executeNotificationPreferenceUpdate(array $tool, array $context, array $draft): array
    {
        $input = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        $result = $this->updateNotificationPreference->execute($context['workspace']->id, $context['user']->id, (string) ($input['event_key'] ?? ''), $input);
        return $this->completedActionResult($tool, $context, $result, (string) ($result['event_key'] ?? ''));
    }

    private function previewWorkspaceWrite(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $workspace = $context['workspace'];
        if ($tool['key'] === 'workspace.update') {
            Gate::forUser($context['user'])->authorize('update', $workspace);
            $normalized = collect($input)->only(['name', 'default_locale', 'timezone', 'currency'])->filter(fn ($value) => $value !== null && $value !== '')->all();
            $changes = collect($normalized)->map(fn ($value, $key): array => ['label' => Str::headline(str_replace('_', ' ', (string) $key)), 'before' => (string) ($workspace->{$key} ?? ''), 'after' => (string) $value])->values()->all();
            $label = $workspace->name;
            $entity = ['id' => $workspace->id, 'type' => 'workspace', 'version' => 1];
        } elseif ($tool['key'] === 'members.invite') {
            abort_unless($context['user']->hasWorkspacePermission($workspace->id, 'members.invite') || $context['user']->hasWorkspacePermission($workspace->id, 'members.manage'), 403);
            Validator::make($input, ['email' => ['required', 'email'], 'role_id' => ['nullable', 'ulid']])->validate();
            $changes = [['label' => trans('chat.capabilities.email_label', [], $context['locale']), 'after' => $input['email']]];
            $label = (string) $input['email'];
            $entity = null;
        } else {
            abort_unless($context['user']->hasWorkspacePermission($workspace->id, 'members.manage'), 403);
            $resolution = $this->listWorkspaceMembersForTool->find($workspace->id, $input['membership_id'] ?? null, $input['member_search'] ?? null, $context['entity_refs'] ?? []);
            if (($resolution['status'] ?? null) !== 'resolved') {
                $clarification = $this->entityResolutionClarificationResult(
                    $tool,
                    $context,
                    $resolution,
                    ['input' => $input],
                    'member',
                    'membership_id',
                    (string) ($input['member_search'] ?? ''),
                    'membership'
                );
                return $clarification ?? $this->genericResolutionResult($tool, $context, $resolution, 'member');
            }
            $member = $resolution['entity'];
            $label = $member->user?->name ?? $member->user?->email ?? $member->id;
            $changes = $tool['key'] === 'members.remove'
                ? [['label' => trans('chat.capabilities.member_label', [], $context['locale']), 'before' => $label, 'after' => trans('chat.capabilities.removed', [], $context['locale'])]]
                : collect($input)->only(['role_id', 'status'])->map(fn ($value, $key): array => ['label' => Str::headline(str_replace('_', ' ', (string) $key)), 'before' => (string) ($member->{$key} ?? ''), 'after' => (string) $value])->values()->all();
            $entity = ['id' => $member->id, 'type' => 'membership', 'version' => 1];
        }
        $this->assertHasChanges($changes);
        return $this->buildConfirmationPreview($tool, $source, $context, $payload, [
            'action' => $label, 'changes' => $changes, 'destructive' => $tool['key'] === 'members.remove',
            'description' => trans('chat.capabilities.workspace_preview_description', [], $context['locale']),
            'metadata' => [['label' => trans('chat.capabilities.member_label', [], $context['locale']), 'value' => $label]],
            'title' => trans('chat.capabilities.workspace_preview_title', [], $context['locale']), 'type' => trans('chat.capabilities.workspace_type', [], $context['locale']),
        ], [['label' => trans('chat.capabilities.member_label', [], $context['locale']), 'value' => $label]], ['entity' => $entity, 'input' => $input, 'tool_key' => $tool['key']]);
    }

    private function executeWorkspaceWrite(array $tool, array $context, array $draft): array
    {
        $input = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        $workspace = $context['workspace'];
        if ($tool['key'] === 'workspace.update') {
            $updated = $this->updateWorkspace->execute($workspace, collect($input)->only(['name', 'default_locale', 'timezone', 'currency'])->all());
            return $this->completedActionResult($tool, $context, $updated->toArray(), $updated->name);
        }
        if ($tool['key'] === 'members.invite') {
            $result = $this->inviteWorkspaceMember->execute($workspace, $context['user']->id, (string) $input['email'], $input['role_id'] ?? null);
            return $this->completedActionResult($tool, $context, $result, (string) ($input['email'] ?? ''));
        }
        $membership = WorkspaceMembership::query()->where('workspace_id', $workspace->id)->whereKey($draft['entity']['id'] ?? $input['membership_id'] ?? null)->firstOrFail();
        $updated = $tool['key'] === 'members.remove'
            ? $this->removeWorkspaceMembership->execute($membership, $context['user']->id)
            : $this->updateWorkspaceMembership->execute($membership, $context['user']->id, collect($input)->only(['role_id', 'status'])->all());
        return $this->completedActionResult($tool, $context, ['id' => $updated->id, 'status' => $updated->status, 'user' => $updated->user?->name], $updated->user?->name ?? $updated->id);
    }

    private function executeTaskDelete(array $tool, array $context, array $draft): array
    {
        $input = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        $entity = is_array($draft['entity'] ?? null) ? $draft['entity'] : [];
        $task = Task::query()->where('workspace_id', $context['workspace']->id)->whereKey($entity['id'] ?? $input['task_id'] ?? null)->firstOrFail();
        Gate::forUser($context['user'])->authorize('delete', $task);
        $label = $task->title;
        $this->deleteTask->execute($task);

        return $this->completedActionResult($tool, $context, ['id' => $task->id, 'type' => 'task', 'deleted' => true], $label);
    }

    private function taskResolutionResult(array $tool, array $context, array $resolution): array
    {
        $locale = (string) ($context['locale'] ?? 'en');
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.tasks.ambiguous', [], $locale)
            : trans('chat.tasks.not_found', [], $locale);
        return [
            'blocks' => [['text' => $text, 'type' => 'text']],
            'entity_refs' => [],
            'result_ref_json' => ['candidates' => $resolution['candidates'] ?? []],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function previewTeamStaffWrite(array $tool, array $context, array $payload, array $source): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $type = (string) $tool['entity_type'];
        $input = $this->resolveTeamStaffInput($context, $input, $type);
        $entity = null;
        if ($tool['operation_type'] !== 'create' && $type !== 'availability' && $tool['key'] !== 'teams.members.sync') {
            $resolution = $this->teamStaffEntityResolver->resolve(
                $context['workspace']->id,
                $type,
                $input[$type.'_id'] ?? null,
                $input[$type.'_search'] ?? ($type === 'shift' ? ($input['member_search'] ?? null) : null),
                $context['entity_refs'] ?? []
            );
            if (($resolution['status'] ?? null) !== 'resolved') {
                if (in_array($resolution['status'] ?? null, ['ambiguous', 'suggested_match'], true)) {
                    $reference = (string) ($input[$type.'_search'] ?? ($type === 'shift' ? ($input['member_search'] ?? '') : ''));
                    return $this->entityDisambiguationResult(
                        $tool,
                        $context,
                        $payload,
                        $type.'_id',
                        $reference,
                        $resolution['candidates'] ?? [],
                        ($resolution['status'] ?? null) === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate'
                    );
                }

                return $this->teamStaffResolutionResult($tool, $context, $resolution, $type);
            }
            $entity = $resolution['entity'];
            $payload['entity'] = ['id' => $entity->id, 'type' => $type, 'version' => $entity->version ?? 1];
        }

        if ($type === 'availability') {
            $membership = $this->resolveTeamStaffMembership($context, $input);
            if (($membership['status'] ?? null) !== 'resolved') {
                if (in_array($membership['status'] ?? null, ['ambiguous', 'suggested_match'], true)) {
                    return $this->entityDisambiguationResult(
                        $tool,
                        $context,
                        $payload,
                        'membership_id',
                        (string) ($input['member_search'] ?? ''),
                        $membership['candidates'] ?? [],
                        ($membership['status'] ?? null) === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate',
                        'membership',
                        'member'
                    );
                }

                return $this->teamStaffResolutionResult($tool, $context, $membership, 'member');
            }
            $payload['entity'] = ['id' => $membership['entity']->id, 'type' => 'membership', 'version' => 1];
        }

        if ($tool['key'] === 'teams.members.sync') {
            $resolution = $this->teamStaffEntityResolver->resolve($context['workspace']->id, 'team', $input['team_id'] ?? null, $input['team_search'] ?? null, $context['entity_refs'] ?? []);
            if (($resolution['status'] ?? null) !== 'resolved') {
                if (in_array($resolution['status'] ?? null, ['ambiguous', 'suggested_match'], true)) {
                    return $this->entityDisambiguationResult(
                        $tool,
                        $context,
                        $payload,
                        'team_id',
                        (string) ($input['team_search'] ?? ''),
                        $resolution['candidates'] ?? [],
                        ($resolution['status'] ?? null) === 'suggested_match' ? 'confirm_suggestion' : 'choose_candidate'
                    );
                }

                return $this->teamStaffResolutionResult($tool, $context, $resolution, 'team');
            }
            $entity = $resolution['entity'];
            $payload['entity'] = ['id' => $entity->id, 'type' => 'team', 'version' => 1];
        }

        $this->authorizeTeamStaff($tool, $context, $entity);
        $label = $entity ? $this->teamStaffEntityResolver->label($entity, $type === 'availability' ? 'membership' : $type) : ($input['name'] ?? $type);
        $locale = (string) ($context['locale'] ?? 'en');
        $changes = collect($input)->reject(fn ($value, $key) => str_ends_with((string) $key, '_id') || str_ends_with((string) $key, '_search') || $value === null || $value === '')->map(fn ($value, $key) => [
            'label' => Str::headline(str_replace('_', ' ', (string) $key)),
            'after' => is_scalar($value) ? (string) $value : json_encode($value),
            'before' => null,
        ])->values()->all();

        return $this->buildConfirmationPreview($tool, $source, $context, $payload, [
            'action' => $label,
            'changes' => $changes,
            'description' => trans('chat.team_staff.write_preview_description', [], $locale),
            'metadata' => [['label' => trans('chat.team_staff.entity_label', [], $locale), 'value' => $label]],
            'title' => trans('chat.team_staff.write_preview_title', [], $locale),
            'type' => trans('chat.team_staff.write_type', [], $locale),
        ], [['label' => trans('chat.team_staff.entity_label', [], $locale), 'value' => $label]], [
            'entity' => $payload['entity'] ?? null, 'input' => $input, 'tool_key' => $tool['key'],
        ]);
    }

    private function resolveTeamStaffInput(array $context, array $input, string $type): array
    {
        foreach ([['team', 'team_id', 'team_search'], ['station', 'station_id', 'station_search'], ['membership', 'membership_id', 'member_search']] as [$entityType, $idKey, $searchKey]) {
            if ($entityType === 'membership' && $type !== 'shift') continue;
            if (empty($input[$idKey]) && empty($input[$searchKey])) continue;
            $resolution = $this->teamStaffEntityResolver->resolve($context['workspace']->id, $entityType, $input[$idKey] ?? null, $input[$searchKey] ?? null, $context['entity_refs'] ?? []);
            if (($resolution['status'] ?? null) !== 'resolved') {
                throw ValidationException::withMessages([$idKey => [($resolution['status'] ?? null) === 'ambiguous' ? 'The selected staff record is ambiguous.' : 'The selected staff record was not found.']]);
            }
            $input[$idKey] = $resolution['entity']->id;
        }
        unset($input['team_search'], $input['station_search'], $input['member_search']);
        return $input;
    }

    private function executeTeamStaffWrite(array $tool, array $context, array $draft): array
    {
        $workspaceId = $context['workspace']->id;
        $input = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        $type = (string) $tool['entity_type'];
        $entity = null;
        if ($type !== 'availability') {
            $entityId = $draft['entity']['id'] ?? $input[$type.'_id'] ?? null;
            if ($entityId) {
                $entity = $this->teamStaffEntityResolver->resolve($workspaceId, $type, $entityId, null, [])['entity'] ?? null;
            }
        }
        if ($tool['key'] === 'teams.members.sync') {
            if (!$entity instanceof Team) throw ValidationException::withMessages(['team' => ['The team is no longer available.']]);
            $updated = $this->syncTeamMembers->execute($entity, $workspaceId, $input['member_ids'] ?? [], $input['lead_membership_id'] ?? null);
            $resource = (new \App\Http\Resources\TeamResource($updated))->resolve();
            return $this->completedActionResult($tool, $context, $resource, $updated->name);
        }
        if ($tool['operation_type'] === 'delete') {
            if (!$entity) throw ValidationException::withMessages(['entity' => ['The selected record is no longer available.']]);
            $this->deleteTeamStaffEntity->execute($entity);
            return $this->completedActionResult($tool, $context, ['id' => $entity->id, 'type' => $type], $this->teamStaffEntityResolver->label($entity, $type));
        }
        if ($type === 'availability') {
            $membershipId = $draft['entity']['id'] ?? $input['membership_id'] ?? null;
            $membership = WorkspaceMembership::query()->where('workspace_id', $workspaceId)->findOrFail($membershipId);
            $result = $this->syncAvailability->execute($membership, $input);
            return $this->completedActionResult($tool, $context, $result, $membership->user?->name ?? $membership->id);
        }
        $userId = $context['user']->id;
        $updated = match ($tool['key']) {
            'teams.create' => $this->createTeam->execute($workspaceId, $userId, $input),
            'teams.update' => $this->updateTeam->execute($entity, $workspaceId, $userId, $input),
            'stations.create' => $this->createStation->execute($workspaceId, $userId, $input),
            'stations.update' => $this->updateStation->execute($entity, $userId, $input),
            'shifts.create' => $this->createShift->execute($workspaceId, $userId, $input),
            'shifts.update' => $this->updateShift->execute($entity, $userId, $input),
            default => throw ValidationException::withMessages(['action_id' => ['The selected team staff action is not executable.']]),
        };
        $resource = match ($type) {
            'team' => (new \App\Http\Resources\TeamResource($updated))->resolve(),
            'station' => (new \App\Http\Resources\StationResource($updated))->resolve(),
            default => (new \App\Http\Resources\ShiftResource($updated))->resolve(),
        };
        return $this->completedActionResult($tool, $context, $resource, $this->teamStaffEntityResolver->label($updated, $type));
    }

    private function resolveTeamStaffMembership(array $context, array $input): array
    {
        return $this->teamStaffEntityResolver->resolve(
            $context['workspace']->id, 'membership', $input['membership_id'] ?? null,
            $input['member_search'] ?? null, $context['entity_refs'] ?? []
        );
    }

    private function authorizeTeamStaff(array $tool, array $context, mixed $entity = null): void
    {
        $ability = $tool['operation_type'] === 'create' ? 'create' : ($tool['operation_type'] === 'delete' ? 'delete' : 'update');
        if ($tool['entity_type'] === 'availability') {
            Gate::forUser($context['user'])->authorize(
                $tool['operation_type'] === 'read' ? 'viewAny' : 'create',
                \App\Models\Availability::class
            );
            return;
        }
        Gate::forUser($context['user'])->authorize($ability, $entity ?? match ($tool['entity_type']) {
            'team' => Team::class, 'station' => Station::class, default => Shift::class,
        });
    }

    private function teamStaffResolutionResult(array $tool, array $context, array $resolution, string $entity): array
    {
        $locale = (string) ($context['locale'] ?? 'en');
        if (($resolution['status'] ?? null) === 'ambiguous') {
            return ['blocks' => [['component' => 'clarification.options', 'data' => [
                'title' => trans('chat.team_staff.choose_entity_title', ['entity' => $entity], $locale),
                'description' => trans('chat.team_staff.choose_entity', ['entity' => $entity], $locale),
                'options' => collect($resolution['candidates'] ?? [])->map(fn ($candidate) => ['id' => $candidate['id'], 'label' => $candidate['name'], 'value' => $candidate['name']])->all(),
                'selection_mode' => 'immediate',
            ], 'schema_version' => 1, 'type' => 'component']], 'entity_refs' => [], 'tool_keys' => []];
        }
        return ['blocks' => [['component' => 'error.recovery', 'data' => [
            'title' => trans('chat.team_staff.not_found_title', [], $locale), 'description' => trans('chat.team_staff.not_found', ['entity' => $entity], $locale), 'safe_detail' => trans('chat.team_staff.not_found', ['entity' => $entity], $locale), 'error_code' => 'ENTITY_NOT_FOUND',
        ], 'schema_version' => 1, 'type' => 'component']], 'entity_refs' => [], 'tool_keys' => []];
    }

    private function executeTaskCreate(
        array $tool,
        array $context,
        array $draft
    ): array {
        $input = $this->validateTaskCreateInput(
            is_array($draft['input'] ?? null) ? $draft['input'] : []
        );
        $workspaceId = $context['workspace']->id;

        Gate::forUser($context['user'])->authorize('create', Task::class);

        $task = $this->createTask->execute(
            $workspaceId,
            $context['user']->id,
            $input
        );
        $task = $this->loadTaskForTool($workspaceId, $task->id);
        $resource = (new TaskResource($task))->resolve();
        $locale = (string) ($context['locale'] ?? 'en');

        return [
            'blocks' => [
                [
                    'text' => trans('chat.tasks.created_text', [], $locale),
                    'type' => 'text',
                ],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'description' => trans('chat.tasks.created_description', [], $locale),
                        'details' => [
                            [
                                'label' => trans('chat.tasks.title_label', [], $locale),
                                'value' => $task->title,
                            ],
                            [
                                'label' => trans('chat.tasks.status_label', [], $locale),
                                'value' => $task->status,
                            ],
                        ],
                        'status' => 'success',
                        'title' => trans('chat.tasks.created_title', [], $locale),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => [[
                'id' => $resource['id'],
                'role' => 'active',
                'snapshot' => $resource,
                'type' => 'task',
                'version' => $resource['version'] ?? 1,
            ]],
            'result_ref_json' => $resource,
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executeDirectoryWrite(
        array $tool,
        array $context,
        array $draft
    ): array {
        $type = (string) $tool['entity_type'];
        $operation = (string) $tool['operation_type'];
        $input = $this->normalizeDirectoryInput(
            $type,
            $operation,
            is_array($draft['input'] ?? null) ? $draft['input'] : [],
            $context
        );
        $workspaceId = $context['workspace']->id;
        $userId = $context['user']->id;
        $entity = null;

        if ($operation === 'create') {
            $entity = match ($type) {
                'event' => app(\App\Application\Actions\Events\CreateEvent::class)->execute($workspaceId, $userId, $input),
                'client' => app(\App\Application\Actions\Clients\CreateClient::class)->execute($workspaceId, $userId, $input),
                'contact' => app(\App\Application\Actions\Contacts\CreateContact::class)->execute($workspaceId, $userId, $input),
                'venue' => app(\App\Application\Actions\Venues\CreateVenue::class)->execute($workspaceId, $userId, $input),
            };
        } else {
            $entityPayload = is_array($draft['entity'] ?? null) ? $draft['entity'] : [];
            $resolution = $this->directoryEntityResolver->resolve(
                $workspaceId,
                $type,
                $entityPayload['id'] ?? null,
                null,
                []
            );
            if (($resolution['status'] ?? null) !== 'resolved') {
                throw ValidationException::withMessages(['entity' => ['The selected entity is no longer available.']]);
            }
            $entity = $this->loadDirectoryEntity($resolution['entity'], $type);
            $this->authorizeDirectoryTool($tool, $context, $entity);

            if ($type === 'event' && $operation === 'cancel') {
                $entity = app(\App\Application\Actions\Events\CancelEvent::class)->execute($entity, $userId);
                if (!$entity) {
                    throw ValidationException::withMessages(['version' => ['The event changed before confirmation.']]);
                }
            } elseif ($operation === 'delete') {
                $result = match ($type) {
                    'event' => app(\App\Application\Actions\Events\DeleteEvent::class)->execute($entity),
                    'client' => app(\App\Application\Actions\Clients\DeleteClient::class)->execute($entity),
                    'contact' => app(\App\Application\Actions\Contacts\DeleteContact::class)->execute($entity),
                    'venue' => app(\App\Application\Actions\Venues\DeleteVenue::class)->execute($entity),
                };
                if (!$result['deleted']) {
                    throw ValidationException::withMessages(['dependencies' => ['This record still has related records.']]);
                }
            } else {
                $entity = match ($type) {
                    'event' => app(\App\Application\Actions\Events\UpdateEvent::class)->execute($entity, (int) ($entityPayload['version'] ?? $entity->version), $input, $userId),
                    'client' => app(\App\Application\Actions\Clients\UpdateClient::class)->execute($entity, $userId, $input),
                    'contact' => app(\App\Application\Actions\Contacts\UpdateContact::class)->execute($entity, $userId, $input),
                    'venue' => app(\App\Application\Actions\Venues\UpdateVenue::class)->execute($entity, $userId, $input),
                };
                if (!$entity) {
                    throw ValidationException::withMessages(['version' => ['The record changed before confirmation.']]);
                }
            }
        }

        if ($operation === 'delete') {
            $completedLabel = $this->directoryEntityResolver->label($entity, $type);
            $resource = ['id' => $draft['entity']['id'] ?? null, 'deleted' => true, 'type' => $type];
            $entityRefs = [];
        } else {
            $completedLabel = $this->directoryEntityResolver->label($entity, $type);
            $entity = $this->loadDirectoryEntity($entity, $type);
            $resource = $this->directoryResource($entity, $type);
            $entityRefs = [$this->directoryEntityRef($entity, $type, 'active')];
        }

        $locale = (string) ($context['locale'] ?? 'en');
        return [
            'blocks' => [
                ['text' => trans('chat.directory.write_completed', [], $locale), 'type' => 'text'],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'description' => trans('chat.directory.write_completed_description', [], $locale),
                        'details' => [['label' => trans('chat.directory.entity_label', [], $locale), 'value' => $completedLabel]],
                        'status' => 'success',
                        'title' => trans('chat.directory.write_completed_title', [], $locale),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => $entityRefs,
            'result_ref_json' => $resource,
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executePrepGeneration(array $tool, array $context, array $draft): array
    {
        $workspaceId = $context['workspace']->id;
        $input = $this->validatePrepGenerationInput(is_array($draft['input'] ?? null) ? $draft['input'] : [], $workspaceId);
        $target = $this->resolvePrepGenerationTarget($context, $input);
        if (($target['status'] ?? null) !== 'resolved') {
            throw ValidationException::withMessages(['prep' => ['The event or prep list is no longer available.']]);
        }

        $prepList = $target['prep_list'];
        $draftContext = is_array($draft['generation_context'] ?? null) ? $draft['generation_context'] : [];
        if ($prepList instanceof PrepList && array_key_exists('current_version', $draftContext)
            && (int) $prepList->current_version !== (int) $draftContext['current_version']) {
            throw ValidationException::withMessages(['version' => ['The prep list changed before this confirmation was executed.']]);
        }

        $result = DB::transaction(function () use ($context, $input, $target, $tool): array {
            $prepList = $target['prep_list'];
            if (!$prepList instanceof PrepList) {
                Gate::forUser($context['user'])->authorize('create', PrepList::class);
                $prepList = $this->createPrepList->execute(
                    $context['workspace']->id,
                    $context['user']->id,
                    [
                        'event_id' => $target['event']->id,
                        'name' => $input['name'] ?? ($target['event']->name.' Prep'),
                        'production_ends_at' => $input['due_at'] ?? null,
                        'timezone' => $input['timezone'] ?? $target['event']->timezone,
                    ]
                );
            }
            Gate::forUser($context['user'])->authorize('update', $prepList);

            return $this->generatePrepList->execute(
                $prepList,
                $context['workspace']->id,
                $context['user']->id,
                $this->generationAttributes($input, $tool),
                true
            );
        });

        $resource = (new PrepListResource($result['prep_list']))->resolve();
        $locale = (string) ($context['locale'] ?? 'en');
        return [
            'blocks' => [
                ['text' => trans('chat.prep.generation_completed', [], $locale), 'type' => 'text'],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'generation' => [
                            'guest_count' => $result['version']?->guest_count_snapshot,
                            'items' => $result['items'],
                            'menu_label' => $result['menu_label'],
                            'summary' => $result['summary'],
                            'warnings' => $result['warnings'],
                        ],
                        'prep_list' => $resource,
                        'title' => trans('chat.prep.generation_result_title', [], $locale),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => [$this->prepListEntityRef($resource, 'active')],
            'result_ref_json' => [
                'prep_list' => $resource,
                'warnings' => $result['warnings'],
                'summary' => $result['summary'],
            ],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executePrepListUpdate(array $tool, array $context, array $draft): array
    {
        $input = $this->validatePrepListInput(is_array($draft['input'] ?? null) ? $draft['input'] : []);
        $entity = $this->validateEntityPayload(is_array($draft['entity'] ?? null) ? $draft['entity'] : [], 'prep_list');
        $prepList = $this->prepEntityResolver->resolveList($context['workspace']->id, [], $entity['id'])['prep_list'] ?? null;
        if (!$prepList instanceof PrepList) {
            throw ValidationException::withMessages(['prep_list' => ['The prep list is no longer available.']]);
        }
        Gate::forUser($context['user'])->authorize('update', $prepList);
        $updated = $this->updatePrepList->execute($prepList, $context['workspace']->id, $context['user']->id, $input);
        $resource = (new PrepListResource($this->prepEntityResolver->resolveList($context['workspace']->id, [], $updated->id)['prep_list']))->resolve();
        $locale = (string) ($context['locale'] ?? 'en');

        return [
            'blocks' => [
                ['text' => trans('chat.prep.list_update_completed', [], $locale), 'type' => 'text'],
                ['component' => $tool['result_component'], 'data' => [
                    'prep_list' => $resource,
                    'title' => trans('chat.prep.detail_title', [], $locale),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [$this->prepListEntityRef($resource, 'active')],
            'result_ref_json' => $resource,
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function executePrepItemUpdate(
        array $tool,
        array $context,
        array $draft
    ): array {
        $workspaceId = $context['workspace']->id;
        $resolved = $this->resolvePrepItemPayload($tool, $context, $draft);
        if (isset($resolved['resolution'])) {
            throw ValidationException::withMessages(['prep_item' => ['The production item is no longer available.']]);
        }
        $entity = $resolved['entity'];
        $input = $this->validatePrepItemInput($resolved['input'], $workspaceId);
        $prepItem = $this->loadPrepItemForTool($workspaceId, $entity['id']);

        Gate::forUser($context['user'])->authorize('update', $prepItem);

        $updated = $this->updatePrepItem->execute(
            $prepItem,
            (int) $entity['version'],
            $this->mapPrepItemUpdateAttributes($input),
            $context['user']->id
        );

        if (!$updated) {
            throw ValidationException::withMessages([
                'version' => ['The prep item changed before this confirmation was executed.'],
            ]);
        }

        return [
            'blocks' => [
                [
                    'text' => 'El ítem de producción se actualizó correctamente.',
                    'type' => 'text',
                ],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'description' => 'La actualización confirmada ya fue aplicada.',
                        'details' => [
                            [
                                'label' => 'Prep item',
                                'value' => $updated->title,
                            ],
                            [
                                'label' => 'Estado',
                                'value' => $updated->status,
                            ],
                        ],
                        'status' => 'success',
                        'title' => 'Prep item actualizado',
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'result_ref_json' => (new PrepItemResource($updated))->resolve(),
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function previewMenuCreate(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        Gate::forUser($context['user'])->authorize('create', Menu::class);

        $draft = $this->canonicalizeMenuDraft(
            is_array($payload['input'] ?? null) ? $payload['input'] : [],
            $context['workspace']->id
        );
        $previewData = $this->menuPreviewData($draft);

        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            $previewData,
            [
                [
                    'label' => 'Menú',
                    'value' => $draft['name'],
                ],
                [
                    'label' => 'Ítems',
                    'value' => (string) $previewData['item_count'],
                ],
                [
                    'label' => 'Recetas enlazadas',
                    'value' => (string) $previewData['recipe_match_count'],
                ],
                [
                    'label' => 'Sin receta',
                    'value' => (string) $previewData['unresolved_count'],
                ],
            ],
            [
                'input' => $draft,
                'tool_key' => $tool['key'],
            ]
        );
    }

    private function executeMenuCreate(
        array $tool,
        array $context,
        array $draft
    ): array {
        $workspaceId = $context['workspace']->id;
        $menuDraft = $this->canonicalizeMenuDraft(
            is_array($draft['input'] ?? null) ? $draft['input'] : [],
            $workspaceId
        );

        Gate::forUser($context['user'])->authorize('create', Menu::class);

        $menu = $this->createMenu->execute(
            $workspaceId,
            $context['user']->id,
            $menuDraft['payload']
        );
        $menu = $this->loadMenuForTool($workspaceId, $menu->id);
        $resource = (new MenuResource($menu))->resolve();
        $itemCount = (int) ($resource['item_count'] ?? 0);
        $recipeCount = (int) ($resource['recipe_count'] ?? 0);

        return [
            'blocks' => [
                [
                    'text' => 'El menú se creó correctamente después de tu confirmación.',
                    'type' => 'text',
                ],
                [
                    'component' => $tool['result_component'],
                    'data' => [
                        'description' => 'El menú ya está disponible en Recipes/Menus. Los ítems sin receta quedaron registrados sin receta inventada.',
                        'details' => [
                            ['label' => 'Menú', 'value' => $menu->name],
                            ['label' => 'Ítems', 'value' => (string) $itemCount],
                            ['label' => 'Recetas enlazadas', 'value' => (string) $recipeCount],
                            ['label' => 'Sin receta', 'value' => (string) max(0, $itemCount - $recipeCount)],
                        ],
                        'status' => 'success',
                        'title' => 'Menú creado',
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'entity_refs' => [[
                'id' => $resource['id'],
                'role' => 'active',
                'snapshot' => $resource,
                'type' => 'menu',
                'version' => $resource['current_version'] ?? null,
            ]],
            'result_ref_json' => $resource,
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function canonicalizeMenuDraft(array $draft, string $workspaceId): array
    {
        // Confirmation drafts created by the preview flow contain the already
        // normalized menu payload under `payload`. Flatten it back into the
        // canonical input shape before applying the same validation used for
        // a first-time preview. This also keeps pending confirmations created
        // before this fix executable.
        if (is_array($draft['payload'] ?? null) && is_array($draft['payload']['sections'] ?? null)) {
            $payload = $draft['payload'];
            $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];

            $draft = [
                'name' => $draft['name'] ?? $payload['name'] ?? null,
                'sections' => $payload['sections'],
                'requested_guest_count' => $metadata['requested_guest_count'] ?? null,
                'excluded_items' => $metadata['excluded_items'] ?? [],
                'source' => $metadata['source'] ?? ['type' => 'chat'],
            ];
        }

        $validated = Validator::make($draft, [
            'name' => ['required', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.name' => ['required', 'string', 'max:255'],
            'sections.*.type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sections.*.items' => ['required', 'array', 'min:1'],
            'sections.*.items.*.name' => ['required', 'string', 'max:255'],
            'sections.*.items.*.type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sections.*.items.*.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.*.items.*.notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sections.*.items.*.quantity_per_guest' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.items.*.serving_unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sections.*.items.*.quantity_suggestion' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sections.*.items.*.serving_unit_suggestion' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sections.*.items.*.recipe_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('recipes', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId))],
            'sections.*.items.*.recipe_version_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('recipe_versions', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId))],
            'requested_guest_count' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'excluded_items' => ['sometimes', 'array'],
            'excluded_items.*' => ['string', 'max:255'],
            'source' => ['sometimes', 'array'],
            'source.type' => ['sometimes', 'string', 'max:30'],
            'source.text' => ['sometimes', 'string', 'max:10000'],
        ])->validate();

        $recipeResolutions = [];
        $payloadSections = [];
        $itemCount = 0;

        foreach ($validated['sections'] as $sectionIndex => $section) {
            $payloadItems = [];

            foreach ($section['items'] as $itemIndex => $item) {
                $resolution = $this->resolveRecipeForMenuItem($workspaceId, $item['name']);
                $recipeResolutions[] = [
                    'item' => $item['name'],
                    ...$resolution['metadata'],
                ];
                $approvedRecipeId = $item['recipe_id'] ?? null;
                $approvedRecipeVersionId = $item['recipe_version_id'] ?? null;
                $quantitySuggestion = $item['quantity_suggestion'] ?? null;
                $servingUnitSuggestion = $item['serving_unit_suggestion'] ?? null;
                $quantitySource = isset($item['quantity_per_guest'])
                    ? ($quantitySuggestion !== null && (float) $item['quantity_per_guest'] === (float) $quantitySuggestion
                        ? 'ai_suggestion'
                        : 'user')
                    : null;
                $payloadItems[] = [
                    'name' => trim($item['name']),
                    'description' => $item['description'] ?? null,
                    'type' => $item['type'] ?? 'dish',
                    'notes' => $item['notes'] ?? null,
                    'position' => $itemIndex + 1,
                    'recipe_id' => $approvedRecipeId,
                    'recipe_version_id' => $approvedRecipeVersionId,
                    'quantity_per_guest' => $item['quantity_per_guest'] ?? null,
                    'serving_unit' => $item['serving_unit'] ?? null,
                    'quantity_suggestion' => $quantitySuggestion,
                    'serving_unit_suggestion' => $servingUnitSuggestion,
                    'metadata' => [
                        'ai_recipe_resolution' => $resolution['metadata'],
                        'recipe_suggestion' => $resolution['recipe_id'] ? [
                            'recipe_id' => $resolution['recipe_id'],
                            'recipe_version_id' => $resolution['recipe_version_id'],
                            'name' => $resolution['recipe_name'],
                            'status' => 'pending_approval',
                        ] : null,
                        'recipe_source' => $approvedRecipeId
                            ? ($approvedRecipeId === $resolution['recipe_id'] ? 'ai_suggestion' : 'user')
                            : null,
                        'quantity_source' => $quantitySource,
                        'quantity_suggestion' => $quantitySuggestion,
                        'serving_unit_suggestion' => $servingUnitSuggestion,
                        'approval_status' => 'pending',
                    ],
                ];
                $itemCount++;
            }

            $payloadSections[] = [
                'name' => trim($section['name']),
                'type' => $section['type'] ?? null,
                'position' => $sectionIndex + 1,
                'items' => $payloadItems,
            ];
        }

        $requestedGuestCount = $validated['requested_guest_count'] ?? null;
        $metadata = [
            'created_via' => 'chat',
            'source' => $validated['source'] ?? ['type' => 'chat'],
            'excluded_items' => $validated['excluded_items'] ?? [],
            'requested_guest_count' => $requestedGuestCount,
            'recipe_resolutions' => $recipeResolutions,
            'prep_guest_count' => $requestedGuestCount,
        ];

        return [
            'name' => trim($validated['name']),
            'item_count' => $itemCount,
            'recipe_resolutions' => $recipeResolutions,
            'payload' => [
                'name' => trim($validated['name']),
                'status' => 'draft',
                'metadata' => $metadata,
                'sections' => $payloadSections,
            ],
        ];
    }

    private function menuPreviewData(array $draft): array
    {
        $recipeMatchCount = collect($draft['recipe_resolutions'])
            ->where('status', 'matched')
            ->count();
        $unresolvedCount = collect($draft['recipe_resolutions'])
            ->reject(fn (array $resolution): bool => ($resolution['status'] ?? null) === 'matched')
            ->count();
        $changes = [];

        foreach ($draft['payload']['sections'] as $section) {
            $changes[] = [
                'label' => $section['name'],
                'after' => collect($section['items'])->pluck('name')->implode(', '),
            ];
        }

        return [
            'items' => collect($draft['payload']['sections'])
                ->flatMap(fn (array $section) => collect($section['items'])->map(fn (array $item) => [
                    'id' => $item['name'],
                    'name' => $item['name'],
                    'quantity_per_guest' => $item['quantity_per_guest'] ?? null,
                    'serving_unit' => $item['serving_unit'] ?? null,
                    'quantity_suggestion' => $item['metadata']['quantity_suggestion'] ?? null,
                    'serving_unit_suggestion' => $item['metadata']['serving_unit_suggestion'] ?? null,
                    'recipe_id' => $item['recipe_id'] ?? null,
                    'recipe_version_id' => $item['recipe_version_id'] ?? null,
                    'recipe_suggestion' => $item['metadata']['recipe_suggestion'] ?? null,
                    'preview_total' => isset($item['quantity_per_guest'], $draft['payload']['metadata']['requested_guest_count'])
                        ? round((float) $item['quantity_per_guest'] * (int) $draft['payload']['metadata']['requested_guest_count'], 4)
                        : null,
                    'section' => $section['name'],
                ]))->values()->all(),
            'action' => $draft['name'],
            'changes' => $changes,
            'description' => 'Revisa las secciones e ítems antes de crear el menú. El número de invitados se conserva como dato de preparación y no modifica ningún evento.',
            'impact' => ($draft['payload']['metadata']['prep_guest_count'] ?? null)
                ? 'Preparación solicitada para '.(int) $draft['payload']['metadata']['prep_guest_count'].' personas.'
                : null,
            'item_count' => $draft['item_count'],
            'metadata' => [
                ['label' => 'Recetas enlazadas', 'value' => (string) $recipeMatchCount],
                ['label' => 'Ítems sin receta', 'value' => (string) $unresolvedCount],
                ['label' => 'Exclusiones', 'value' => (string) count($draft['payload']['metadata']['excluded_items'] ?? [])],
            ],
            'recipe_match_count' => $recipeMatchCount,
            'title' => 'Borrador de menú',
            'type' => 'Menu creation',
            'unresolved_count' => $unresolvedCount,
        ];
    }

    private function resolveRecipeForMenuItem(string $workspaceId, string $itemName): array
    {
        $matches = Recipe::query()
            ->where('workspace_id', $workspaceId)
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($itemName))])
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            return [
                'recipe_id' => null,
                'recipe_version_id' => null,
                'recipe_name' => null,
                'metadata' => [
                    'status' => $matches->isEmpty() ? 'not_found' : 'ambiguous',
                    'confidence' => 'none',
                ],
            ];
        }

        $recipe = $matches->first();
        $recipeVersion = RecipeVersion::query()
            ->where('workspace_id', $workspaceId)
            ->where('recipe_id', $recipe->id)
            ->where('version', $recipe->current_version)
            ->first();

        if (!$recipeVersion) {
            return [
                'recipe_id' => null,
                'recipe_version_id' => null,
                'recipe_name' => null,
                'metadata' => [
                    'status' => 'no_current_version',
                    'confidence' => 'none',
                ],
            ];
        }

        return [
            'recipe_id' => $recipe->id,
            'recipe_version_id' => $recipeVersion?->id,
            'recipe_name' => $recipe->name,
            'metadata' => [
                'status' => 'matched',
                'confidence' => 'exact_name',
            ],
        ];
    }

    private function loadMenuForTool(string $workspaceId, string $menuId): Menu
    {
        return Menu::query()
            ->whereKey($menuId)
            ->where('workspace_id', $workspaceId)
            ->with([
                'createdBy',
                'updatedBy',
                'currentVersionRecord.createdBy',
                'currentVersionRecord.approvedBy',
                'currentVersionRecord.sections.items.recipe.currentVersionRecord',
                'currentVersionRecord.sections.items.recipeVersion.allergens',
                'currentVersionRecord.eventAssignments.event.venue',
            ])
            ->firstOrFail();
    }

    private function buildConfirmationPreview(
        array $tool,
        array $source,
        array $context,
        array $payload,
        array $previewData,
        array $confirmationDetails,
        array $draft
    ): array {
        [$token, $confirmation] = $this->createConfirmation(
            $source,
            $tool,
            $context,
            $payload,
            [
                ...$draft,
                'preview' => $previewData,
            ]
        );
        $previewData = [
            ...$previewData,
            'action_key' => $tool['key'],
            'confirmation_required' => (bool) $tool['requires_confirmation'],
            'confirmation_id' => $confirmation->id,
            'draft_id' => $previewData['draft_id'] ?? ($draft['draft_state']['draft_id'] ?? $confirmation->id),
            'revision' => $previewData['revision'] ?? ($draft['draft_state']['revision'] ?? null),
            'entity_type' => $tool['entity_type'],
            'expires_at' => $confirmation->expires_at?->toIso8601String(),
            'operation_type' => $tool['operation_type'],
        ];
        Log::info('ai.preview.created', [
            'action_key' => $tool['key'],
            'draft_id' => $previewData['draft_id'],
            'confirmation_id' => $confirmation->id,
            'revision' => $previewData['revision'],
            'correlation_id' => $context['correlation_id'] ?? null,
            'workspace_id' => $source['workspace_id'],
        ]);
        $editableMenu = is_array($draft['input'] ?? null) ? $draft['input'] : [];
        if (is_array($editableMenu['payload'] ?? null)) {
            $payload = $editableMenu['payload'];
            $payloadMetadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
            $editableMenu = [
                ...$payload,
                'requested_guest_count' => $payloadMetadata['requested_guest_count'] ?? null,
            ];
        }

        return [
            'blocks' => [
                [
                    'text' => 'Preparé un borrador seguro. Revisa el cambio antes de confirmarlo.',
                    'type' => 'text',
                ],
                [
                    'component' => 'action.preview',
                    'data' => $previewData,
                    'schema_version' => 1,
                    'type' => 'component',
                ],
                [
                    'component' => 'action.confirm',
                    'data' => [
                        'confirmation_token' => $token,
                        'draft_id' => $previewData['draft_id'],
                        'confirmation_id' => $confirmation->id,
                        'idempotency_key' => $confirmation->idempotency_key,
                        'description' => 'Esta acción se ejecutará solo después de tu confirmación explícita.',
                        'details' => $confirmationDetails,
                        'editable_menu' => $editableMenu,
                        'title' => 'Confirma esta acción',
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ],
            ],
            'confirmation' => [
                'confirmation_id' => $confirmation->id,
                'draft_id' => $previewData['draft_id'],
                'expires_at' => $confirmation->expires_at?->toIso8601String(),
                'id' => $confirmation->id,
                'status' => $confirmation->status,
                'token' => $token,
                'idempotency_key' => $confirmation->idempotency_key,
            ],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function createConfirmation(
        array $source,
        array $tool,
        array $context,
        array $payload,
        array $draft
    ): array {
        $token = Str::random(48);
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? Str::ulid());
        $confirmation = ActionConfirmation::query()->create([
            'workspace_id' => $source['workspace_id'],
            'message_id' => $source['message_id'],
            'ai_tool_call_id' => $context['ai_tool_call_id'] ?? null,
            'action_key' => $tool['key'],
            'token_hash' => hash('sha256', $token),
            'draft_json' => [
                ...$draft,
                'action_id' => $payload['action_id'] ?? $tool['action_id'],
                'component_instance_id' => $source['component_instance_id'],
                'entity_reference_alias' => is_array($payload['_entity_reference_alias'] ?? null)
                    ? $payload['_entity_reference_alias']
                    : null,
                'routing' => $context['routing'] ?? null,
                'orchestration_correlation_id' => $context['correlation_id'] ?? null,
                'source_component_key' => $source['component_key'],
            ],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => (string) Str::ulid(),
        ]);

        return [$token, $confirmation];
    }

    private function validateEntityPayload(array $entity, string $expectedType): array
    {
        return Validator::make($entity, [
            'id' => ['required', 'ulid'],
            'type' => ['required', Rule::in([$expectedType])],
            'version' => ['required', 'integer', 'min:1'],
        ])->validate();
    }

    private function normalizeDirectoryInput(
        string $type,
        string $operation,
        array $input,
        array $context
    ): array {
        if (in_array($operation, ['delete', 'cancel'], true)) {
            return [];
        }

        $fields = match ($type) {
            'event' => [
                'event_group_id', 'event_type', 'guest_count_confirmed', 'guest_count_expected',
                'name', 'notes', 'priority', 'service_type', 'starts_at', 'ends_at', 'status', 'timezone',
            ],
            'client' => [
                'address_line_1', 'address_line_2', 'city', 'company_name', 'country_code', 'email',
                'name', 'notes', 'phone', 'postal_code', 'state', 'status', 'tax_id', 'website',
            ],
            'contact' => [
                'client_id', 'contact_type', 'display_name', 'email', 'first_name', 'is_primary',
                'job_title', 'last_name', 'notes', 'phone',
            ],
            'venue' => [
                'access_instructions', 'address_line_1', 'address_line_2', 'capacity', 'city',
                'contact_email', 'contact_name', 'contact_phone', 'country_code', 'kitchen_notes',
                'latitude', 'loading_notes', 'longitude', 'name', 'notes', 'parking_notes', 'postal_code',
                'state', 'status', 'timezone',
            ],
            default => [],
        };

        $normalized = collect($fields)
            ->filter(fn (string $field): bool => array_key_exists($field, $input))
            ->mapWithKeys(fn (string $field): array => [$field => $input[$field]])
            ->all();

        if ($type === 'event') {
            foreach (['client', 'contact', 'venue'] as $relatedType) {
                $searchKey = $relatedType.'_search';
                if (!empty($input[$searchKey])) {
                    $normalized[$relatedType.'_id'] = $this->resolveRelatedId(
                        $context,
                        $relatedType,
                        (string) $input[$searchKey]
                    );
                }
            }
            if ($operation === 'create') {
                $normalized['timezone'] ??= $context['timezone'] ?? 'UTC';
                $normalized['status'] ??= 'draft';
                $normalized['priority'] ??= 'normal';
            }
        }

        if ($type === 'contact' && !empty($input['client_search'])) {
            $normalized['client_id'] = $this->resolveRelatedId($context, 'client', (string) $input['client_search']);
        }

        $normalized = $this->validateDirectoryInput($type, $normalized, (string) $context['workspace']->id);

        $missing = [];
        if ($operation === 'create') {
            $required = match ($type) {
                'event' => ['name', 'starts_at', 'timezone', 'status'],
                'client', 'venue' => ['name'],
                'contact' => ['first_name'],
                default => [],
            };
            foreach ($required as $field) {
                if (!array_key_exists($field, $normalized) || trim((string) $normalized[$field]) === '') {
                    $missing[] = $field;
                }
            }
        } elseif ($normalized === []) {
            $missing[] = 'change';
        }

        if ($missing !== []) {
            $normalized['_missing_fields'] = $missing;
        }

        return $normalized;
    }

    private function validateDirectoryInput(string $type, array $input, string $workspaceId): array
    {
        $rules = match ($type) {
            'event' => [
                'event_group_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('event_groups', 'id')->where('workspace_id', $workspaceId)],
                'client_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('clients', 'id')->where('workspace_id', $workspaceId)],
                'contact_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('contacts', 'id')->where('workspace_id', $workspaceId)],
                'venue_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('venues', 'id')->where('workspace_id', $workspaceId)],
                'name' => ['sometimes', 'string', 'max:255'],
                'starts_at' => ['sometimes', 'nullable', 'date'],
                'ends_at' => ['sometimes', 'nullable', 'date'],
                'timezone' => ['sometimes', 'timezone'],
                'guest_count_expected' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'guest_count_confirmed' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'status' => ['sometimes', 'in:draft,tentative,confirmed,in_production,completed,cancelled'],
                'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            ],
            'client' => [
                'name' => ['sometimes', 'string', 'max:180'], 'company_name' => ['sometimes', 'nullable', 'string', 'max:180'],
                'email' => ['sometimes', 'nullable', 'email', 'max:255'], 'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
                'website' => ['sometimes', 'nullable', 'url', 'max:2048'], 'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            ],
            'contact' => [
                'client_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('clients', 'id')->where('workspace_id', $workspaceId)],
                'first_name' => ['sometimes', 'string', 'max:100'], 'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
                'display_name' => ['sometimes', 'nullable', 'string', 'max:180'], 'email' => ['sometimes', 'nullable', 'email', 'max:255'],
                'phone' => ['sometimes', 'nullable', 'string', 'max:32'], 'is_primary' => ['sometimes', 'boolean'],
            ],
            'venue' => [
                'name' => ['sometimes', 'string', 'max:180'], 'city' => ['sometimes', 'nullable', 'string', 'max:120'],
                'country_code' => ['sometimes', 'nullable', 'string', 'size:2'], 'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
                'capacity' => ['sometimes', 'nullable', 'integer', 'min:0'], 'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'], 'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            ],
            default => [],
        };

        $validated = $rules === [] ? $input : Validator::make($input, $rules)->validate();
        if (
            $type === 'event'
            && !empty($validated['starts_at'])
            && !empty($validated['ends_at'])
            && strtotime((string) $validated['ends_at']) <= strtotime((string) $validated['starts_at'])
        ) {
            throw ValidationException::withMessages(['ends_at' => ['The event end must be after its start.']]);
        }

        if ($type === 'event' && !empty($validated['client_id']) && !empty($validated['contact_id'])) {
            $belongsToClient = Contact::query()
                ->where('workspace_id', $workspaceId)
                ->whereKey($validated['contact_id'])
                ->where(function ($query) use ($validated): void {
                    $query->whereNull('client_id')->orWhere('client_id', $validated['client_id']);
                })
                ->exists();
            if (!$belongsToClient) {
                throw ValidationException::withMessages(['contact_id' => ['The selected contact does not belong to the selected client.']]);
            }
        }

        return $validated;
    }

    private function resolveRelatedId(array $context, string $type, string $search): string
    {
        $resolution = $this->directoryEntityResolver->resolve(
            $context['workspace']->id,
            $type,
            null,
            $search,
            $context['entity_refs'] ?? []
        );

        if (($resolution['status'] ?? null) === 'resolved') {
            return (string) $resolution['entity']->id;
        }

        throw ValidationException::withMessages([
            $type => [($resolution['status'] ?? null) === 'ambiguous'
                ? 'The entity reference is ambiguous.'
                : 'The related entity was not found.'],
        ]);
    }

    private function authorizeDirectoryTool(array $tool, array $context, mixed $entity = null): void
    {
        $model = match ($tool['entity_type']) {
            'event' => Event::class,
            'client' => Client::class,
            'contact' => Contact::class,
            'venue' => Venue::class,
            default => null,
        };
        if ($model === null) {
            return;
        }

        $ability = $tool['operation_type'] === 'create'
            ? 'create'
            : ($tool['operation_type'] === 'delete' ? 'delete' : 'update');
        Gate::forUser($context['user'])->authorize($ability, $entity ?? $model);
    }

    private function loadDirectoryEntity(mixed $entity, string $type): mixed
    {
        if (!$entity) {
            return null;
        }

        return $entity->load(match ($type) {
            'event' => ['client.primaryContact', 'contact.client', 'group', 'venue'],
            'client' => ['contacts.client', 'primaryContact'],
            'contact' => ['client'],
            'venue' => [],
        });
    }

    private function directoryResource(mixed $entity, string $type): array
    {
        return match ($type) {
            'event' => (new EventResource($entity))->resolve(),
            'client' => (new ClientResource($entity))->resolve(),
            'contact' => (new ContactResource($entity))->resolve(),
            'venue' => (new VenueResource($entity))->resolve(),
        };
    }

    private function directoryEntityRef(mixed $entity, string $type, string $role): array
    {
        $label = $this->directoryEntityResolver->label($entity, $type);

        return [
            'id' => $entity->id,
            'role' => $role,
            'snapshot' => [
                'id' => $entity->id,
                'label' => $label,
                'name' => $label,
                'type' => $type,
                'version' => $entity->version ?? null,
            ],
            'type' => $type,
            'version' => $entity->version ?? null,
        ];
    }

    private function directoryResolutionResult(array $tool, array $context, array $resolution): array
    {
        $locale = (string) ($context['locale'] ?? 'en');
        if (($resolution['status'] ?? null) === 'system_failure') {
            return $this->semanticFallbackFailureResult($tool, $context);
        }
        if (($resolution['status'] ?? null) === 'clarification_required') {
            return $this->semanticFallbackClarificationResult($tool, $context, (string) ($tool['entity_type'] ?? 'record'));
        }
        if (($resolution['status'] ?? null) === 'ambiguous') {
            return [
                'blocks' => [[
                    'component' => 'clarification.options',
                    'data' => [
                        'description' => trans('chat.directory.choose_entity', [], $locale),
                        'options' => collect($resolution['matches'] ?? [])->map(fn ($entity) => [
                            'id' => $entity->id,
                            'label' => $this->directoryEntityResolver->label($entity, $tool['entity_type']),
                            'value' => $this->directoryEntityResolver->label($entity, $tool['entity_type']),
                        ])->values()->all(),
                        'selection_mode' => 'immediate',
                        'title' => trans('chat.directory.choose_entity_title', [], $locale),
                    ],
                    'schema_version' => 1,
                    'type' => 'component',
                ]],
                'entity_refs' => [],
                'suggestions' => [],
                'tool_keys' => [],
            ];
        }

        return [
            'status' => 'final_not_found',
            'blocks' => [[
                'component' => 'error.recovery',
                'data' => [
                    'description' => trans('chat.directory.entity_not_found', [], $locale),
                    'error_code' => 'ENTITY_NOT_FOUND',
                    'safe_detail' => trans('chat.directory.entity_not_found', [], $locale),
                    'title' => trans('chat.directory.entity_not_found_title', [], $locale),
                ],
                'schema_version' => 1,
                'type' => 'component',
            ]],
            'entity_refs' => [],
            'suggestions' => [],
            'tool_keys' => [],
        ];
    }

    private function directoryMissingFieldsResult(array $tool, array $context, array $fields): array
    {
        $locale = (string) ($context['locale'] ?? 'en');

        return [
            'blocks' => [[
                'text' => trans('chat.directory.missing_fields', ['fields' => implode(', ', $fields)], $locale),
                'type' => 'text',
            ]],
            'entity_refs' => [],
            'suggestions' => [],
            'tool_keys' => [],
        ];
    }

    private function directoryFieldLabel(string $field, string $locale): string
    {
        $label = trans('chat.directory.fields.'.$field, [], $locale);
        return $label === 'chat.directory.fields.'.$field
            ? Str::headline(str_replace('_', ' ', $field))
            : $label;
    }

    private function validateTaskInput(array $input, string $workspaceId): array
    {
        return Validator::make($input, [
            'blocked_reason' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'event_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('events', 'id')->where('workspace_id', $workspaceId)],
            'event_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'membership_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('workspace_memberships', 'id')->where(function ($query) use ($workspaceId): void {
                    $query
                        ->where('workspace_id', $workspaceId)
                        ->where('status', 'active');
                }),
            ],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:64'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'time_hour' => ['sometimes', 'integer', 'between:1,23'],
            'time_minute' => ['sometimes', 'integer', 'between:0,59'],
            'time_period' => ['sometimes', 'nullable', Rule::in(['am', 'pm'])],
            'team_id' => ['sometimes', 'nullable', 'ulid'],
            'station_id' => ['sometimes', 'nullable', 'ulid'],
            'team_search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'station_search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'member_search' => ['sometimes', 'nullable', 'string', 'max:150'],
        ])->validate();
    }

    private function validateTaskCreateInput(array $input): array
    {
        return Validator::make($input, [
            'blocked_reason' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'event_id' => ['sometimes', 'nullable', 'ulid'],
            'event_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:64'],
            'team_id' => ['sometimes', 'nullable', 'ulid'],
            'station_id' => ['sometimes', 'nullable', 'ulid'],
            'membership_id' => ['sometimes', 'nullable', 'ulid'],
            'team_search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'station_search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'member_search' => ['sometimes', 'nullable', 'string', 'max:150'],
        ])->validate();
    }

    private function validateTaskAssignmentInput(array $input, string $workspaceId): array
    {
        return Validator::make($input, [
            'from_member_search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'from_membership_id' => ['sometimes', 'nullable', 'ulid'],
            'member_search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'membership_id' => ['sometimes', 'nullable', 'ulid'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'task_ids' => ['sometimes', 'array', 'min:1'],
            'task_ids.*' => ['ulid'],
            'task_id' => ['sometimes', 'nullable', 'ulid'],
            'task_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'due_from' => ['sometimes', 'nullable', 'date'],
            'due_to' => ['sometimes', 'nullable', 'date'],
        ])->validate();
    }

    private function resolveTaskRelationships(array $context, array $input): array
    {
        if (!empty($input['event_id']) || !empty($input['event_search'])) {
            $resolution = $this->directoryEntityResolver->resolve(
                $context['workspace']->id,
                'event',
                $input['event_id'] ?? null,
                $input['event_search'] ?? null,
                $context['entity_refs'] ?? []
            );
            if (($resolution['status'] ?? null) !== 'resolved') {
                throw ValidationException::withMessages([
                    'event_id' => [($resolution['status'] ?? null) === 'ambiguous' ? 'The requested event is ambiguous.' : 'The requested event was not found.'],
                ]);
            }
            $input['event_id'] = $resolution['entity']->id;
        }

        foreach ([['team', 'team_id', 'team_search'], ['station', 'station_id', 'station_search'], ['membership', 'membership_id', 'member_search']] as [$type, $idKey, $searchKey]) {
            if (empty($input[$idKey]) && empty($input[$searchKey])) continue;
            if ($type === 'membership' && $this->isCurrentMemberReference($input[$searchKey] ?? null, $context)) {
                $input[$idKey] = $context['membership']->id;
                unset($input[$searchKey]);
                continue;
            }
            $resolution = $this->teamStaffEntityResolver->resolve($context['workspace']->id, $type, $input[$idKey] ?? null, $input[$searchKey] ?? null, $context['entity_refs'] ?? []);
            if (($resolution['status'] ?? null) !== 'resolved') {
                throw ValidationException::withMessages([$idKey => [($resolution['status'] ?? null) === 'ambiguous' ? 'The requested assignment is ambiguous.' : 'The requested assignment was not found.']]);
            }
            $input[$idKey] = $resolution['entity']->id;
        }
        unset($input['event_search'], $input['team_search'], $input['station_search'], $input['member_search']);
        if (!empty($input['membership_id'])) {
            $input['assignments'] = [['membership_id' => $input['membership_id'], 'is_primary' => true]];
        }
        return $input;
    }

    private function isCurrentMemberReference(mixed $reference, array $context): bool
    {
        if (!isset($context['membership']) || !is_object($context['membership'])) {
            return false;
        }

        return in_array(Str::lower(trim((string) $reference)), ['me', 'myself', 'yo', 'a mi', 'a ti', 'mí', 'mi'], true);
    }

    private function resolvePrepItemPayload(array $tool, array $context, array $payload): array
    {
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $entityPayload = is_array($payload['entity'] ?? null) ? $payload['entity'] : [];
        $resolution = $this->prepEntityResolver->resolveItem(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $entityPayload['id'] ?? $input['prep_item_id'] ?? null,
            $input['prep_item_search'] ?? null,
            $input['prep_list_id'] ?? null
        );
        if (($resolution['status'] ?? null) !== 'resolved') {
            return ['resolution' => [...$resolution, 'entity_type' => 'prep_item']];
        }

        $item = $resolution['item'];
        $entity = [
            'id' => $item->id,
            'type' => 'prep_item',
            'version' => (int) ($entityPayload['version'] ?? $input['version'] ?? $item->version),
        ];

        if (!empty($input['assignee_search']) || array_key_exists('assignment_membership_id', $input)) {
            $membershipResolution = $this->prepEntityResolver->resolveMembership(
                $context['workspace']->id,
                $context['entity_refs'] ?? [],
                $input['assignment_membership_id'] ?? null,
                $input['assignee_search'] ?? null
            );
            if (($membershipResolution['status'] ?? null) !== 'resolved') {
                return ['resolution' => [
                    'status' => $membershipResolution['status'] ?? 'missing',
                    'candidates' => $membershipResolution['candidates'] ?? [],
                    'entity_type' => 'membership',
                ]];
            }
            $input['assignment_membership_id'] = $membershipResolution['membership']->id;
        }

        if ($tool['key'] === 'prep.items.unassign') {
            $input['assignment_membership_id'] = null;
        } elseif ($tool['key'] === 'prep.items.complete') {
            $input['status'] = 'done';
        } elseif ($tool['key'] === 'prep.items.reopen') {
            $input['status'] = 'todo';
        } elseif ($tool['key'] === 'prep.items.assign' && empty($input['assignment_membership_id'])) {
            return ['resolution' => ['status' => 'missing']];
        }

        unset($input['prep_item_id'], $input['prep_item_search'], $input['prep_list_id'], $input['version'], $input['assignee_search']);

        return ['entity' => $entity, 'input' => $input];
    }

    private function validatePrepGenerationInput(array $input, string $workspaceId): array
    {
        return Validator::make($input, [
            'event_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('events', 'id')->where('workspace_id', $workspaceId)],
            'event_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'prep_list_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('prep_lists', 'id')->where('workspace_id', $workspaceId)],
            'prep_list_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'guest_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'menu_version_id' => ['sometimes', 'nullable', 'ulid'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'include_assignments' => ['sometimes', 'boolean'],
            'preserve_completed_items' => ['sometimes', 'boolean'],
            'preserve_assignments' => ['sometimes', 'boolean'],
            'assignment_membership_id' => ['sometimes', 'nullable', 'ulid', Rule::exists('workspace_memberships', 'id')->where(fn ($query) => $query->where('workspace_id', $workspaceId)->where('status', 'active'))],
            'notes' => ['sometimes', 'nullable', 'string'],
            'change_summary' => ['sometimes', 'nullable', 'string'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
        ])->validate();
    }

    private function validatePrepListInput(array $input): array
    {
        return Validator::make($input, [
            'prep_list_id' => ['sometimes', 'nullable', 'ulid'],
            'prep_list_search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'event_id' => ['sometimes', 'nullable', 'ulid'],
            'name' => ['sometimes', 'string', 'max:255'],
            'production_starts_at' => ['sometimes', 'nullable', 'date'],
            'production_ends_at' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'in_progress', 'review', 'approved', 'completed', 'cancelled'])],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ])->validate();
    }

    private function resolvePrepGenerationTarget(array $context, array $input): array
    {
        $eventResolution = $this->directoryEntityResolver->resolve(
            $context['workspace']->id,
            'event',
            $input['event_id'] ?? null,
            $input['event_search'] ?? null,
            $context['entity_refs'] ?? []
        );
        if (($eventResolution['status'] ?? null) !== 'resolved') {
            return [
                'status' => $eventResolution['status'] ?? 'missing',
                'entity_type' => 'event',
                'candidates' => collect($eventResolution['matches'] ?? [])->map(fn ($event): array => [
                    'id' => $event->id,
                    'name' => $event->name,
                ])->values()->all(),
            ];
        }

        $event = $eventResolution['entity'];
        $explicitList = filled($input['prep_list_id'] ?? null) || filled($input['prep_list_search'] ?? null);
        $listResolution = $this->prepEntityResolver->resolveList(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $input['prep_list_id'] ?? null,
            $input['prep_list_search'] ?? null,
            $event->id
        );
        if ($explicitList && ($listResolution['status'] ?? null) !== 'resolved') {
            return [...$listResolution, 'entity_type' => 'prep_list'];
        }
        if (($listResolution['status'] ?? null) === 'ambiguous') {
            return [...$listResolution, 'entity_type' => 'prep_list'];
        }

        return [
            'status' => 'resolved',
            'event' => $event,
            'prep_list' => $listResolution['prep_list'] ?? null,
        ];
    }

    private function generationAttributes(array $input, array $tool): array
    {
        return [
            ...collect([
                'guest_count', 'menu_version_id', 'due_at', 'include_assignments',
                'preserve_completed_items', 'preserve_assignments', 'assignment_membership_id',
                'notes', 'change_summary',
            ])->filter(fn (string $key): bool => array_key_exists($key, $input))
                ->mapWithKeys(fn (string $key): array => [$key => $input[$key]])->all(),
            'source' => $tool['key'] === 'prep.regenerate' ? 'regeneration' : 'manual',
        ];
    }

    private function validatePrepItemInput(array $input, string $workspaceId): array
    {
        return Validator::make($input, [
            'assignment_membership_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('workspace_memberships', 'id')->where(function ($query) use ($workspaceId): void {
                    $query
                        ->where('workspace_id', $workspaceId)
                        ->where('status', 'active');
                }),
            ],
            'blocked_reason' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'quantity' => ['sometimes', 'nullable', 'numeric'],
            'unit_id' => ['sometimes', 'nullable', 'ulid'],
            'portions' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'yield_quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'yield_unit_id' => ['sometimes', 'nullable', 'ulid'],
            'actual_quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'actual_unit_id' => ['sometimes', 'nullable', 'ulid'],
            'prep_section_id' => ['sometimes', 'nullable', 'ulid'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'skipped'])],
            'title' => ['sometimes', 'string', 'max:255'],
        ])->validate();
    }

    private function buildTaskChanges(Task $task, array $input, string $workspaceId): array
    {
        $changes = [];

        foreach ([
            'description' => 'Description',
            'type' => 'Type',
            'starts_at' => 'Starts at',
            'event_id' => 'Event',
            'blocked_reason' => 'Blocked reason',
        ] as $field => $label) {
            if (array_key_exists($field, $input) && (string) ($input[$field] ?? '') !== (string) ($task->{$field} ?? '')) {
                $changes[] = [
                    'after' => $input[$field] ?? 'None',
                    'before' => $task->{$field} ?? 'None',
                    'label' => $label,
                ];
            }
        }

        if (array_key_exists('title', $input) && $input['title'] !== $task->title) {
            $changes[] = [
                'after' => $input['title'],
                'before' => $task->title,
                'label' => 'Título',
            ];
        }

        if (array_key_exists('status', $input) && $input['status'] !== $task->status) {
            $changes[] = [
                'after' => $input['status'],
                'before' => $task->status,
                'label' => 'Estado',
            ];
        }

        if (array_key_exists('priority', $input) && $input['priority'] !== $task->priority) {
            $changes[] = [
                'after' => $input['priority'],
                'before' => $task->priority,
                'label' => 'Prioridad',
            ];
        }

        if (array_key_exists('due_at', $input)) {
            $currentValue = $task->due_at?->toIso8601String();
            $nextValue = $input['due_at'] ?? null;

            if ($currentValue !== $nextValue) {
                $changes[] = [
                    'after' => $nextValue ?? 'Sin fecha',
                    'before' => $currentValue ?? 'Sin fecha',
                    'label' => 'Fecha límite',
                ];
            }
        }

        foreach (['team_id' => 'Team', 'station_id' => 'Station'] as $field => $label) {
            if (array_key_exists($field, $input) && (string) ($task->{$field} ?? '') !== (string) ($input[$field] ?? '')) {
                $changes[] = ['after' => $input[$field] ?? 'None', 'before' => $task->{$field} ?? 'None', 'label' => $label];
            }
        }

        if (array_key_exists('membership_id', $input)) {
            $currentAssignment = $task->assignments->firstWhere('is_primary', true)
                ?? $task->assignments->first();
            $before = $currentAssignment?->membership?->user?->name ?? 'Sin asignar';
            $after = $this->resolveMembershipLabel($workspaceId, $input['membership_id'] ?? null);

            if ($before !== $after) {
                $changes[] = [
                    'after' => $after,
                    'before' => $before,
                    'label' => 'Responsable',
                ];
            }
        }

        return $changes;
    }

    private function buildPrepItemChanges(PrepItem $prepItem, array $input, string $workspaceId): array
    {
        $changes = [];

        foreach (['description', 'due_at', 'notes', 'starts_at', 'unit_id', 'portions', 'yield_quantity', 'yield_unit_id', 'actual_quantity', 'actual_unit_id', 'prep_section_id', 'blocked_reason'] as $field) {
            if (array_key_exists($field, $input) && (string) ($input[$field] ?? '') !== (string) ($prepItem->{$field} ?? '')) {
                $changes[] = [
                    'after' => (string) ($input[$field] ?? ''),
                    'before' => (string) ($prepItem->{$field} ?? ''),
                    'label' => $field,
                ];
            }
        }

        if (array_key_exists('title', $input) && $input['title'] !== $prepItem->title) {
            $changes[] = [
                'after' => $input['title'],
                'before' => $prepItem->title,
                'label' => 'Título',
            ];
        }

        if (array_key_exists('status', $input) && $input['status'] !== $prepItem->status) {
            $changes[] = [
                'after' => $input['status'],
                'before' => $prepItem->status,
                'label' => 'Estado',
            ];
        }

        if (array_key_exists('quantity', $input) && (string) ($input['quantity'] ?? '') !== (string) ($prepItem->quantity ?? '')) {
            $changes[] = [
                'after' => (string) ($input['quantity'] ?? 'Sin cantidad'),
                'before' => (string) ($prepItem->quantity ?? 'Sin cantidad'),
                'label' => 'Cantidad',
            ];
        }

        if (array_key_exists('priority', $input) && $input['priority'] !== $prepItem->priority) {
            $changes[] = [
                'after' => $input['priority'],
                'before' => $prepItem->priority,
                'label' => 'Prioridad',
            ];
        }

        if (array_key_exists('assignment_membership_id', $input)) {
            $currentAssignment = $prepItem->assignments->firstWhere('is_primary', true)
                ?? $prepItem->assignments->first();
            $before = $currentAssignment?->membership?->user?->name ?? 'Sin asignar';
            $after = $this->resolveMembershipLabel(
                $workspaceId,
                $input['assignment_membership_id'] ?? null
            );

            if ($before !== $after) {
                $changes[] = [
                    'after' => $after,
                    'before' => $before,
                    'label' => 'Responsable',
                ];
            }
        }

        return $changes;
    }

    private function mapTaskUpdateAttributes(array $input): array
    {
        $attributes = [];

        if (array_key_exists('title', $input)) {
            $attributes['title'] = $input['title'];
        }

        if (array_key_exists('status', $input)) {
            $attributes['status'] = $input['status'];
        }

        if (array_key_exists('priority', $input)) {
            $attributes['priority'] = $input['priority'];
        }

        if (array_key_exists('due_at', $input)) {
            $attributes['due_at'] = $input['due_at'];
        }

        foreach (['blocked_reason', 'description', 'event_id', 'starts_at', 'type'] as $field) {
            if (array_key_exists($field, $input)) $attributes[$field] = $input[$field];
        }

        foreach (['team_id', 'station_id'] as $field) {
            if (array_key_exists($field, $input)) $attributes[$field] = $input[$field];
        }

        if (array_key_exists('membership_id', $input)) {
            $attributes['assignments'] = $input['membership_id']
                ? [[
                    'is_primary' => true,
                    'membership_id' => $input['membership_id'],
                    'status' => 'assigned',
                ]]
                : [];
        }

        return $attributes;
    }

    private function mapPrepItemUpdateAttributes(array $input): array
    {
        $attributes = [];

        foreach ([
            'assignment_membership_id',
            'blocked_reason',
            'description',
            'due_at',
            'notes',
            'priority',
            'quantity',
            'unit_id',
            'portions',
            'yield_quantity',
            'yield_unit_id',
            'actual_quantity',
            'actual_unit_id',
            'prep_section_id',
            'starts_at',
            'status',
            'title',
        ] as $key) {
            if (array_key_exists($key, $input)) {
                $attributes[$key] = $input[$key];
            }
        }

        return $attributes;
    }

    private function loadTaskForTool(string $workspaceId, string $taskId): Task
    {
        return Task::query()
            ->whereKey($taskId)
            ->where('workspace_id', $workspaceId)
            ->with([
                'assignments.assignedBy',
                'assignments.membership.role',
                'assignments.membership.user',
                'completedBy',
                'createdBy',
                'event',
                'station.team',
                'team',
                'updatedBy',
            ])
            ->firstOrFail();
    }

    private function loadPrepItemForTool(string $workspaceId, string $prepItemId): PrepItem
    {
        return PrepItem::query()
            ->whereKey($prepItemId)
            ->where('workspace_id', $workspaceId)
            ->with([
                'assignments.assignedBy',
                'assignments.membership.role',
                'assignments.membership.teams',
                'assignments.membership.user',
                'actualUnit',
                'completedBy',
                'createdBy',
                'recipe',
                'recipeVersion',
                'unit',
                'updatedBy',
                'yieldUnit',
            ])
            ->firstOrFail();
    }

    private function resolveConfirmationSource(array $context): array
    {
        if (isset($context['source_block']) && $context['source_block'] instanceof MessageBlock) {
            return [
                'component_instance_id' => $context['source_block']->instance_id,
                'component_key' => $context['source_block']->component_key,
                'message_id' => $context['source_block']->message_id,
                'workspace_id' => $context['source_block']->workspace_id,
            ];
        }

        if (isset($context['source_message']) && $context['source_message'] instanceof Message) {
            return [
                'component_instance_id' => (string) Str::ulid(),
                'component_key' => 'assistant.message',
                'message_id' => $context['source_message']->id,
                'workspace_id' => $context['source_message']->workspace_id,
            ];
        }

        throw ValidationException::withMessages([
            'component_instance_id' => ['The source component block or assistant message is required.'],
        ]);
    }

    private function resolveMembershipLabel(string $workspaceId, ?string $membershipId): string
    {
        if (!$membershipId) {
            return 'Sin asignar';
        }

        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspaceId)
            ->with('user')
            ->find($membershipId);

        return $membership?->user?->name
            ?? $membership?->id
            ?? 'Sin asignar';
    }

    private function assertHasChanges(array $changes): void
    {
        if ($changes === []) {
            throw ValidationException::withMessages([
                'input' => ['The requested action does not introduce a meaningful change.'],
            ]);
        }
    }

    private function readSummaryText(string $toolKey, int $count, string $locale): string
    {
        return match ($toolKey) {
            'menus.search' => trans('chat.menu.search_summary', ['count' => $count], $locale),
            'menus.show' => $count > 0
                ? trans('chat.menu.show_summary', [], $locale)
                : trans('chat.menu.not_found', [], $locale),
            'recipes.list' => trans('chat.recipe.list_summary', ['count' => $count], $locale),
            'recipes.detail', 'recipes.versions' => trans('chat.recipe.detail_summary', ['name' => ''], $locale),
            'events.list' => "Encontré {$count} eventos para este contexto.",
            'prep.list' => "Encontré {$count} listas de prep para este contexto.",
            'tasks.mine' => trans('chat.tasks.mine_summary', ['count' => $count], $locale),
            'tasks.list', 'tasks.search' => trans('chat.tasks.list_summary', ['count' => $count], $locale),
            'documents.list' => trans('chat.capabilities.list_summary', ['count' => $count, 'entity' => trans('chat.capabilities.documents', [], $locale)], $locale),
            'beos.list' => trans('chat.capabilities.list_summary', ['count' => $count, 'entity' => trans('chat.capabilities.beos', [], $locale)], $locale),
            'clients.list' => trans('chat.directory.list_summary', ['count' => $count, 'entity' => trans('chat.directory.clients', [], $locale)], $locale),
            'contacts.list' => trans('chat.directory.list_summary', ['count' => $count, 'entity' => trans('chat.directory.contacts', [], $locale)], $locale),
            'venues.list' => trans('chat.directory.list_summary', ['count' => $count, 'entity' => trans('chat.directory.venues', [], $locale)], $locale),
            'teams.list', 'teams.detail' => trans('chat.team_staff.list_summary', ['count' => $count, 'entity' => trans('chat.team_staff.teams', [], $locale)], $locale),
            'stations.list', 'stations.detail' => trans('chat.team_staff.list_summary', ['count' => $count, 'entity' => trans('chat.team_staff.stations', [], $locale)], $locale),
            'shifts.list', 'shifts.detail' => trans('chat.team_staff.list_summary', ['count' => $count, 'entity' => trans('chat.team_staff.shifts', [], $locale)], $locale),
            'availability.list' => trans('chat.team_staff.list_summary', ['count' => $count, 'entity' => trans('chat.team_staff.availability', [], $locale)], $locale),
            default => "Encontré {$count} resultados.",
        };
    }

    private function readComponentPayload(string $toolKey, array $result, string $locale): array
    {
        $matchesDescription = trans('chat.capabilities.matches_description', [
            'count' => (int) ($result['count'] ?? 0),
        ], $locale);

        return match ($toolKey) {
            'menus.search' => [
                'description' => $matchesDescription,
                'menus' => $result['items'] ?? [],
                'title' => trans('chat.menu.search_title', [], $locale),
            ],
            'menus.show' => [
                'menu' => $result['items'][0] ?? null,
                'title' => trans('chat.menu.show_title', [], $locale),
            ],
            'recipes.list' => [
                'description' => $matchesDescription,
                'recipes' => $result['items'] ?? [],
                'title' => trans('chat.recipe.list_title', [], $locale),
            ],
            'events.list' => [
                'description' => $matchesDescription,
                'events' => $result['items'] ?? [],
                'title' => trans('chat.events.list_title', [], $locale),
            ],
            'prep.list' => [
                'description' => $matchesDescription,
                'items' => $result['items'] ?? [],
                'title' => 'Prep activa',
            ],
            'tasks.mine' => [
                'description' => $matchesDescription,
                'tasks' => $result['items'] ?? [],
                'title' => trans('chat.tasks.mine_title', [], $locale),
            ],
            'tasks.list', 'tasks.search' => ['description' => $matchesDescription, 'tasks' => $result['items'] ?? [], 'title' => trans('chat.tasks.list_title', [], $locale)],
            'documents.list', 'beos.list' => [
                'details' => [['label' => trans('chat.capabilities.records_label', [], $locale), 'value' => (string) ($result['count'] ?? 0)]],
                'description' => $matchesDescription,
                'entity_type' => $toolKey === 'documents.list' ? 'document' : 'beo',
                'items' => $result['items'] ?? [],
                'status' => 'success',
                'title' => trans('chat.capabilities.result_title', [], $locale),
            ],
            'clients.list' => [
                'description' => $matchesDescription,
                'entity_type' => 'client',
                'items' => $result['items'] ?? [],
                'title' => trans('chat.directory.clients', [], $locale),
            ],
            'contacts.list' => [
                'description' => $matchesDescription,
                'entity_type' => 'contact',
                'items' => $result['items'] ?? [],
                'title' => trans('chat.directory.contacts', [], $locale),
            ],
            'venues.list' => [
                'description' => $matchesDescription,
                'entity_type' => 'venue',
                'items' => $result['items'] ?? [],
                'title' => trans('chat.directory.venues', [], $locale),
            ],
            'teams.list', 'teams.detail' => ['description' => $matchesDescription, 'entity_type' => 'team', 'items' => $result['items'] ?? [], 'title' => trans('chat.team_staff.teams', [], $locale)],
            'stations.list', 'stations.detail' => ['description' => $matchesDescription, 'entity_type' => 'station', 'items' => $result['items'] ?? [], 'title' => trans('chat.team_staff.stations', [], $locale)],
            'shifts.list', 'shifts.detail' => ['description' => $matchesDescription, 'entity_type' => 'shift', 'items' => $result['items'] ?? [], 'title' => trans('chat.team_staff.shifts', [], $locale)],
            'availability.list' => ['description' => $matchesDescription, 'entity_type' => 'availability', 'items' => $result['items'] ?? [], 'title' => trans('chat.team_staff.availability', [], $locale)],
            default => [
                'items' => $result['items'] ?? [],
            ],
        };
    }

    private function genericEntityRefs(array $items, string $type): array
    {
        return collect($items)->map(fn (array $item, int $index): array => [
            'id' => $item['id'] ?? null,
            'ordinal' => $index + 1,
            'role' => $index === 0 ? 'active' : 'recent',
            'snapshot' => $item,
            'type' => $type,
        ])->filter(fn (array $ref): bool => filled($ref['id'] ?? null))->values()->all();
    }

    private function teamStaffEntityRefs(string $toolKey, array $items): array
    {
        $type = match (true) {
            str_starts_with($toolKey, 'teams.') => 'team',
            str_starts_with($toolKey, 'stations.') => 'station',
            str_starts_with($toolKey, 'shifts.') => 'shift',
            default => null,
        };
        if ($type === null) return [];
        return collect($items)->map(fn (array $item, int $index): array => [
            'id' => $item['id'] ?? null, 'ordinal' => $index + 1,
            'role' => $index === 0 ? 'active' : 'recent', 'snapshot' => $item, 'type' => $type,
        ])->filter(fn (array $ref): bool => filled($ref['id'] ?? null))->values()->all();
    }
}
