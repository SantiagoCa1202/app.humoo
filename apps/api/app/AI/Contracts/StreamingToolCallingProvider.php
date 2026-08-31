<?php

namespace App\AI\Contracts;

interface StreamingToolCallingProvider extends ToolCallingProvider
{
    /**
     * Run one tool-calling turn and expose only user-visible output text
     * deltas. Tool arguments and reasoning remain private to the backend.
     *
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $tools
     * @param array<int, array<string, mixed>> $input
     * @param callable(array<string, mixed>): void $onEvent
     * @return array<string, mixed>
     */
    public function streamToolTurn(
        array $context,
        array $tools,
        ?string $previousResponseId,
        array $input,
        callable $onEvent,
    ): array;
}
