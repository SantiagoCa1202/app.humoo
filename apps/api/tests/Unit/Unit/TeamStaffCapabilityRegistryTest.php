<?php

namespace Tests\Unit\Unit;

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

}
