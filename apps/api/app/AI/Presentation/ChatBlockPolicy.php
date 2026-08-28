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

        $hasRemoteComponent = collect($normalized)->contains(
            static fn (array $block): bool => ($block['type'] ?? null) === 'component'
        );

        if (!$hasRemoteComponent) {
            return $normalized;
        }

        return array_values(array_filter(
            $normalized,
            static fn (array $block): bool => ($block['type'] ?? 'text') !== 'text'
        ));
    }
}
