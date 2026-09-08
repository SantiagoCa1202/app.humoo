<?php

namespace App\Console\Commands;

use App\AI\Runtime\AiExecutionPlanRecovery;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class ResumeAiExecutionPlan extends Command
{
    protected $signature = 'ai:execution-plan:resume {plan : Execution plan ULID}';

    protected $description = 'Safely requeue an AI execution plan with no uncertain operations';

    public function handle(AiExecutionPlanRecovery $recovery): int
    {
        try {
            $plan = $recovery->resumeUncertainFree((string) $this->argument('plan'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first() ?? 'The plan cannot be resumed safely.');

            return self::FAILURE;
        }

        $this->info("Execution plan {$plan->id} was queued without repeating completed work.");

        return self::SUCCESS;
    }
}
