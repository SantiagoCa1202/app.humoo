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
}
