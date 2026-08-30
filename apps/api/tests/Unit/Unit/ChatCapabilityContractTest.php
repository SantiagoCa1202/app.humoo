<?php

namespace Tests\Unit\Unit;

use App\AI\Presentation\ComponentRegistry;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
use App\AI\Tools\ToolProfileSelector;
use Tests\TestCase;

class ChatCapabilityContractTest extends TestCase
{
    public function test_every_registered_capability_has_policy_executable_handler_and_supported_component(): void
    {
        $registry = new ToolRegistry();

        foreach ($registry->allMetadata() as $metadata) {
            $tool = $registry->resolve($metadata['key']);
            $this->assertSame($metadata['key'], $tool['policy']['action_key']);
            $this->assertNotEmpty($metadata['input_schema']);
            $this->assertTrue(ToolExecutor::supportsAction($registry, $metadata['key']));
            $this->assertTrue(ComponentRegistry::supports($metadata['output_schema']['component']));
        }
    }

    public function test_backend_component_keys_are_registered_in_the_client_type_and_remote_registry(): void
    {
        $types = file_get_contents(base_path('../client/src/features/chat/types.ts'));
        $remote = file_get_contents(base_path('../client/src/features/chat/remote-components.tsx'));

        $this->assertIsString($types);
        $this->assertIsString($remote);

        foreach (ComponentRegistry::COMPONENTS as $key) {
            $this->assertStringContainsString('"'.$key.'"', $types, $key.' is missing from ChatComponentRegistryKey.');
            $this->assertMatchesRegularExpression('/"'.preg_quote($key, '/').'"\s*:/', $remote, $key.' is missing from remoteComponentRegistry.');
        }
    }

    public function test_historic_aliases_resolve_to_current_canonical_capabilities(): void
    {
        $registry = new ToolRegistry();

        $this->assertSame('tasks.create', $registry->actionKeyForIntent('create_task'));
        $this->assertSame('menus.items.move_section', $registry->actionKeyForIntent('move_menu_item_section'));
        $this->assertSame('documents.retry_extraction', $registry->actionKeyForIntent('retry_document_extraction'));
        $this->assertSame('members.remove', $registry->actionKeyForIntent('remove_member'));
    }

    public function test_deferred_modules_are_not_advertised_as_chat_tools(): void
    {
        $modules = collect((new ToolRegistry())->allMetadata())->pluck('module')->all();

        $this->assertNotContains('inventory', $modules);
        $this->assertNotContains('suppliers', $modules);
        $this->assertNotContains('purchase_orders', $modules);
        $this->assertNotContains('receipts', $modules);
    }

    public function test_canonical_tool_contracts_use_search_then_exact_id_for_targets(): void
    {
        $registry = new ToolRegistry();

        $searchFields = $registry->metadata($registry->resolve('recipes.list'))['input_schema']['fields'];
        $detailFields = $registry->metadata($registry->resolve('recipes.detail'))['input_schema']['fields'];
        $updateFields = $registry->metadata($registry->resolve('recipes.update'))['input_schema']['fields'];

        $this->assertContains('search', $searchFields);
        $this->assertContains('recipe_id', $detailFields);
        $this->assertNotContains('recipe_search', $detailFields);
        $this->assertContains('recipe_id', $updateFields);
        $this->assertNotContains('recipe_search', $updateFields);
    }

    public function test_openai_task_create_contract_exposes_duration_and_task_filters(): void
    {
        $registry = new ToolRegistry();
        $create = $registry->metadata($registry->resolve('tasks.create'));
        $search = $registry->metadata($registry->resolve('tasks.search'));

        $this->assertArrayHasKey('duration_minutes', $create['input_schema']['properties']);
        $this->assertContains('overdue', $search['input_schema']['fields']);
    }

    public function test_task_mutations_expose_search_and_bulk_target_contracts(): void
    {
        $registry = new ToolRegistry();

        foreach (['tasks.update', 'tasks.status.update', 'tasks.complete'] as $action) {
            $fields = $registry->metadata($registry->resolve($action))['input_schema']['fields'];

            $this->assertContains('task_id', $fields, $action);
            $this->assertContains('task_search', $fields, $action);
            $this->assertContains('task_ids', $fields, $action);
            $this->assertContains('search', $fields, $action);
        }

        $assignmentFields = $registry->metadata($registry->resolve('tasks.assign'))['input_schema']['fields'];
        $this->assertContains('member_search', $assignmentFields);
        $this->assertContains('task_search', $assignmentFields);
        $this->assertContains('search', $assignmentFields);
        $this->assertContains('task_ids', $assignmentFields);
    }

    public function test_task_profile_includes_workspace_member_lookup_for_assignment(): void
    {
        $registry = new ToolRegistry();
        $profile = (new ToolProfileSelector())->select(
            ['message' => 'asigna la tarea de programar a jennifer', 'active_entities' => []],
            $registry->allMetadata()
        );

        $this->assertContains('members.list', collect($profile['metadata'])->pluck('key')->all());
    }

    public function test_all_menu_mutations_are_confirmation_gated(): void
    {
        $registry = new ToolRegistry();

        foreach (['menus.rename', 'menus.items.add', 'menus.items.move_section'] as $key) {
            $tool = $registry->resolve($key);
            $this->assertSame('write', $tool['mode']);
            $this->assertTrue($tool['requires_confirmation']);
        }
    }
}
