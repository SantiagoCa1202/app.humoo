<?php

namespace App\AI\Tools;

final class ToolProfileSelector
{
    /**
     * Keep the complete runtime registry available to the model.
     *
     * Intent, module selection, entity resolution, dependencies, and turn
     * sequencing belong to the model. This compatibility boundary therefore
     * does not classify text locally with keywords, regular expressions, or a
     * competing parser.
     *
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $metadata
     * @return array{profile:string, metadata:array<int, array<string, mixed>>}
     */
    public function select(array $context, array $metadata): array
    {
        return ['profile' => 'all', 'metadata' => $metadata];
    }
}
