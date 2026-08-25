<?php

namespace App\AI\Tools;

use App\AI\Policy\ActionPolicy;
use Illuminate\Validation\ValidationException;

class ToolRegistry
{
    private ActionPolicy $actionPolicy;

    public function __construct(?ActionPolicy $actionPolicy = null)
    {
        $this->actionPolicy = $actionPolicy ?? new ActionPolicy();
    }

    private const ACTION_ALIASES = [
        'show_events' => 'events.list',
        'show_my_tasks' => 'tasks.mine',
        'show_prep_lists' => 'prep.list',
        'update_prep_item' => 'prep_items.update',
        'update_task' => 'tasks.update',
        'create_menu' => 'menus.create',
        'search_menus' => 'menus.search',
        'show_menu' => 'menus.show',
        'rename_menu' => 'menus.rename',
        'add_menu_item' => 'menus.items.add',
        'move_menu_item_section' => 'menus.items.move_section',
    ];

    private const TOOLS = [
        'events.list' => [
            'action_id' => 'events.list',
            'component' => 'events.list',
            'description' => 'List workspace events using safe server filters.',
            'entity_type' => 'event',
            'module' => 'events',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'events.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep.list' => [
            'action_id' => 'prep.list',
            'component' => 'prep.list',
            'description' => 'List active prep lists for the current workspace.',
            'entity_type' => 'prep_list',
            'module' => 'prep',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'prep_lists.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'tasks.mine' => [
            'action_id' => 'tasks.mine',
            'component' => 'tasks.mine',
            'description' => 'List open tasks assigned to the current membership.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'tasks.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep_items.update' => [
            'action_id' => 'update_prep_item',
            'component' => 'action.preview',
            'description' => 'Prepare a safe preview to update a prep item.',
            'entity_type' => 'prep_item',
            'module' => 'prep',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'prep_lists.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'tasks.update' => [
            'action_id' => 'update_task',
            'component' => 'action.preview',
            'description' => 'Prepare a safe preview to update a task.',
            'entity_type' => 'task',
            'module' => 'tasks',
            'mode' => 'write',
            'operation_type' => 'update',
            'permission' => 'tasks.edit',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'menus.create' => [
            'action_id' => 'menus.create',
            'component' => 'action.preview',
            'description' => 'Prepare a menu draft from chat content and create it after explicit confirmation.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'write',
            'operation_type' => 'create',
            'permission' => 'menus.create',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
            'schema_version' => 1,
        ],
        'menus.search' => [
            'action_id' => 'menus.search',
            'component' => 'menus.list',
            'description' => 'Search menus in the active workspace by name or description.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'menus.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'menus.show' => [
            'action_id' => 'menus.show',
            'component' => 'menus.detail',
            'description' => 'Show one menu and its current sections and items. Never create or modify a menu.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'read',
            'operation_type' => 'read',
            'permission' => 'menus.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'menus.rename' => [
            'action_id' => 'menus.rename',
            'component' => 'action.result',
            'description' => 'Rename the resolved menu in the active workspace.',
            'entity_type' => 'menu',
            'module' => 'menus',
            'mode' => 'action',
            'operation_type' => 'update',
            'permission' => 'menus.edit',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'menus.items.add' => [
            'action_id' => 'menus.items.add',
            'component' => 'action.result',
            'description' => 'Add one named item to a resolved menu section.',
            'entity_type' => 'menu_item',
            'module' => 'menus',
            'mode' => 'action',
            'operation_type' => 'create',
            'permission' => 'menus.edit',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'menus.items.move_section' => [
            'action_id' => 'menus.items.move_section',
            'component' => 'action.result',
            'description' => 'Move one existing menu item to another section without creating a new menu.',
            'entity_type' => 'menu_item',
            'module' => 'menus',
            'mode' => 'action',
            'operation_type' => 'update',
            'permission' => 'menus.edit',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
    ];

    public function resolve(string $actionId): array
    {
        $normalized = self::ACTION_ALIASES[$actionId] ?? $actionId;

        if (!array_key_exists($normalized, self::TOOLS)) {
            throw ValidationException::withMessages([
                'action_id' => ['The selected action is not registered.'],
            ]);
        }

        $tool = self::TOOLS[$normalized];
        $policy = $this->actionPolicy->resolve($normalized);

        return [
            'key' => $normalized,
            'policy' => $policy,
            ...$tool,
            'requires_confirmation' => (bool) ($tool['requires_confirmation'] || $policy['confirmation_required']),
        ];
    }

    public function actionKeyForIntent(string $intent): ?string
    {
        $normalized = self::ACTION_ALIASES[$intent] ?? $intent;

        return array_key_exists($normalized, self::TOOLS) ? $normalized : null;
    }

    public function metadata(array $tool): array
    {
        return [
            'action_id' => $tool['action_id'],
            'component' => $tool['component'],
            'confirmation_policy' => $tool['requires_confirmation']
                ? 'explicit_confirmation'
                : 'none',
            'context_requirements' => in_array($tool['entity_type'], ['menu', 'menu_item'], true)
                ? ['active_menu_or_menu_search']
                : [],
            'description' => $tool['description'],
            'entity_type' => $tool['entity_type'],
            'key' => $tool['key'],
            'input_schema' => $tool['input_schema'] ?? [],
            'module' => $tool['module'] ?? null,
            'mode' => $tool['mode'],
            'operation_type' => $tool['operation_type'] ?? $tool['mode'],
            'policy' => $tool['policy'] ?? $this->actionPolicy->resolve($tool['key']),
            'output_schema' => $tool['output_schema'] ?? ['component' => $tool['component'].'@'.$tool['schema_version']],
            'permission' => $tool['permission'],
            'requires_confirmation' => $tool['requires_confirmation'],
            'schema_version' => $tool['schema_version'],
        ];
    }

    public function allMetadata(): array
    {
        return collect(self::TOOLS)
            ->map(fn (array $tool, string $key) => $this->metadata([
                'key' => $key,
                ...$tool,
            ]))
            ->values()
            ->all();
    }
}
