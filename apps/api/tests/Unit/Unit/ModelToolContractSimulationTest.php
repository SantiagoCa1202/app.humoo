<?php

namespace Tests\Unit\Unit;

use App\AI\Tools\ToolRegistry;
use Tests\TestCase;

class ModelToolContractSimulationTest extends TestCase
{
    public function test_simulated_conversation_uses_canonical_actions_and_allowed_arguments(): void
    {
        $registry = new ToolRegistry();
        $modelCalls = [
            [
                'function' => 'members_list',
                'arguments' => ['search' => 'Santiago'],
            ],
            [
                'function' => 'tasks_search',
                'arguments' => [
                    'membership_id' => 'membership-santiago',
                    'status' => 'todo',
                ],
            ],
            [
                'function' => 'tasks_assign',
                'arguments' => [
                    'task_ids' => ['task-1', 'task-2'],
                    'membership_id' => 'membership-jennifer',
                ],
            ],
        ];

        foreach ($modelCalls as $call) {
            $actionKey = str_replace('_', '.', $call['function']);
            $tool = $registry->metadata($registry->resolve($actionKey));
            $schema = $tool['input_schema'];
            $fields = $schema['fields'] ?? array_keys($schema['properties'] ?? []);

            $this->assertSame($actionKey, $tool['key']);
            foreach (array_keys($call['arguments']) as $field) {
                $this->assertContains($field, $fields, $actionKey.' does not expose '.$field);
            }
            if (($tool['mode'] ?? 'read') === 'write') {
                $this->assertTrue($tool['requires_confirmation'], $actionKey.' must require confirmation');
            }
        }

        $this->assertSame('members.list', str_replace('_', '.', $modelCalls[0]['function']));
        $this->assertSame('tasks.search', str_replace('_', '.', $modelCalls[1]['function']));
        $this->assertSame('tasks.assign', str_replace('_', '.', $modelCalls[2]['function']));
    }

    public function test_simulated_contextual_and_bulk_turns_preserve_server_selected_ids(): void
    {
        $registry = new ToolRegistry();
        $searchResult = [
            ['id' => 'task-first', 'title' => 'Revisar freezer'],
            ['id' => 'task-second', 'title' => 'Revisar dry storage'],
        ];
        $modelCalls = [
            [
                'function' => 'tasks_search',
                'arguments' => ['search' => 'limpieza', 'status' => 'todo'],
            ],
            [
                'function' => 'tasks_status_update',
                'arguments' => [
                    'task_ids' => array_column($searchResult, 'id'),
                    'status' => 'done',
                ],
            ],
            [
                'function' => 'tasks_assign',
                'arguments' => [
                    'task_id' => $searchResult[0]['id'],
                    'membership_id' => 'membership-santiago',
                ],
            ],
        ];

        $this->assertSame(['task-first', 'task-second'], $modelCalls[1]['arguments']['task_ids']);
        $this->assertSame('task-first', $modelCalls[2]['arguments']['task_id']);
        $this->assertNotSame('tasks_create', $modelCalls[2]['function']);

        foreach ($modelCalls as $call) {
            $actionKey = str_replace('_', '.', $call['function']);
            $tool = $registry->metadata($registry->resolve($actionKey));

            $this->assertSame($actionKey, $tool['key']);
            if (($tool['mode'] ?? 'read') === 'write') {
                $this->assertTrue($tool['requires_confirmation']);
            }
        }
    }
}
