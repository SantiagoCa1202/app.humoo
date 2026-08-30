<?php

namespace Tests\Unit;

use App\AI\EntityResolution\ChatEntityResolver;
use App\AI\EntityResolution\DirectoryEntityResolver;
use App\AI\EntityResolution\MenuEntityResolver;
use App\AI\EntityResolution\PrepEntityResolver;
use App\AI\EntityResolution\RecipeEntityResolver;
use App\AI\EntityResolution\TeamStaffEntityResolver;
use App\Application\Actions\ChatTools\ListTasksForTool;
use App\Application\Actions\ChatTools\ListWorkspaceMembersForTool;
use Mockery;
use Tests\TestCase;

class ChatEntityResolverTest extends TestCase
{
    public function test_task_resolution_uses_the_shared_chat_context_and_preserves_the_module_result(): void
    {
        $tasks = Mockery::mock(ListTasksForTool::class);
        $tasks->shouldReceive('find')
            ->once()
            ->with('workspace-1', 'task-1', 'freezer', [['type' => 'task', 'id' => 'task-1']], 'action-1', 'review freezer', 'user-1')
            ->andReturn(['status' => 'resolved', 'entity' => 'task']);

        $resolver = $this->resolver($tasks);

        $result = $resolver->resolve(
            'workspace-1',
            'task',
            ['task_id' => 'task-1', 'task_search' => 'freezer'],
            [['type' => 'task', 'id' => 'task-1']],
            'action-1',
            'review freezer',
            'user-1',
        );

        $this->assertSame(['status' => 'resolved', 'entity' => 'task'], $result);
    }

    public function test_membership_resolution_is_shared_with_task_and_staff_flows(): void
    {
        $members = Mockery::mock(ListWorkspaceMembersForTool::class);
        $members->shouldReceive('find')
            ->once()
            ->with('workspace-1', null, 'Jennifer', [])
            ->andReturn(['status' => 'ambiguous', 'candidates' => [['id' => 'member-1', 'name' => 'Jennifer Mora']]]);

        $resolver = $this->resolver(null, $members);

        $result = $resolver->resolve('workspace-1', 'membership', ['member_search' => 'Jennifer']);

        $this->assertSame('ambiguous', $result['status']);
        $this->assertSame('member-1', $result['candidates'][0]['id']);
    }

    private function resolver(?ListTasksForTool $tasks = null, ?ListWorkspaceMembersForTool $members = null): ChatEntityResolver
    {
        return new ChatEntityResolver(
            $tasks ?? Mockery::mock(ListTasksForTool::class),
            $members ?? Mockery::mock(ListWorkspaceMembersForTool::class),
            Mockery::mock(DirectoryEntityResolver::class),
            Mockery::mock(RecipeEntityResolver::class),
            Mockery::mock(MenuEntityResolver::class),
            Mockery::mock(PrepEntityResolver::class),
            Mockery::mock(TeamStaffEntityResolver::class),
        );
    }
}
