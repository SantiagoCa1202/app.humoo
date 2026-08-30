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
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_delete_uses_one_preview_and_deletes_the_complete_selected_set(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $otherWorkspace = Workspace::query()->create([
            'name' => 'Other Kitchen',
            'slug' => 'other-kitchen',
            'default_locale' => 'en',
            'timezone' => 'America/New_York',
            'currency' => 'USD',
            'status' => 'active',
        ]);

        $first = Task::query()->create($this->taskAttributes($workspace, $actor, 'Limpiar freezer'));
        $second = Task::query()->create($this->taskAttributes($workspace, $actor, 'Limpiar dry storage'));
        $outside = Task::query()->create($this->taskAttributes($otherWorkspace, $actor, 'Limpiar freezer externo'));

        [$conversation, $sourceMessage] = $this->conversationContext($workspace, $actor);
        app()->instance('currentWorkspace', $workspace);
        $executor = app(ToolExecutor::class);
        $context = [
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000021',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'user' => $actor,
            'workspace' => $workspace,
        ];

        $preview = $executor->request($context, [
            'action_id' => 'tasks.delete',
            'entity' => [],
            'input' => ['search' => 'Limpiar'],
        ]);

        $confirmationId = $preview['confirmation']['id'] ?? null;
        $this->assertNotNull($confirmationId);
        $previewData = collect($preview['blocks'])->firstWhere('component', 'action.preview')['data'];
        $this->assertSame(2, count($previewData['changes']));

        $confirmation = ActionConfirmation::query()->findOrFail($confirmationId);
        $draftEntity = $confirmation->draft_json['entity'] ?? [];
        $this->assertCount(2, $draftEntity['ids'] ?? []);
        $this->assertSame([
            $first->id => $first->version,
            $second->id => $second->version,
        ], $draftEntity['versions']);

        $result = $executor->confirm($confirmation, $context);

        $this->assertDatabaseMissing('tasks', ['id' => $first->id, 'workspace_id' => $workspace->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $second->id, 'workspace_id' => $workspace->id]);
        $this->assertDatabaseHas('tasks', ['id' => $outside->id, 'workspace_id' => $otherWorkspace->id]);
        $this->assertSame(2, $result['result_ref_json']['count']);
        $this->assertCount(2, $result['result_ref_json']['items']);
    }

    public function test_bulk_delete_aborts_when_one_selected_task_changed_after_preview(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $first = Task::query()->create($this->taskAttributes($workspace, $actor, 'Eliminar uno'));
        $second = Task::query()->create($this->taskAttributes($workspace, $actor, 'Eliminar dos'));
        [$conversation, $sourceMessage] = $this->conversationContext($workspace, $actor);
        app()->instance('currentWorkspace', $workspace);
        $executor = app(ToolExecutor::class);
        $context = [
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000022',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'user' => $actor,
            'workspace' => $workspace,
        ];

        $preview = $executor->request($context, [
            'action_id' => 'tasks.delete',
            'entity' => [],
            'input' => ['task_ids' => [$first->id, $second->id]],
        ]);
        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);

        $second->forceFill(['version' => $second->version + 1])->save();

        try {
            $executor->confirm($confirmation, $context);
            $this->fail('Expected a version validation error.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }

        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'workspace_id' => $workspace->id]);
        $this->assertDatabaseHas('tasks', ['id' => $second->id, 'workspace_id' => $workspace->id]);
    }

    public function test_grouped_task_creation_uses_one_confirmation_and_creates_every_task(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $actor = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        [$conversation, $sourceMessage] = $this->conversationContext($workspace, $actor);
        app()->instance('currentWorkspace', $workspace);
        $executor = app(ToolExecutor::class);
        $context = [
            'conversation' => $conversation,
            'correlation_id' => '01j00000000000000000000023',
            'entity_refs' => [],
            'locale' => 'es',
            'source_message' => $sourceMessage,
            'user' => $actor,
            'workspace' => $workspace,
        ];

        $preview = $executor->request($context, [
            'action_id' => 'tasks.create_many',
            'entity' => [],
            'input' => [
                'tasks' => [
                    ['title' => 'Revisar freezer', 'priority' => 'high'],
                    ['title' => 'Hacer inventario', 'priority' => 'urgent'],
                ],
            ],
        ]);

        $this->assertNotNull($preview['confirmation']['id'] ?? null);
        $confirmation = ActionConfirmation::query()->findOrFail($preview['confirmation']['id']);
        $this->assertCount(2, $confirmation->draft_json['input']['tasks'] ?? []);

        $result = $executor->confirm($confirmation, $context);

        $this->assertSame(2, $result['result_ref_json']['count']);
        $this->assertDatabaseHas('tasks', ['workspace_id' => $workspace->id, 'title' => 'Revisar freezer', 'priority' => 'high']);
        $this->assertDatabaseHas('tasks', ['workspace_id' => $workspace->id, 'title' => 'Hacer inventario', 'priority' => 'urgent']);
    }

    /** @return array<string, mixed> */
    private function taskAttributes(Workspace $workspace, User $actor, string $title): array
    {
        return [
            'created_by' => $actor->id,
            'priority' => 'normal',
            'source' => 'user',
            'status' => 'todo',
            'title' => $title,
            'type' => 'general',
            'updated_by' => $actor->id,
            'version' => 1,
            'workspace_id' => $workspace->id,
        ];
    }

    /** @return array{0: Conversation, 1: Message} */
    private function conversationContext(Workspace $workspace, User $actor): array
    {
        $conversation = Conversation::query()->create([
            'created_by' => $actor->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Bulk task operations',
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
        $message = Message::query()->create([
            'content_text' => 'Elimina las tareas de limpieza.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $actor->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);

        return [$conversation, $message];
    }
}
