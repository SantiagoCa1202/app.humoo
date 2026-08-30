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
}
