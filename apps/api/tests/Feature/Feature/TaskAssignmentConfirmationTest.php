<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Models\ActionConfirmation;
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

    public function test_combined_task_update_persists_assignment_and_returns_final_details(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $role = Role::query()->whereNull('workspace_id')->where('key', 'owner')->firstOrFail();
        $assignee = User::factory()->create([
            'name' => 'Santiago Update Test',
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
            'title' => 'Revisar freezer combinado',
            'type' => 'general',
            'updated_by' => $actor->id,
            'version' => 1,
            'workspace_id' => $workspace->id,
        ]);
        $conversation = Conversation::query()->create([
            'created_by' => $actor->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Combined task update',
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
            'content_text' => 'Ponla para el viernes a las 4 PM, prioridad urgente, asígnala a Santiago y luego muéstrame cómo quedó.',
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
            'correlation_id' => '01j00000000000000000000006',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'user' => $actor,
            'workspace' => $workspace,
        ];

        $preview = $executor->request($context, [
            'action_id' => 'tasks.update',
            'entity' => ['id' => $task->id, 'type' => 'task', 'version' => 1],
            'input' => [
                'membership_id' => $membership->id,
                'priority' => 'urgent',
                'starts_at' => '2026-09-04T16:00:00+00:00',
            ],
        ]);
        $previewData = collect($preview['blocks'])->firstWhere('component', 'action.preview')['data'];

        $this->assertSame('Santiago Update Test', collect($previewData['changes'])->firstWhere('label', 'Responsable')['after']);

        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $result = $executor->confirm($confirmation, $context);
        $details = collect($result['blocks'])->firstWhere('component', 'action.result')['data']['details'];

        $task->refresh();
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('2026-09-04T16:00:00+00:00', $task->starts_at?->toIso8601String());
        $this->assertSame($membership->id, $task->assignments()->firstOrFail()->membership_id);
        $this->assertSame('Santiago Update Test', collect($details)->firstWhere('label', 'Responsable')['value']);
        $this->assertSame('urgent', collect($details)->firstWhere('label', 'Prioridad')['value']);
        $this->assertSame('2026-09-04T16:00:00+00:00', collect($details)->firstWhere('label', 'Comienza')['value']);
    }
}
