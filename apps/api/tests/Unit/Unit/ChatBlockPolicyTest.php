<?php

namespace Tests\Unit\Unit;

use App\AI\Presentation\ChatBlockPolicy;
use Tests\TestCase;

class ChatBlockPolicyTest extends TestCase
{
    public function test_remote_component_responses_preserve_plain_text_blocks(): void
    {
        $blocks = ChatBlockPolicy::normalize([
            ['type' => 'text', 'text' => 'The recipe is ready.'],
            ['type' => 'component', 'component' => 'recipes.list', 'schema_version' => 1],
        ]);

        $this->assertCount(2, $blocks);
        $this->assertSame('text', $blocks[0]['type']);
        $this->assertSame('component', $blocks[1]['type']);
    }

    public function test_text_only_recovery_responses_are_preserved(): void
    {
        $blocks = ChatBlockPolicy::normalize([
            ['type' => 'text', 'text' => 'Please clarify your request.'],
        ]);

        $this->assertCount(1, $blocks);
        $this->assertSame('text', $blocks[0]['type']);
    }
}
