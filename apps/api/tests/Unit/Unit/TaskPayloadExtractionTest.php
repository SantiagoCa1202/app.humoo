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

    public function test_task_commands_route_to_registered_operations(): void
    {
        $provider = new RuleBasedAIProvider();

        $search = $provider->generate([
            'locale' => 'es',
            'message' => 'Muestrame las tareas de manana',
            'timezone' => 'America/New_York',
        ]);
        $this->assertSame('tool_action', $search['intent']);
        $this->assertSame('tasks.search', $search['slots']['action_key']);

        $assignment = $provider->generate([
            'locale' => 'es',
            'message' => 'Reasigna la tarea de inventario a Jennifer',
        ]);
        $this->assertSame('tasks.assign', $assignment['slots']['action_key']);
        $this->assertSame('Jennifer', $assignment['slots']['input']['member_search']);

        $complete = $provider->generate([
            'locale' => 'es',
            'message' => 'Marca la limpieza del cooler como completada',
        ]);
        $this->assertSame('tasks.complete', $complete['slots']['action_key']);
    }

    public function test_task_creation_extracts_title_from_date_time_prefix(): void
    {
        $decision = (new RuleBasedAIProvider())->generate([
            'locale' => 'en',
            'message' => 'Create a task tomorrow at 8, clean coolers, for 3 hours',
            'timezone' => 'America/New_York',
        ]);

        $this->assertSame('clean coolers', $decision['slots']['task_title']);
        $this->assertNotNull($decision['slots']['starts_at']);
        $this->assertNotNull($decision['slots']['due_at']);
    }
}
