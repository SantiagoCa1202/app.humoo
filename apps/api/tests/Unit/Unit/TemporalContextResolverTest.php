<?php

namespace Tests\Unit\Unit;

use App\AI\Temporal\TemporalContextResolver;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TemporalContextResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_workspace_timezone_builds_the_authoritative_temporal_snapshot(): void
    {
        CarbonImmutable::setTestNow('2026-08-29 19:46:00 UTC');
        $snapshot = app(TemporalContextResolver::class)->resolve(
            new Workspace(['id' => 'workspace-1', 'timezone' => 'America/New_York']),
            new User(['id' => 'user-1', 'timezone' => 'America/Chicago']),
            'es',
        );

        $this->assertSame('2026-08-29T19:46:00Z', $snapshot['current_utc_datetime']);
        $this->assertSame('2026-08-29T15:46:00-04:00', $snapshot['current_local_datetime']);
        $this->assertSame('2026-08-29', $snapshot['current_local_date']);
        $this->assertSame('15:46', $snapshot['current_local_time']);
        $this->assertSame('America/New_York', $snapshot['timezone']);
        $this->assertSame('workspace', $snapshot['timezone_source']);
        $this->assertSame('es', $snapshot['locale']);
    }

    public function test_active_event_timezone_has_priority_over_workspace_and_user(): void
    {
        $snapshot = app(TemporalContextResolver::class)->resolve(
            new Workspace(['timezone' => 'America/New_York']),
            new User(['timezone' => 'America/Chicago']),
            'en',
            [['type' => 'event', 'snapshot' => ['timezone' => 'America/Los_Angeles']]],
        );

        $this->assertSame('America/Los_Angeles', $snapshot['timezone']);
        $this->assertSame('entity', $snapshot['timezone_source']);
    }

    public function test_user_timezone_is_used_when_workspace_timezone_is_missing(): void
    {
        $snapshot = app(TemporalContextResolver::class)->resolve(
            new Workspace(['timezone' => null]),
            new User(['timezone' => 'America/New_York']),
        );

        $this->assertSame('America/New_York', $snapshot['timezone']);
        $this->assertSame('user', $snapshot['timezone_source']);
    }

    public function test_configured_fallback_is_used_only_when_trusted_timezones_are_missing(): void
    {
        config()->set('ai.temporal.fallback_timezone', 'America/Los_Angeles');
        $snapshot = app(TemporalContextResolver::class)->resolve(
            new Workspace(['timezone' => null]),
            new User(['timezone' => null]),
        );

        $this->assertSame('America/Los_Angeles', $snapshot['timezone']);
        $this->assertSame('fallback', $snapshot['timezone_source']);
    }

    public function test_invalid_stored_timezones_are_not_accepted_as_authoritative(): void
    {
        config()->set('ai.temporal.fallback_timezone', 'UTC');
        $snapshot = app(TemporalContextResolver::class)->resolve(
            new Workspace(['timezone' => 'Not/AZone']),
            new User(['timezone' => 'Still/Not/AZone']),
        );

        $this->assertSame('UTC', $snapshot['timezone']);
        $this->assertSame('fallback', $snapshot['timezone_source']);
    }

    public function test_snapshot_recalculates_the_clock_for_each_turn(): void
    {
        $resolver = app(TemporalContextResolver::class);
        $workspace = new Workspace(['timezone' => 'America/New_York']);
        $user = new User();

        CarbonImmutable::setTestNow('2026-08-29 19:46:00 UTC');
        $first = $resolver->resolve($workspace, $user);
        CarbonImmutable::setTestNow('2026-08-31 19:46:00 UTC');
        $second = $resolver->resolve($workspace, $user);

        $this->assertSame('2026-08-29', $first['current_local_date']);
        $this->assertSame('2026-08-31', $second['current_local_date']);
    }
}
