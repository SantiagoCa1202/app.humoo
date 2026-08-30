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

class TaskCreationAssignmentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_preserves_the_member_resolved_during_task_creation_preview(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $role = Role::query()->whereNull('workspace_id')->where('key', 'owner')->firstOrFail();
        $jennifer = User::factory()->create([
            'name' => 'Jennifer Test',
            'status' => 'active',
        ]);
        $membership = WorkspaceMembership::query()->create([
            'joined_at' => now(),
            'role_id' => $role->id,
            'status' => 'active',
            'user_id' => $jennifer->id,
            'workspace_id' => $workspace->id,
        ]);
        $conversation = Conversation::query()->create([
            'created_by' => $actor->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Task creation assignment',
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
            'content_text' => 'Crea una tarea para Jennifer.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);

        app()->instance('currentWorkspace', $workspace);
        $context = [
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000005',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'user' => $actor,
            'workspace' => $workspace,
        ];
        $executor = app(ToolExecutor::class);

        $preview = $executor->request($context, [
            'action_id' => 'tasks.create',
            'input' => [
                'membership_id' => $membership->id,
                'priority' => 'normal',
                'starts_at' => '2026-08-30 06:25:00',
                'status' => 'todo',
                'title' => 'Revisar el freezer',
                'type' => 'general',
            ],
        ]);
        $previewData = collect($preview['blocks'])->firstWhere('component', 'action.preview')['data'];
        $this->assertSame('Jennifer Test', collect($previewData['changes'])->firstWhere('label', 'Responsable')['after']);
        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);

        $executor->confirm($confirmation, $context);

        $task = Task::query()->where('workspace_id', $workspace->id)->where('title', 'Revisar el freezer')->firstOrFail();
        $this->assertSame($membership->id, $task->assignments()->firstOrFail()->membership_id);
    }
}
