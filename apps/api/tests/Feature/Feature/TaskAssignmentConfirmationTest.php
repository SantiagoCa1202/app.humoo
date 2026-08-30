<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_confirmation_uses_the_resolved_task_version(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $role = Role::query()->whereNull('workspace_id')->where('key', 'owner')->firstOrFail();
        $assignee = User::factory()->create([
            'name' => 'Santiago Test',
            'status' => 'active',
        ]);
        $membership = WorkspaceMembership::query()->create([
            'joined_at' => now(),
            'role_id' => $role->id,
            'status' => 'active',
            'user_id' => $assignee->id,
            'workspace_id' => $workspace->id,
        ]);
        $task = Task::query()->create([
            'created_by' => $actor->id,
            'priority' => 'normal',
            'source' => 'user',
            'status' => 'todo',
            'title' => 'Programar app',
            'type' => 'general',
            'updated_by' => $actor->id,
            'version' => 3,
            'workspace_id' => $workspace->id,
        ]);
        $conversation = Conversation::query()->create([
            'created_by' => $actor->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Task assignment',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $actor->id,
            'workspace_id' => $workspace->id,
        ]);
        $sourceMessage = Message::query()->create([
            'content_text' => 'Asigna la tarea a Santiago.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);

        app()->instance('currentWorkspace', $workspace);
        $executor = app(ToolExecutor::class);
        $context = [
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000004',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'user' => $actor,
            'workspace' => $workspace,
        ];

        $preview = $executor->request($context, [
            'action_id' => 'tasks.assign',
            'entity' => [
                'id' => $task->id,
                'type' => 'task',
                // Simulate the model supplying its common default instead of
                // the current server-side version.
                'version' => 1,
            ],
            'input' => ['membership_id' => $membership->id],
        ]);

        $confirmationId = $preview['confirmation']['id'] ?? null;

        $this->assertNotNull($confirmationId);

        $actionConfirmation = \App\Models\ActionConfirmation::query()->findOrFail($confirmationId);
        $result = $executor->confirm($actionConfirmation, $context);

        $task->refresh();
        $this->assertSame('Santiago Test', $task->assignments()->firstOrFail()->membership()->firstOrFail()->user()->firstOrFail()->name);
        $this->assertSame(4, $task->version);
        $this->assertSame('tasks.assign', $result['tool']['key']);
    }
}
