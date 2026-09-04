<?php

namespace Tests\Unit\Unit;

use App\AI\Tools\ToolObservation;
use Tests\TestCase;

class ToolObservationTest extends TestCase
{
    public function test_it_exposes_one_uniform_observation_contract_with_legacy_aliases(): void
    {
        $result = ToolObservation::make(
            false,
            'VALIDATION_FAILED',
            'Correct the arguments.',
            ['missing_fields' => ['title']],
            ['validation_failed' => true, 'recoverable' => true],
            ['correct_arguments'],
        );

        $this->assertSame(['ok', 'data', 'error', 'signals', 'meta', 'code', 'message_for_model', 'retryable', 'allowed_next_actions', 'safe_details'], array_keys($result));
        $this->assertSame('VALIDATION_FAILED', $result['error']['code']);
        $this->assertTrue($result['signals']['validation_failed']);
        $this->assertTrue($result['signals']['recoverable']);
        $this->assertSame($result['data'], $result['safe_details']);
    }
}
