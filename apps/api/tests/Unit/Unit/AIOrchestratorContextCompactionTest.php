<?php

namespace Tests\Unit\Unit;

use App\AI\Orchestration\AIOrchestrator;
use Tests\TestCase;

class AIOrchestratorContextCompactionTest extends TestCase
{
    public function test_oversized_continuation_uses_a_bounded_authoritative_snapshot(): void
    {
        config()->set('ai.context.max_serialized_characters', 8000);
        $orchestrator = app(AIOrchestrator::class);
        $method = new \ReflectionMethod($orchestrator, 'toolLoopDynamicContext');
        $longText = str_repeat('context-', 250);
        $context = [
            'operational_context' => [
                'workspace_id' => '01jworkspace',
                'historical_candidate_sets' => array_fill(0, 100, ['label' => $longText]),
                'historical_entity_refs' => array_fill(0, 100, ['snapshot' => $longText]),
                'active_entity_refs' => array_fill(0, 30, ['id' => '01jentity', 'snapshot' => $longText]),
                'objective' => [
                    'id' => '01jobjective',
                    'revision' => 3,
                    'status' => 'running',
                    'description' => $longText,
                    'operation_count' => 200,
                    'completed_count' => 125,
                    'pending_count' => 75,
                    'expected_results' => collect(range(1, 200))->map(fn (int $index): array => [
                        'result_key' => 'result_'.$index,
                        'label' => $longText,
                        'required' => true,
                    ])->all(),
                ],
                'execution_plan' => [
                    'id' => '01jplan',
                    'status' => 'running',
                    'operation_count' => 200,
                    'steps' => collect(range(1, 200))->map(fn (int $index): array => [
                        'step_key' => 'step_'.$index,
                        'action_key' => 'tasks.update',
                        'status' => 'pending',
                        'input' => ['unbounded' => $longText],
                    ])->all(),
                ],
            ],
            'temporal_context' => ['timezone' => 'America/New_York'],
        ];

        $snapshot = $method->invoke($orchestrator, $context);
        $serialized = json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertIsString($serialized);
        $this->assertLessThanOrEqual(8000, strlen($serialized));
        $this->assertArrayNotHasKey('historical_candidate_sets', $snapshot['operational_context']);
        $this->assertArrayNotHasKey('historical_entity_refs', $snapshot['operational_context']);
        $this->assertSame('01jobjective', $snapshot['operational_context']['objective']['id']);
        $this->assertSame(125, $snapshot['operational_context']['objective']['completed_count']);
        $this->assertArrayNotHasKey('input', $snapshot['operational_context']['execution_plan']['steps'][0] ?? []);
    }
}
