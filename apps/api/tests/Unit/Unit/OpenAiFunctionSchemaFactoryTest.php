<?php

namespace Tests\Unit\Unit;

use App\AI\Capabilities\OpenAiFunctionSchemaFactory;
use Tests\TestCase;

class OpenAiFunctionSchemaFactoryTest extends TestCase
{
    public function test_deferred_function_keeps_its_canonical_schema_and_marks_provider_loading(): void
    {
        $definition = (new OpenAiFunctionSchemaFactory)->make([
            'action_key' => 'recipes.list',
            'defer_loading' => true,
            'description' => 'Search recipes.',
            'input_schema' => ['fields' => ['search']],
        ]);

        $this->assertTrue($definition['defer_loading']);
        $this->assertSame('recipes_list', $definition['name']);
        $this->assertArrayHasKey('search', $definition['parameters']['properties']);
    }

    public function test_generic_tool_schemas_have_concrete_array_and_object_shapes(): void
    {
        $factory = new OpenAiFunctionSchemaFactory;

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
        $definition = (new OpenAiFunctionSchemaFactory)->make([
            'action_key' => 'notifications.read_all',
            'description' => 'Mark notifications as read.',
            'input_schema' => ['fields' => []],
        ]);

        $this->assertIsObject($definition['parameters']['properties']);
    }

    public function test_canonical_json_schema_properties_are_preserved_for_strict_tools(): void
    {
        $definition = (new OpenAiFunctionSchemaFactory)->make([
            'action_key' => 'tasks.create',
            'description' => 'Create a task.',
            'input_schema' => [
                'type' => 'object',
                'required' => ['title'],
                'properties' => [
                    'title' => ['type' => 'string'],
                    'starts_at' => ['type' => ['string', 'null']],
                    'duration_minutes' => ['type' => ['integer', 'null']],
                    'priority' => ['type' => 'string', 'enum' => ['normal', 'high']],
                ],
            ],
        ]);

        $parameters = $definition['parameters'];

        $this->assertSame(['title', 'starts_at', 'duration_minutes', 'priority'], $parameters['required']);
        $this->assertSame(['integer', 'null'], $parameters['properties']['duration_minutes']['type']);
        $this->assertSame(['string', 'null'], $parameters['properties']['starts_at']['type']);
        $this->assertContains(null, $parameters['properties']['priority']['enum']);
    }

    public function test_global_execution_plan_has_structured_steps_for_registered_actions(): void
    {
        $definition = (new OpenAiFunctionSchemaFactory)->make([
            'action_key' => 'execution_plans.create',
            'description' => 'Create several records.',
            'input_schema' => [],
        ]);

        $parameters = $definition['parameters'];
        $step = $parameters['properties']['steps']['items'];

        $this->assertFalse($definition['strict']);
        $this->assertSame(['title', 'objective', 'block_size', 'steps', 'completion_steps'], $parameters['required']);
        $this->assertFalse($step['additionalProperties']);
        $this->assertContains('action_key', $step['required']);
        $this->assertArrayNotHasKey('enum', $step['properties']['action_key']);
        $this->assertSame(120, $step['properties']['action_key']['maxLength']);
        $this->assertStringContainsString('registered write capability', $step['properties']['action_key']['description']);
        $this->assertTrue($step['properties']['input']['additionalProperties']);
        $this->assertSame('array', $parameters['properties']['completion_steps']['type']);
        $this->assertSame(
            ['step_key', 'action_key', 'label', 'input', 'after'],
            $parameters['properties']['completion_steps']['items']['required'],
        );
        $this->assertArrayHasKey('after', $step['properties']);
        $this->assertArrayNotHasKey('depends_on', $step['properties']);
        $this->assertArrayNotHasKey('input_bindings', $step['properties']);
    }

    public function test_execution_plan_action_keys_are_canonical_and_derived_from_the_authorized_catalog(): void
    {
        $definition = (new OpenAiFunctionSchemaFactory([
            ['key' => 'execution_plans.create', 'mode' => 'write', 'requires_confirmation' => true],
            ['key' => 'recipes.create', 'mode' => 'write', 'requires_confirmation' => true],
            ['key' => 'tasks.list', 'mode' => 'read', 'requires_confirmation' => false],
        ]))->make([
            'action_key' => 'execution_plans.create',
            'description' => 'Create several records.',
            'input_schema' => [],
        ]);

        $actionKey = $definition['parameters']['properties']['steps']['items']['properties']['action_key'];

        $this->assertSame(['recipes.create'], $actionKey['enum']);
        $this->assertSame('string', $actionKey['type']);
        $this->assertSame(
            ['tasks.list'],
            $definition['parameters']['properties']['completion_steps']['items']['properties']['action_key']['enum'],
        );
    }

    public function test_orchestration_response_has_an_explicit_terminal_contract(): void
    {
        $definition = (new OpenAiFunctionSchemaFactory)->make([
            'action_key' => 'orchestration.respond',
            'description' => 'End one tool loop.',
            'input_schema' => [],
        ]);

        $parameters = $definition['parameters'];

        $this->assertTrue($definition['strict']);
        $this->assertFalse($parameters['additionalProperties']);
        $this->assertSame(
            ['completed', 'clarification_required', 'waiting_confirmation', 'partial', 'nonrecoverable_error'],
            $parameters['properties']['status']['enum'],
        );
        $this->assertSame(
            ['status', 'message', 'blocks', 'continuation', 'suggestions', 'reason', 'missing_fields', 'remaining_operations'],
            $parameters['required'],
        );
    }

    public function test_execution_plan_revision_has_structured_existing_item_inputs(): void
    {
        $definition = (new OpenAiFunctionSchemaFactory)->make([
            'action_key' => 'execution_plans.revise',
            'description' => 'Repair pending work.',
            'input_schema' => [],
        ]);

        $parameters = $definition['parameters'];
        $item = $parameters['properties']['items']['items'];

        $this->assertFalse($definition['strict']);
        $this->assertSame(['execution_plan_id', 'items'], $parameters['required']);
        $this->assertSame(['item_id', 'input'], $item['required']);
        $this->assertTrue($item['properties']['input']['additionalProperties']);
    }
}
