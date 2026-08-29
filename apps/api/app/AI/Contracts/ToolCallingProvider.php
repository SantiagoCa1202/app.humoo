<?php

namespace App\AI\Contracts;

interface ToolCallingProvider
{
    /**
     * Run one Responses API turn. A turn may return function calls or final
     * assistant text. Persistent turns use the Conversations API and send
     * tool results as new input items.
     *
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $tools
     * @param array<int, array<string, mixed>> $input
     * @return array<string, mixed>
     */
    public function toolTurn(
        array $context,
        array $tools,
        ?string $previousResponseId = null,
        array $input = []
    ): array;
}
