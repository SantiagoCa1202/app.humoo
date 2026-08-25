<?php

namespace App\AI\Tools;

use App\AI\EntityResolution\MenuEntityResolver;
use App\AI\EntityResolution\DirectoryEntityResolver;
use App\AI\EntityResolution\RecipeEntityResolver;
use App\AI\EntityResolution\PrepEntityResolver;
use App\Application\Actions\ChatTools\ListDirectoryEntitiesForTool;
use App\Application\Actions\Menus\CreateMenu;
use App\Application\Actions\Menus\UpdateMenuFromChat;
use App\Application\Actions\ChatTools\ListEventsForTool;
use App\Application\Actions\ChatTools\ListMenusForTool;
use App\Application\Actions\ChatTools\ListRecipesForTool;
use App\Application\Actions\ChatTools\ListMyTasksForTool;
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
use App\Http\Resources\ClientResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PrepItemResource;
use App\Http\Resources\PrepListResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RecipeVersionResource;
use App\Http\Resources\MenuResource;
use App\Http\Resources\TaskResource;
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
use App\Models\Venue;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToolExecutor
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private ListEventsForTool $listEventsForTool,
        private ListMenusForTool $listMenusForTool,
        private ListPrepListsForTool $listPrepListsForTool,
        private ListPrepItemsForTool $listPrepItemsForTool,
        private ListMyTasksForTool $listMyTasksForTool,
        private CreatePrepList $createPrepList,
        private GeneratePrepList $generatePrepList,
        private UpdatePrepList $updatePrepList,
        private UpdatePrepItem $updatePrepItem,
        private CreateTask $createTask,
        private UpdateTask $updateTask,
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
        private ScaleRecipe $scaleRecipe
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
            'tasks.update' => $this->executeTaskUpdate($tool, $context, $draft),
            'menus.create' => $this->executeMenuCreate($tool, $context, $draft),
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

        if (in_array($tool['key'], ['recipes.detail', 'recipes.versions', 'recipes.scale'], true)) {
            return $this->executeRecipeRead($tool, $context, $filters);
        }

        if (in_array($tool['key'], ['events.detail', 'clients.detail', 'contacts.detail', 'venues.detail'], true)) {
            return $this->executeDirectoryDetailRead($tool, $context, $filters);
        }

        if (in_array($tool['key'], ['prep.detail', 'prep.items.list', 'prep.items.detail'], true)) {
            return $this->executePrepRead($tool, $context, $filters);
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
            return $this->directoryResolutionResult($tool, $context, $resolution);
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

    private function executeRecipeRead(array $tool, array $context, array $input): array
    {
        $resolution = $this->recipeEntityResolver->resolve(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $input['recipe_id'] ?? null,
            $input['recipe_search'] ?? null,
            $input['recipe_version_id'] ?? null
        );

        if (($resolution['status'] ?? null) !== 'resolved') {
            return $this->recipeResolutionResult($tool, $context, $resolution);
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
                    'title' => trans('chat.recipe.'.$tool['key'].'.title', [], $context['locale']),
                ], 'schema_version' => 1, 'type' => 'component'],
            ],
            'entity_refs' => [$this->recipeEntityRef($recipe, 'active')],
            'result_ref_json' => ['count' => count($items), 'items' => $items],
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
                return $this->prepResolutionResult($tool, $context, $resolution, 'item');
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
                return $this->prepResolutionResult($tool, $context, [
                    'status' => $eventResolution['status'] ?? 'missing',
                    'candidates' => collect($eventResolution['matches'] ?? [])->map(fn ($event): array => ['id' => $event->id, 'name' => $event->name])->values()->all(),
                ], 'event');
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
            return $this->prepResolutionResult($tool, $context, $resolution, 'list');
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
        $locale = (string) ($context['locale'] ?? 'en');
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.prep.ambiguous', ['entity' => $entity], $locale)
            : trans('chat.prep.not_found', ['entity' => $entity], $locale);

        return [
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
        $candidates = $resolution['candidates'] ?? [];
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.recipe.ambiguous', [], $context['locale'])
            : trans('chat.recipe.not_found', [], $context['locale']);
        return [
            'blocks' => [['text' => $text, 'type' => 'text']],
            'entity_refs' => [],
            'result_ref_json' => ['candidates' => $candidates],
            'tool' => $this->toolRegistry->metadata($tool),
        ];
    }

    private function menuResolutionResult(array $tool, array $context, array $resolution, string $entity = 'menu'): array
    {
        $text = ($resolution['status'] ?? null) === 'ambiguous'
            ? trans('chat.menu.ambiguous', ['entity' => $entity], $context['locale'])
            : trans('chat.menu.not_found', [], $context['locale']);
        return [
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
        $menuResolution = $this->menuEntityResolver->resolveMenu(
            $context['workspace']->id,
            $context['entity_refs'] ?? [],
            $input['menu_id'] ?? null,
            $input['menu_search'] ?? null
        );

        if (($menuResolution['status'] ?? null) === 'ambiguous') {
            throw ValidationException::withMessages([
                'menu' => ['The menu reference is ambiguous.'],
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
            'tasks.update' => $this->previewTaskUpdate($tool, $context, $payload, $source),
            'menus.create' => $this->previewMenuCreate($tool, $context, $payload, $source),
            'menus.update', 'menus.items.update', 'menus.items.delete' => $this->previewMenuWrite($tool, $context, $payload, $source),
            'recipes.create', 'recipes.update' => $this->previewRecipeWrite($tool, $context, $payload, $source),
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
            $resolution = $this->recipeEntityResolver->resolve($context['workspace']->id, $context['entity_refs'] ?? [], $input['recipe_id'] ?? null, $input['recipe_search'] ?? null);
            if (($resolution['status'] ?? null) !== 'resolved') {
                return $this->recipeResolutionResult($tool, $context, $resolution);
            }
            $draft['recipe_id'] = $resolution['recipe']->id;
            $draft['current_version_id'] = $resolution['version']?->id;
            $draft['expected_revision'] = $resolution['version']?->revision;
            Gate::forUser($context['user'])->authorize('update', $resolution['recipe']);
        } else {
            Gate::forUser($context['user'])->authorize('create', Recipe::class);
        }
        $normalized = $this->validateRecipeInput($draft, $tool['key'] === 'recipes.update');
        return $this->buildConfirmationPreview(
            $tool,
            $source,
            $context,
            $payload,
            [
                'action' => $normalized['name'],
                'changes' => [['label' => trans('chat.recipe.name_label', [], $context['locale']), 'after' => $normalized['name']]],
                'description' => trans('chat.recipe.write_preview_description', [], $context['locale']),
                'metadata' => [['label' => trans('chat.recipe.name_label', [], $context['locale']), 'value' => $normalized['name']]],
                'title' => trans('chat.recipe.write_preview_title', [], $context['locale']),
                'type' => trans('chat.recipe.write_preview_type', [], $context['locale']),
            ],
            [['label' => trans('chat.recipe.name_label', [], $context['locale']), 'value' => $normalized['name']]],
            ['entity' => $tool['key'] === 'recipes.update' ? ['id' => $normalized['recipe_id'], 'type' => 'recipe', 'version' => $normalized['expected_revision']] : null, 'input' => $normalized, 'tool_key' => $tool['key']]
        );
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
            'version.yields' => ['required', 'array', 'min:1'],
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

    private function completedActionResult(array $tool, array $context, array $resource, string $label): array
    {
        return [
            'blocks' => [
                ['text' => trans('chat.action.completed', [], $context['locale']), 'type' => 'text'],
                ['component' => $tool['result_component'] ?? 'action.result', 'data' => [
                    'description' => trans('chat.action.completed_description', [], $context['locale']),
                    'details' => [['label' => trans('chat.action.record_label', [], $context['locale']), 'value' => $label]],
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
        $entity = $this->validateEntityPayload(
            is_array($payload['entity'] ?? null) ? $payload['entity'] : [],
            'task'
        );
        $input = $this->validateTaskInput(
            is_array($payload['input'] ?? null) ? $payload['input'] : [],
            $workspaceId
        );
        $task = $this->loadTaskForTool($workspaceId, $entity['id']);

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

    private function previewPrepItemUpdate(
        array $tool,
        array $context,
        array $payload,
        array $source
    ): array {
        $workspaceId = $context['workspace']->id;
        $resolved = $this->resolvePrepItemPayload($tool, $context, $payload);
        if (isset($resolved['resolution'])) {
            return $this->prepResolutionResult($tool, $context, $resolved['resolution'], 'item');
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
            return $this->prepResolutionResult($tool, $context, $target, 'event or prep list');
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
            return $this->prepResolutionResult($tool, $context, $resolution, 'list');
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
                'expires_at' => $confirmation->expires_at?->toIso8601String(),
                'id' => $confirmation->id,
                'status' => $confirmation->status,
                'token' => $token,
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
                'routing' => $context['routing'] ?? null,
                'source_component_key' => $source['component_key'],
            ],
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'idempotency_key' => $payload['idempotency_key'] ?? null,
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
            'due_at' => ['sometimes', 'nullable', 'date'],
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
        ])->validate();
    }

    private function validateTaskCreateInput(array $input): array
    {
        return Validator::make($input, [
            'description' => ['sometimes', 'nullable', 'string'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'title' => ['required', 'string', 'max:255'],
        ])->validate();
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
            return ['resolution' => $resolution];
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
            return $listResolution;
        }
        if (($listResolution['status'] ?? null) === 'ambiguous') {
            return $listResolution;
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
            'tasks.mine' => "Encontré {$count} tareas abiertas asignadas a tu membresía.",
            'clients.list' => trans('chat.directory.list_summary', ['count' => $count, 'entity' => trans('chat.directory.clients', [], $locale)], $locale),
            'contacts.list' => trans('chat.directory.list_summary', ['count' => $count, 'entity' => trans('chat.directory.contacts', [], $locale)], $locale),
            'venues.list' => trans('chat.directory.list_summary', ['count' => $count, 'entity' => trans('chat.directory.venues', [], $locale)], $locale),
            default => "Encontré {$count} resultados.",
        };
    }

    private function readComponentPayload(string $toolKey, array $result, string $locale): array
    {
        return match ($toolKey) {
            'menus.search' => [
                'menus' => $result['items'] ?? [],
                'title' => trans('chat.menu.search_title', [], $locale),
            ],
            'menus.show' => [
                'menu' => $result['items'][0] ?? null,
                'title' => trans('chat.menu.show_title', [], $locale),
            ],
            'recipes.list' => [
                'recipes' => $result['items'] ?? [],
                'title' => trans('chat.recipe.list_title', [], $locale),
            ],
            'events.list' => [
                'events' => $result['items'] ?? [],
                'title' => trans('chat.events.list_title', [], $locale),
            ],
            'prep.list' => [
                'items' => $result['items'] ?? [],
                'title' => 'Prep activa',
            ],
            'tasks.mine' => [
                'tasks' => $result['items'] ?? [],
                'title' => 'Tus tareas abiertas',
            ],
            'clients.list' => [
                'items' => $result['items'] ?? [],
                'title' => trans('chat.directory.clients', [], $locale),
            ],
            'contacts.list' => [
                'items' => $result['items'] ?? [],
                'title' => trans('chat.directory.contacts', [], $locale),
            ],
            'venues.list' => [
                'items' => $result['items'] ?? [],
                'title' => trans('chat.directory.venues', [], $locale),
            ],
            default => [
                'items' => $result['items'] ?? [],
            ],
        };
    }
}
