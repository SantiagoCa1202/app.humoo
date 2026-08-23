<?php

namespace Tests\Unit\Unit;

use App\AI\Tools\ToolRegistry;
use App\Models\AiRun;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class AiAcceptanceSecurityTest extends TestCase
{
    public function test_ai_run_metadata_is_cast_as_json(): void
    {
        $this->assertSame('array', (new AiRun)->getCasts()['metadata']);
    }

    public function test_registered_write_tools_are_confirmation_gated(): void
    {
        $metadata = (new ToolRegistry)->allMetadata();

        $writeTools = array_values(array_filter(
            $metadata,
            static fn (array $tool): bool => $tool['mode'] === 'write'
        ));

        $this->assertNotEmpty($writeTools);
        foreach ($writeTools as $tool) {
            $this->assertTrue($tool['requires_confirmation'], $tool['key']);
        }
    }

    public function test_unknown_tools_are_rejected_by_the_allowlist(): void
    {
        $this->expectException(ValidationException::class);

        (new ToolRegistry)->resolve('arbitrary.database.write');
    }
}
