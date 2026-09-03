<?php

namespace Tests\Unit\Unit;

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
            ->with('workspace-1', 'task-1', 'freezer', [['type' => 'task', 'id' => 'task-1']], 'user-1', 'action-1')
            ->andReturn(['status' => 'resolved', 'entity' => 'task']);

        $resolver = $this->resolver($tasks);
        $result = $resolver->resolve(
            'workspace-1',
            'task',
            ['task_id' => 'task-1', 'task_search' => 'freezer'],
            [['type' => 'task', 'id' => 'task-1']],
            'action-1',
            'review freezer',
            'user-1'
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

    public function test_human_references_are_moved_out_of_id_fields_before_validation(): void
    {
        $resolver = new ChatEntityResolver(
            $this->createStub(ListTasksForTool::class),
            $this->createStub(ListWorkspaceMembersForTool::class),
            $this->createStub(DirectoryEntityResolver::class),
            $this->createStub(RecipeEntityResolver::class),
            $this->createStub(MenuEntityResolver::class),
            $this->createStub(PrepEntityResolver::class),
            $this->createStub(TeamStaffEntityResolver::class),
        );

        $normalized = $resolver->normalizeInputReferences([
            'membership_id' => 'Jennifer Mora',
            'tasks' => [
                ['event_id' => 'Cena de prueba'],
                ['membership_id' => 'Santiago Castillo'],
            ],
        ]);

        $this->assertNull($normalized['membership_id']);
        $this->assertSame('Jennifer Mora', $normalized['member_search']);
        $this->assertSame('Cena de prueba', $normalized['tasks'][0]['event_search']);
        $this->assertNull($normalized['tasks'][1]['membership_id']);
        $this->assertSame('Santiago Castillo', $normalized['tasks'][1]['member_search']);
    }

    public function test_stable_ulids_remain_unchanged(): void
    {
        $resolver = new ChatEntityResolver(
            $this->createStub(ListTasksForTool::class),
            $this->createStub(ListWorkspaceMembersForTool::class),
            $this->createStub(DirectoryEntityResolver::class),
            $this->createStub(RecipeEntityResolver::class),
            $this->createStub(MenuEntityResolver::class),
            $this->createStub(PrepEntityResolver::class),
            $this->createStub(TeamStaffEntityResolver::class),
        );
        $id = '01j00000000000000000000001';

        $this->assertSame(
            ['task_id' => $id],
            $resolver->normalizeInputReferences(['task_id' => $id])
        );
    }

    public function test_ai_first_recipe_resolution_does_not_forward_the_raw_user_message(): void
    {
        config()->set('ai.routing.tool_loop_enabled', true);

        $recipes = Mockery::mock(RecipeEntityResolver::class);
        $recipes->shouldReceive('resolve')
            ->once()
            ->with('workspace-1', [], null, 'Ranch casero', null, 'recipes.detail', null)
            ->andReturn(['status' => 'resolved']);

        $resolver = new ChatEntityResolver(
            $this->createStub(ListTasksForTool::class),
            $this->createStub(ListWorkspaceMembersForTool::class),
            $this->createStub(DirectoryEntityResolver::class),
            $recipes,
            $this->createStub(MenuEntityResolver::class),
            $this->createStub(PrepEntityResolver::class),
            $this->createStub(TeamStaffEntityResolver::class),
        );

        $result = $resolver->resolve(
            'workspace-1',
            'recipe',
            ['recipe_search' => 'Ranch casero'],
            [],
            'recipes.detail',
            'Muéstrame Ranch casero y luego crea otra receta',
        );

        $this->assertSame('resolved', $result['status']);
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
