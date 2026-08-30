<?php

namespace App\AI\Presentation;

final class ChatBlockPolicy
{
    /** @param array<int, mixed> $blocks @return array<int, array<string, mixed>> */
    public static function normalize(array $blocks): array
    {
        $normalized = array_values(array_filter(
            $blocks,
            static fn (mixed $block): bool => is_array($block)
        ));

        // Text remains part of the conversational contract even when a
        // remote component is present. It may contain the model's question,
        // dependency explanation, or a human-readable fallback.
        return $normalized;
    }
}
