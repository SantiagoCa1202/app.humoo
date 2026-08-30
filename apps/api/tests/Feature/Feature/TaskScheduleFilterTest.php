<?php

namespace Tests\Feature\Feature;

use App\Application\Actions\ChatTools\ListTasksForTool;
use App\Application\Actions\Tasks\CreateTask;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskScheduleFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_filter_matches_tasks_with_a_start_time_and_no_due_time(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        app(CreateTask::class)->execute($workspace->id, $user->id, [
            'assignments' => [['membership_id' => $membership->id]],
            'starts_at' => '2026-08-31 08:00:00',
            'title' => 'Revisar los dry',
            'type' => 'general',
        ]);

        $result = app(ListTasksForTool::class)->execute($workspace->id, [
            'due_from' => '2026-08-31 00:00:00',
            'due_to' => '2026-08-31 23:59:59',
            'membership_id' => $membership->id,
        ]);

        $this->assertSame(1, $result['count']);
        $this->assertSame('Revisar los dry', $result['items'][0]['title']);
    }

    public function test_date_filter_can_exclude_an_active_assignee(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $role = \App\Models\Role::query()->whereNull('workspace_id')->where('key', 'owner')->firstOrFail();
        $santiago = User::factory()->create(['name' => 'Santiago Castillo', 'status' => 'active']);
        $santiagoMembership = WorkspaceMembership::query()->create([
            'joined_at' => now(),
            'role_id' => $role->id,
            'status' => 'active',
            'user_id' => $santiago->id,
            'workspace_id' => $workspace->id,
        ]);

        app(CreateTask::class)->execute($workspace->id, $actor->id, [
            'assignments' => [['membership_id' => $santiagoMembership->id]],
            'starts_at' => '2026-08-31 08:00:00',
            'title' => 'Revisar los dry',
            'type' => 'general',
        ]);
        app(CreateTask::class)->execute($workspace->id, $actor->id, [
            'starts_at' => '2026-08-31 09:00:00',
            'title' => 'Limpiar dry storage',
            'type' => 'general',
        ]);

        $result = app(ListTasksForTool::class)->execute($workspace->id, [
            'due_from' => '2026-08-01 00:00:00',
            'due_to' => '2026-08-31 23:59:59',
            'status' => 'todo',
            'exclude_membership_id' => $santiagoMembership->id,
        ]);

        $this->assertSame(1, $result['count']);
        $this->assertSame('Limpiar dry storage', $result['items'][0]['title']);

        $searchResult = app(ListTasksForTool::class)->execute($workspace->id, [
            'due_from' => '2026-08-01 00:00:00',
            'due_to' => '2026-08-31 23:59:59',
            'status' => 'todo',
            'exclude_member_search' => 'Santiago',
        ]);

        $this->assertSame(1, $searchResult['count']);
        $this->assertSame('Limpiar dry storage', $searchResult['items'][0]['title']);
    }
}
