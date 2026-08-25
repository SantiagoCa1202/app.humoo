<?php

namespace Tests\Unit\Unit;

use App\AI\Presentation\ComponentRegistry;
use App\AI\Tools\ToolExecutor;
use App\AI\Tools\ToolRegistry;
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
}
