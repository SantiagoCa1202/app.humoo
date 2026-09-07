<?php

namespace Tests\Unit\Unit;

use App\AI\Tools\ToolRegistry;
use App\Models\AiRun;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiAcceptanceSecurityTest extends TestCase
{
    public function test_ai_run_metadata_is_cast_as_json(): void
    {
        $this->assertSame('array', (new AiRun)->getCasts()['metadata']);
    }

    public function test_registered_domain_writes_are_confirmation_gated_and_cancellation_is_immediate(): void
    {
        $metadata = (new ToolRegistry)->allMetadata();

        $writeTools = array_values(array_filter(
            $metadata,
            static fn (array $tool): bool => $tool['mode'] === 'write'
        ));

        $this->assertNotEmpty($writeTools);
        foreach ($writeTools as $tool) {
            in_array($tool['key'], ['objectives.cancel', 'objectives.define'], true)
                ? $this->assertFalse($tool['requires_confirmation'], $tool['key'])
                : $this->assertTrue($tool['requires_confirmation'], $tool['key']);
        }
    }

    public function test_unknown_tools_are_rejected_by_the_allowlist(): void
    {
        $this->expectException(ValidationException::class);

        (new ToolRegistry)->resolve('arbitrary.database.write');
    }
}
