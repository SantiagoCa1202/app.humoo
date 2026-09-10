<?php

namespace Tests\Unit\Unit;

use App\AI\Presentation\ChatComponentContract;
use Tests\TestCase;

class ChatComponentContractTest extends TestCase
{
    public function test_component_contract_adds_shared_module_entity_and_operation_metadata(): void
    {
        $block = ChatComponentContract::normalizeBlock(
            [
                'component' => 'action.result',
                'data' => ['items' => [['id' => 'task-1']]],
                'type' => 'component',
            ],
            [
                'action_id' => 'tasks.search',
                'entity_type' => 'task',
                'module' => 'tasks',
                'operation_type' => 'read',
            ]
        );

        $this->assertSame(1, $block['data']['contract_version']);
        $this->assertSame('task', $block['data']['entity_type']);
        $this->assertSame('tasks', $block['data']['module']);
        $this->assertSame('read', $block['data']['operation']);
        $this->assertSame(1, $block['meta']['contract_version']);
        $this->assertSame('task', $block['meta']['entity_type']);
        $this->assertSame('tasks', $block['meta']['module']);
        $this->assertSame('tasks.search', $block['meta']['action_id']);
    }

    public function test_component_contract_preserves_payloads_and_normalizes_canonical_actions(): void
    {
        $block = ChatComponentContract::normalizeBlock([
            'actions' => [
                ['id' => 'tasks.read', 'label' => 'Ver'],
            ],
            'component' => 'tasks.list',
            'data' => ['tasks' => []],
            'meta' => ['entity_type' => 'task'],
            'schema_version' => 1,
            'type' => 'component',
        ]);

        $this->assertSame([], $block['data']['tasks']);
        $this->assertSame('task', $block['meta']['entity_type']);
        $this->assertSame('tasks.read', $block['actions'][0]['action_id']);
        $this->assertFalse($block['actions'][0]['disabled']);
        $this->assertFalse($block['actions'][0]['requires_confirmation']);
    }

    public function test_non_component_blocks_are_not_changed(): void
    {
        $block = ['text' => 'Respuesta', 'type' => 'text'];

        $this->assertSame($block, ChatComponentContract::normalizeBlock($block));
    }
}
