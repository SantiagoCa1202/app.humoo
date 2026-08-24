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
}
