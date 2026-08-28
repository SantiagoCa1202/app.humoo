<?php

namespace Tests\Unit\Unit;

use App\AI\Capabilities\OpenAiFunctionSchemaFactory;
use Tests\TestCase;

class OpenAiFunctionSchemaFactoryTest extends TestCase
{
    public function test_generic_tool_schemas_have_concrete_array_and_object_shapes(): void
    {
        $factory = new OpenAiFunctionSchemaFactory();

        $definition = $factory->make([
            'action_key' => 'events.list',
            'description' => 'List events.',
            'input_schema' => [
                'fields' => ['event_id', 'limit', 'active_only', 'records', 'menu_draft'],
            ],
        ]);

        $parameters = $definition['parameters'];

        $this->assertSame(['string', 'null'], $parameters['properties']['event_id']['type']);
        $this->assertSame(['integer', 'null'], $parameters['properties']['limit']['type']);
        $this->assertSame(['boolean', 'null'], $parameters['properties']['active_only']['type']);
        $this->assertSame(['array', 'null'], $parameters['properties']['records']['type']);
        $this->assertArrayHasKey('items', $parameters['properties']['records']);
        $this->assertSame(['object', 'null'], $parameters['properties']['menu_draft']['type']);
        $this->assertFalse($parameters['properties']['menu_draft']['additionalProperties']);
    }

    public function test_empty_generic_tool_schema_uses_an_object_for_empty_properties(): void
    {
        $definition = (new OpenAiFunctionSchemaFactory())->make([
            'action_key' => 'notifications.read_all',
            'description' => 'Mark notifications as read.',
            'input_schema' => ['fields' => []],
        ]);

        $this->assertIsObject($definition['parameters']['properties']);
    }
}
