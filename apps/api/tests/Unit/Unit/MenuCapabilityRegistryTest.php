<?php

namespace Tests\Unit\Unit;

use App\AI\Tools\ToolRegistry;
use Tests\TestCase;

class MenuCapabilityRegistryTest extends TestCase
{
    public function test_menu_read_capabilities_are_not_mapped_to_create(): void
    {
        $registry = app(ToolRegistry::class);

        $show = $registry->resolve('menus.show');
        $create = $registry->resolve('menus.create');
        $move = $registry->resolve('menus.items.move_section');

        $this->assertSame('read', $show['mode']);
        $this->assertFalse($show['requires_confirmation']);
        $this->assertSame('write', $create['mode']);
        $this->assertTrue($create['requires_confirmation']);
        $this->assertSame('action', $move['mode']);
        $this->assertFalse($move['requires_confirmation']);
    }

    public function test_menu_capability_metadata_exposes_contract_fields(): void
    {
        $metadata = app(ToolRegistry::class)->metadata([
            'key' => 'menus.show',
            ...app(ToolRegistry::class)->resolve('menus.show'),
        ]);

        foreach ([
            'key',
            'module',
            'description',
            'operation_type',
            'input_schema',
            'output_schema',
            'permission',
            'confirmation_policy',
            'context_requirements',
        ] as $field) {
            $this->assertArrayHasKey($field, $metadata);
        }
    }

    public function test_detail_capabilities_expose_search_and_stable_identifier_fields(): void
    {
        $registry = app(ToolRegistry::class);

        foreach ([
            ['menus.show', ['menu_id', 'menu_search']],
            ['recipes.detail', ['recipe_id', 'recipe_search']],
            ['events.detail', ['entity_id', 'entity_search']],
            ['clients.detail', ['entity_id', 'entity_search']],
            ['contacts.detail', ['entity_id', 'entity_search']],
            ['venues.detail', ['entity_id', 'entity_search']],
            ['tasks.detail', ['task_id', 'task_search']],
            ['documents.detail', ['document_id', 'document_search']],
            ['beos.detail', ['beo_id', 'beo_search']],
            ['prep.detail', ['prep_list_id', 'prep_list_search']],
            ['teams.detail', ['team_id', 'team_search']],
            ['stations.detail', ['station_id', 'station_search']],
            ['shifts.detail', ['shift_id', 'shift_search']],
        ] as [$action, $fields]) {
            $metadata = $registry->metadata([
                'key' => $action,
                ...$registry->resolve($action),
            ]);

            foreach ($fields as $field) {
                $this->assertContains($field, $metadata['input_schema']['fields'], $action);
            }
        }
    }
}
