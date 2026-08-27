<?php

namespace Tests\Unit\Unit;

use App\AI\Providers\RuleBasedAIProvider;
use Tests\TestCase;

class TaskPayloadExtractionTest extends TestCase
{
    public function test_task_creation_extracts_title_and_time_range_before_validation(): void
    {
        $decision = (new RuleBasedAIProvider())->generate([
            'locale' => 'es',
            'message' => 'crear una tarea mañana de 08:00 a 10:00 de limpiar coolers',
            'timezone' => 'America/New_York',
        ]);

        $this->assertSame('create_task', $decision['intent']);
        $this->assertSame('limpiar coolers', $decision['slots']['task_title']);
        $this->assertStringContainsString('T08:00:00', $decision['slots']['starts_at']);
        $this->assertStringContainsString('T10:00:00', $decision['slots']['due_at']);
    }
}
