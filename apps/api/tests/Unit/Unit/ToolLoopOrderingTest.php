<?php

namespace Tests\Unit\Unit;

use App\AI\Orchestration\AIOrchestrator;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ToolLoopOrderingTest extends TestCase
{
    public function test_legacy_semantic_services_cannot_be_reenabled_by_configuration(): void
    {
        $orchestrator = (new ReflectionClass(AIOrchestrator::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AIOrchestrator::class, 'legacySemanticServices');
        $method->setAccessible(true);

        config(['ai.routing.tool_loop_enabled' => false]);
        $this->expectException(\LogicException::class);
        $method->invoke($orchestrator);
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

    public function test_generic_update_fields_are_nullable_for_strict_function_calls(): void
    {
        $orchestrator = (new ReflectionClass(AIOrchestrator::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AIOrchestrator::class, 'toolLoopDefinitions');
        $method->setAccessible(true);

        $definitions = $method->invoke($orchestrator);
        $taskDefinition = collect($definitions)->firstWhere('name', 'tasks_update');

        $this->assertSame(['string', 'null'], $taskDefinition['parameters']['properties']['priority']['type']);
        $this->assertSame(['string', 'null'], $taskDefinition['parameters']['properties']['status']['type']);
    }

    #[DataProvider('taskCreateRelationshipSearchProvider')]
    public function test_task_creation_allows_resolver_backed_relationship_searches(string $searchKey): void
    {
        $orchestrator = (new ReflectionClass(AIOrchestrator::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AIOrchestrator::class, 'toolLoopReferenceError');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($orchestrator, [
            'key' => 'tasks.create',
            'operation_type' => 'create',
            'target_entity_required' => false,
            'reference_fields' => ['membership_id', 'member_search', 'team_id', 'team_search', 'station_id', 'station_search', 'event_id', 'event_search'],
        ], [
            'title' => 'Revisar inventario',
            $searchKey => 'referencia natural',
        ]));
    }

    public function test_task_updates_allow_resolver_backed_relationship_searches_with_a_task_selector(): void
    {
        $orchestrator = (new ReflectionClass(AIOrchestrator::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(AIOrchestrator::class, 'toolLoopReferenceError');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($orchestrator, [
            'key' => 'tasks.update',
            'operation_type' => 'update',
            'target_entity_required' => true,
            'reference_fields' => [],
        ], [
            'task_search' => 'freezer',
            'member_search' => 'Santiago',
            'starts_at' => '2026-08-31T09:30:00-04:00',
        ]));
    }

    /** @return array<string, array{string}> */
    public static function taskCreateRelationshipSearchProvider(): array
    {
        return [
            'member' => ['member_search'],
            'team' => ['team_search'],
            'station' => ['station_search'],
            'event' => ['event_search'],
        ];
    }
}
