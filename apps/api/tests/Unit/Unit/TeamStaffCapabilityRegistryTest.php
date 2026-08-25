<?php

namespace Tests\Unit\Unit;

use App\AI\Providers\RuleBasedAIProvider;
use App\AI\Tools\ToolRegistry;
use Tests\TestCase;

class TeamStaffCapabilityRegistryTest extends TestCase
{
    public function test_team_staff_reads_and_writes_are_registered_with_confirmation_policy(): void
    {
        $registry = new ToolRegistry();

        $this->assertSame('read', $registry->resolve('teams.list')['mode']);
        $this->assertFalse($registry->resolve('stations.list')['requires_confirmation']);
        $this->assertTrue($registry->resolve('shifts.create')['requires_confirmation']);
        $this->assertTrue($registry->resolve('availability.sync')['requires_confirmation']);
        $this->assertSame('team_staff', $registry->resolve('teams.create')['module']);
    }

    public function test_clear_team_staff_language_is_routed_without_inventing_a_domain_action(): void
    {
        $provider = new RuleBasedAIProvider();

        $teams = $provider->generate(['message' => 'muéstrame los equipos', 'locale' => 'es']);
        $station = $provider->generate(['message' => 'crea una estación llamada Cold Food', 'locale' => 'es']);
        $shift = $provider->generate(['message' => 'crea un shift para John mañana de 8 a 4', 'locale' => 'es']);

        $this->assertSame('tool_action', $teams['intent']);
        $this->assertSame('teams.list', $teams['slots']['action_key']);
        $this->assertSame('stations.create', $station['slots']['action_key']);
        $this->assertSame('Cold Food', $station['slots']['name']);
        $this->assertSame('shifts.create', $shift['slots']['action_key']);
        $this->assertSame('John', $shift['slots']['member_search']);
        $this->assertNotNull($shift['slots']['starts_at']);
        $this->assertNotNull($shift['slots']['ends_at']);
    }
}
