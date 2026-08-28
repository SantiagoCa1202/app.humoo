<?php

namespace App\AI\Contracts;

interface ToolCallingProvider
{
    /**
     * Run one Responses API turn. A turn may return function calls or final
     * assistant text. Tool results are sent back using previous_response_id.
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
