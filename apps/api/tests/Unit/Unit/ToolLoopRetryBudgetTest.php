<?php

namespace Tests\Unit\Unit;

use App\AI\Orchestration\ToolLoopRetryBudget;
use App\AI\Tools\ToolObservation;
use Tests\TestCase;

class ToolLoopRetryBudgetTest extends TestCase
{
    public function test_it_allows_one_structural_repair_and_stops_a_second_failure(): void
    {
        $budget = new ToolLoopRetryBudget(1, 1, 'test');
        $failure = ToolObservation::make(false, 'VALIDATION_FAILED', 'Invalid.', [], ['recoverable' => true], ['correct_arguments']);

        $first = $budget->apply('execution_plans.create', ['steps' => []], $failure);
        $second = $budget->apply('execution_plans.create', ['steps' => [['step_key' => 'changed']]], $failure);

        $this->assertTrue($first['retryable']);
        $this->assertSame('STRUCTURAL_PLAN_ERROR', $first['code']);
        $this->assertFalse($second['retryable']);
        $this->assertSame('RETRY_BUDGET_EXHAUSTED', $second['code']);
        $this->assertSame(2, $budget->metrics()['structural_plan_retry_count']);
        $this->assertSame('STRUCTURAL_PLAN_ERROR', $budget->metrics()['plan_validation_error_code']);
    }

    public function test_it_never_retries_the_same_failed_call_blindly(): void
    {
        $budget = new ToolLoopRetryBudget(1, 1, 'test');
        $failure = ToolObservation::make(false, 'VALIDATION_FAILED', 'Invalid.', [], ['recoverable' => true], ['correct_arguments']);

        $budget->apply('tasks.create', ['title' => ''], $failure);
        $repeat = $budget->guard('tasks.create', ['title' => '']);

        $this->assertIsArray($repeat);
        $this->assertFalse($repeat['retryable']);
        $this->assertSame('REPEATED_TOOL_CALL', $repeat['code']);
    }

    public function test_it_deduplicates_an_identical_successful_read_within_the_turn(): void
    {
        $budget = new ToolLoopRetryBudget(1, 1, 'test');
        $success = ToolObservation::make(true, null, 'Loaded.', ['items' => []]);

        $budget->apply('recipes.list', ['search' => 'Steak Frites'], $success);
        $repeat = $budget->guard('recipes.list', ['search' => 'Steak Frites']);

        $this->assertSame('REPEATED_TOOL_CALL', $repeat['code']);
        $this->assertSame(1, $budget->metrics()['duplicate_calls_avoided']);
    }
}
