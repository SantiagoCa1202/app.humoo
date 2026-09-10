<?php

namespace Tests\Unit\Unit;

use App\AI\Orchestration\ToolLoopResultComposer;
use Tests\TestCase;

class ToolLoopResultComposerTest extends TestCase
{
    public function test_read_components_are_kept_before_the_latest_write_result(): void
    {
        $result = ToolLoopResultComposer::compose(
            [[
                'blocks' => [[
                    'component' => 'tasks.list',
                    'data' => ['items' => [['id' => 'task-1']]],
                    'type' => 'component',
                ]],
                'entity_refs' => [['id' => 'task-1', 'type' => 'task']],
            ]],
            [
                'blocks' => [[
                    'component' => 'action.preview',
                    'data' => ['action_key' => 'tasks.update'],
                    'type' => 'component',
                ]],
                'entity_refs' => [['id' => 'task-1', 'type' => 'task']],
                'status' => 'confirmation_required',
            ]
        );

        $this->assertSame(
            ['tasks.list', 'action.preview'],
            collect($result['blocks'])->pluck('component')->all()
        );
        $this->assertCount(1, $result['entity_refs']);
        $this->assertSame('confirmation_required', $result['status']);
    }

    public function test_duplicate_blocks_are_not_rendered_twice(): void
    {
        $block = [
            'component' => 'tasks.list',
            'data' => ['items' => []],
            'type' => 'component',
        ];

        $result = ToolLoopResultComposer::compose(
            [['blocks' => [$block]]],
            ['blocks' => [$block]]
        );

        $this->assertCount(1, $result['blocks']);
    }

    public function test_internal_supporting_reads_are_not_rendered_with_the_latest_result(): void
    {
        $result = ToolLoopResultComposer::compose(
            [[
                'visible' => false,
                'blocks' => [[
                    'component' => 'action.result',
                    'data' => ['items' => [['id' => 'membership-1']]],
                    'type' => 'component',
                ]],
                'entity_refs' => [['id' => 'membership-1', 'type' => 'membership']],
            ]],
            [
                'blocks' => [[
                    'component' => 'action.preview',
                    'data' => ['action_key' => 'tasks.create'],
                    'type' => 'component',
                ]],
                'status' => 'confirmation_required',
            ]
        );

        $this->assertSame(['action.preview'], collect($result['blocks'])->pluck('component')->all());
        $this->assertArrayNotHasKey('entity_refs', $result);
    }

    public function test_normal_terminal_result_preserves_visible_intermediate_text(): void
    {
        $result = ToolLoopResultComposer::compose(
            [[
                'blocks' => [
                    ['text' => 'There is no persisted execution plan in this conversation.', 'type' => 'text'],
                    [
                        'component' => 'execution.plan',
                        'data' => ['status' => 'not_found'],
                        'type' => 'component',
                    ],
                ],
            ]],
            [
                'blocks' => [[
                    'text' => 'La confirmación de la receta sigue pendiente.',
                    'type' => 'text',
                ]],
                'status' => 'waiting_confirmation',
            ],
        );

        $this->assertSame(
            ['There is no persisted execution plan in this conversation.', 'La confirmación de la receta sigue pendiente.'],
            collect($result['blocks'])->where('type', 'text')->pluck('text')->all(),
        );
        $this->assertSame(
            ['execution.plan'],
            collect($result['blocks'])->where('type', 'component')->pluck('component')->all(),
        );
    }

    public function test_execution_plan_latest_text_is_never_used_as_objective_prose(): void
    {
        $result = ToolLoopResultComposer::compose(
            [[
                'tool_key' => 'execution_plans.latest',
                'blocks' => [
                    ['text' => 'There is no persisted execution plan in this conversation.', 'type' => 'text'],
                    ['component' => 'execution.plan', 'data' => ['status' => 'not_found'], 'type' => 'component'],
                ],
            ]],
            [
                'blocks' => [['text' => 'Necesito saber a quién asignar las tareas.', 'type' => 'text']],
                'status' => 'clarification_required',
            ],
        );

        $this->assertSame(
            ['Necesito saber a quién asignar las tareas.'],
            collect($result['blocks'])->where('type', 'text')->pluck('text')->all(),
        );
        $this->assertSame(['execution.plan'], collect($result['blocks'])->where('type', 'component')->pluck('component')->all());
    }
}
