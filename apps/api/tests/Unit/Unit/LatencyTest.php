<?php

namespace Tests\Unit\Unit;

use App\AI\Support\Latency;
use Tests\TestCase;

class LatencyTest extends TestCase
{
    public function test_negative_wall_clock_delta_is_clamped_without_failing(): void
    {
        $this->assertSame(0, Latency::fromSeconds(100.5, 100.25));
        $this->assertSame(0, Latency::fromNanoseconds(2_000_000, 1_000_000));
    }

    public function test_invalid_or_fractional_values_are_normalized_for_unsigned_storage(): void
    {
        $this->assertSame(222, Latency::normalize(222.265));
        $this->assertSame(0, Latency::normalize(-1));
        $this->assertSame(0, Latency::normalize(NAN));
    }
}
