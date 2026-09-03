<?php

namespace Tests\Feature\Feature;

use App\AI\Tools\ToolExecutor;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Menu;
use App\Models\Message;
use App\Models\Recipe;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatReadToolStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_and_recipe_reads_complete_with_a_terminal_status(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $membership = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Read tools status',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'joined_at' => now(),
            'role' => 'owner',
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $message = Message::query()->create([
            'content_text' => 'Muestra el menú y las recetas.',
            'conversation_id' => $conversation->id,
            'locale' => 'es',
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $menu = Menu::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Menu de lectura',
            'status' => 'draft',
            'current_version' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $recipes = collect(range(1, 13))->map(function (int $position) use ($workspace, $user): Recipe {
            return Recipe::query()->create([
                'workspace_id' => $workspace->id,
                'name' => sprintf('Receta de lectura %02d', $position),
                'status' => 'draft',
                'current_version' => 0,
                'type' => 'standard',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
        });

        app()->instance('currentWorkspace', $workspace);
        app()->instance('currentMembership', $membership);
        $context = [
            'conversation' => $conversation,
            'entity_refs' => [],
            'locale' => 'es',
            'membership' => $membership,
            'user' => $user,
            'user_message' => $message,
            'workspace' => $workspace,
        ];
        $executor = app(ToolExecutor::class);

        $menuResult = $executor->request($context, [
            'action_id' => 'menus.show',
            'input' => ['menu_id' => $menu->id],
        ]);
        $recipeResult = $executor->request($context, [
            'action_id' => 'recipes.list',
            'input' => ['search' => 'Receta de lectura'],
        ]);
        $recipeDetailResult = $executor->request($context, [
            'action_id' => 'recipes.detail',
            'input' => ['recipe_id' => $recipes->first()->id],
        ]);
        $planResult = $executor->request($context, [
            'action_id' => 'execution_plans.latest',
            'input' => [],
        ]);

        $this->assertSame('completed', $menuResult['status']);
        $this->assertSame($menu->id, $menuResult['result_ref_json']['items'][0]['id']);
        $this->assertSame('completed', $recipeResult['status']);
        $this->assertCount(13, $recipeResult['result_ref_json']['items']);
        $this->assertContains('Receta de lectura 13', array_column($recipeResult['result_ref_json']['items'], 'name'));
        $this->assertArrayNotHasKey('current_version_record', $recipeResult['result_ref_json']['items'][0]);
        $this->assertSame('completed', $recipeDetailResult['status']);
        $this->assertSame($recipes->first()->id, $recipeDetailResult['result_ref_json']['items'][0]['id']);
        $this->assertSame('completed', $planResult['status']);
    }
}
