<?php

namespace App\AI\Capabilities;

/**
 * Canonical result of conversational interpretation.
 *
 * It deliberately contains no intent aliases or executable state. The caller
 * still has to pass validation, reference resolution and confirmation before
 * ToolExecutor can perform any operation.
 */
final class CapabilityCall
{
    /** @var array<string, mixed> */
    public array $arguments;

    public string $actionKey;
    public string $source;
    public float $confidence;
    public ?string $providerCallId;
    public string $correlationId;
    /** @var array<string, array<string, mixed>> */
    public array $usage;

    /** @param array<string, mixed> $arguments @param array<string, array<string, mixed>> $usage */
    public function __construct(string $actionKey, array $arguments, string $source, float $confidence, ?string $providerCallId, string $correlationId, array $usage = [])
    {
        $this->actionKey = $actionKey;
        $this->arguments = $arguments;
        $this->source = $source;
        $this->confidence = $confidence;
        $this->providerCallId = $providerCallId;
        $this->correlationId = $correlationId;
        $this->usage = $usage;
    }
}
