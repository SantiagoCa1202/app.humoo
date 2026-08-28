<?php

namespace Tests\Unit\Unit;

use App\AI\Capabilities\CapabilityRegistry;
use Tests\TestCase;

class CapabilityRegistryContractTest extends TestCase
{
    public function test_registry_exposes_a_stable_versioned_canonical_contract(): void
    {
        $registry = new CapabilityRegistry();

        $this->assertSame('capabilities-v1', $registry->registryVersion());
        $this->assertSame($registry->registryHash(), $registry->registryHash());

        $definitions = $registry->definitions();
        $actionKeys = array_column($definitions, 'action_key');
        $this->assertSame($actionKeys, array_values(array_unique($actionKeys)));

        foreach ($definitions as $definition) {
            $this->assertSame($definition['action_key'], $definition['action_id']);
            $this->assertNotEmpty($definition['input_schema']);
            $this->assertNotEmpty($definition['output_schema']);
            $this->assertNotEmpty($definition['permission']);
            $this->assertNotEmpty($definition['executor']);
            $this->assertNotEmpty($definition['payload_extractor']);
            $this->assertArrayHasKey('risk', $definition['policy']);
            $this->assertArrayHasKey('confirmation_required', $definition['policy']);
        }
    }

    public function test_legacy_action_ids_are_internal_aliases_of_canonical_keys(): void
    {
        $registry = new CapabilityRegistry();

        $this->assertSame('tasks.create', $registry->resolve('create_task')['key']);
        $this->assertSame('tasks.update', $registry->resolve('update_task')['key']);
        $this->assertSame('prep.items.update', $registry->resolve('update_prep_item')['key']);
    }

    public function test_create_contracts_describe_recipe_and_menu_drafts(): void
    {
        $registry = new CapabilityRegistry();

        $recipe = $registry->definition('recipes.create');
        $menu = $registry->definition('menus.create');

        $this->assertContains('recipe_draft.ingredients', $recipe['input_schema']['fields']);
        $this->assertContains('recipe_draft.steps', $recipe['input_schema']['fields']);
        $this->assertContains('menu_draft.sections.*.items.*.recipe_reference', $menu['input_schema']['fields']);
    }

    public function test_recipe_create_function_schema_is_strict_and_capability_specific(): void
    {
        $function = (new CapabilityRegistry())->functionDefinition('recipes.create');

        $this->assertSame('function', $function['type']);
        $this->assertSame('recipes_create', $function['name']);
        $this->assertTrue($function['strict']);
        $this->assertSame('object', $function['parameters']['type']);
        $this->assertFalse($function['parameters']['additionalProperties']);
        $this->assertSame(
            ['name', 'description', 'yield', 'ingredients', 'steps', 'source'],
            $function['parameters']['required']
        );
        $this->assertArrayNotHasKey('event_id', $function['parameters']['properties']);
        $this->assertContains('portion', $function['parameters']['properties']['yield']['properties']['unit_key']['enum']);
    }
}
