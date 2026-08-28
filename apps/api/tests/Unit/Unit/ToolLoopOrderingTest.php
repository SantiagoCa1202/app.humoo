<?php

namespace Tests\Unit\Unit;

use App\AI\Orchestration\AIOrchestrator;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ToolLoopOrderingTest extends TestCase
{
    public function test_function_calling_v2_does_not_enable_the_tool_loop_by_itself(): void
    {
        $orchestrator = (new ReflectionClass(AIOrchestrator::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AIOrchestrator::class, 'toolLoopEnabled');
        $method->setAccessible(true);

        config([
            'ai.routing.function_calling_v2' => true,
            'ai.routing.tool_loop_enabled' => false,
        ]);

        $this->assertFalse($method->invoke($orchestrator));

        config(['ai.routing.tool_loop_enabled' => true]);

        $this->assertTrue($method->invoke($orchestrator));
    }

    public function test_openai_tool_loop_publishes_the_task_creation_contract(): void
    {
        $orchestrator = (new ReflectionClass(AIOrchestrator::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AIOrchestrator::class, 'toolLoopDefinitions');
        $method->setAccessible(true);

        $definitions = $method->invoke($orchestrator);
        $taskDefinition = collect($definitions)->firstWhere('name', 'tasks_create');

        $this->assertIsArray($taskDefinition);
        $this->assertSame('object', $taskDefinition['parameters']['type']);
        $this->assertArrayHasKey('title', $taskDefinition['parameters']['properties']);
        $this->assertArrayHasKey('starts_at', $taskDefinition['parameters']['properties']);
        $this->assertArrayHasKey('duration_minutes', $taskDefinition['parameters']['properties']);
        $this->assertContains('duration_minutes', $taskDefinition['parameters']['required']);
    }
}
