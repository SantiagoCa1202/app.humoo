<?php

namespace Tests\Feature\Feature;

use App\AI\Runtime\AiExecutionPlanRecovery;
use App\Events\Realtime\ChatStreamed;
use App\Jobs\ExecuteAiExecutionPlan;
use App\Models\AiExecutionPlan;
use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiExecutionPlanRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unstarted_failure_stays_resumable_and_is_requeued_without_uncertain_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        Event::fake([ChatStreamed::class]);
        Queue::fake();

        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $conversation = Conversation::query()->create([
            'created_by' => $user->id,
            'scope_type' => 'general',
            'status' => 'active',
            'title' => 'Recover unstarted plan',
            'visibility' => 'private',
            'workspace_id' => $workspace->id,
        ]);
        $message = Message::query()->create([
            'content_text' => 'Create the confirmed plan.',
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_type' => 'user',
            'status' => 'completed',
            'workspace_id' => $workspace->id,
        ]);
        $run = AiRun::query()->create([
            'actor_id' => $user->id,
            'conversation_id' => $conversation->id,
            'input_message_id' => $message->id,
            'message_id' => $message->id,
            'model_key' => 'test-model',
            'progress_current' => 0,
            'progress_total' => 1,
            'queued_at' => now(),
            'status' => 'running',
            'workspace_id' => $workspace->id,
        ]);
        $plan = AiExecutionPlan::query()->create([
            'ai_run_id' => $run->id,
            'block_size' => 1,
            'conversation_id' => $conversation->id,
            'created_by' => $user->id,
            'item_count' => 1,
            'status' => 'running',
            'title' => 'Unstarted plan',
            'workspace_id' => $workspace->id,
        ]);
        $run->forceFill(['execution_plan_id' => $plan->id])->save();
        $item = $plan->items()->create([
            'action_key' => 'tasks.create',
            'attempts' => 0,
            'input_json' => ['title' => 'Queued task'],
            'is_required' => true,
            'position' => 1,
            'status' => 'queued',
            'step_key' => 'queued_task',
        ]);

        (new ExecuteAiExecutionPlan(
            (string) $plan->id,
            (string) $workspace->id,
            (string) $user->id,
        ))->failed(new \RuntimeException('Queue infrastructure failed before claim.'));

        $this->assertSame('queued', $plan->fresh()->status);
        $this->assertSame('paused', $run->fresh()->status);
        $this->assertSame('WORKFLOW_RETRY_EXHAUSTED', $run->fresh()->error_code);
        $this->assertSame('queued', $item->fresh()->status);
        $this->assertSame(0, $item->fresh()->attempts);

        $recovered = app(AiExecutionPlanRecovery::class)->resumeUncertainFree((string) $plan->id);

        $this->assertSame('queued', $recovered->status);
        $this->assertNull($recovered->finished_at);
        $this->assertSame('queued', $run->fresh()->status);
        $this->assertNull($run->fresh()->error_code);
        $this->assertSame('queued', $item->fresh()->status);
        $this->assertSame(0, $item->fresh()->attempts);
        Queue::assertPushed(ExecuteAiExecutionPlan::class, fn (ExecuteAiExecutionPlan $job): bool => $job->executionPlanId === $plan->id);
    }
}
