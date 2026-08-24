<?php

namespace Tests\Unit\Unit;

use App\AI\Providers\RuleBasedAIProvider;
use Tests\TestCase;

class UnsupportedCapabilityProviderTest extends TestCase
{
    public function test_rule_based_provider_classifies_a_clear_unavailable_operation(): void
    {
        $decision = (new RuleBasedAIProvider())->generate([
            'locale' => 'en',
            'message' => 'Send the prep list to the supplier.',
        ]);

        $this->assertSame('unsupported_capability', $decision['intent']);
        $this->assertSame(
            'purchasing.send_prep_to_supplier',
            $decision['slots']['normalized_key']
        );
    }

    public function test_rule_based_provider_keeps_general_questions_out_of_capability_requests(): void
    {
        $decision = (new RuleBasedAIProvider())->generate([
            'locale' => 'en',
            'message' => 'How does sending prep to the supplier work?',
        ]);

        $this->assertSame('clarify_scope', $decision['intent']);
    }
}
