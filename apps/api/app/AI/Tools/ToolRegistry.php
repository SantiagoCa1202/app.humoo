<?php

namespace App\AI\Tools;

use Illuminate\Validation\ValidationException;

class ToolRegistry
{
    private const ACTION_ALIASES = [
        'show_events' => 'events.list',
        'show_my_tasks' => 'tasks.mine',
        'show_prep_lists' => 'prep.list',
        'update_prep_item' => 'prep_items.update',
        'update_task' => 'tasks.update',
        'create_menu' => 'menus.create',
    ];

    private const TOOLS = [
        'events.list' => [
            'action_id' => 'events.list',
            'component' => 'events.list',
            'description' => 'List workspace events using safe server filters.',
            'entity_type' => 'event',
            'mode' => 'read',
            'permission' => 'events.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep.list' => [
            'action_id' => 'prep.list',
            'component' => 'prep.list',
            'description' => 'List active prep lists for the current workspace.',
            'entity_type' => 'prep_list',
            'mode' => 'read',
            'permission' => 'prep_lists.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'tasks.mine' => [
            'action_id' => 'tasks.mine',
            'component' => 'tasks.mine',
            'description' => 'List open tasks assigned to the current membership.',
            'entity_type' => 'task',
            'mode' => 'read',
            'permission' => 'tasks.view',
            'requires_confirmation' => false,
            'schema_version' => 1,
        ],
        'prep_items.update' => [
            'action_id' => 'update_prep_item',
            'component' => 'action.preview',
            'description' => 'Prepare a safe preview to update a prep item.',
            'entity_type' => 'prep_item',
            'mode' => 'write',
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
            'mode' => 'write',
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
            'mode' => 'write',
            'permission' => 'menus.create',
            'requires_confirmation' => true,
            'result_component' => 'action.result',
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

        return [
            'key' => $normalized,
            ...self::TOOLS[$normalized],
        ];
    }

    public function metadata(array $tool): array
    {
        return [
            'action_id' => $tool['action_id'],
            'component' => $tool['component'],
            'description' => $tool['description'],
            'entity_type' => $tool['entity_type'],
            'key' => $tool['key'],
            'mode' => $tool['mode'],
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
