<?php

namespace App\AI\Orchestration;

final class ToolLoopResultComposer
{
    /**
     * Keeps user-facing read components visible when a later tool produces the
     * final result. Internal resolver reads can opt out with `visible=false`.
     * A terminal orchestration response owns the final prose, so stale text
     * fallbacks from intermediate reads are not persisted beside it.
     *
     * @param array<int, array<string, mixed>> $supportingResults
     * @param array<string, mixed> $latestResult
     * @return array<string, mixed>
     */
    public static function compose(array $supportingResults, array $latestResult): array
    {
        $blocks = [];
        $entityRefs = [];
        $terminalResponse = data_get($latestResult, 'tool.key') === 'orchestration.respond';

        foreach ($supportingResults as $result) {
            if (($result['visible'] ?? true) === false) {
                continue;
            }

            if ($terminalResponse) {
                $result['blocks'] = collect((array) ($result['blocks'] ?? []))
                    ->reject(static fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'text')
                    ->values()
                    ->all();
            }

            self::appendResult($blocks, $entityRefs, $result);
        }
        self::appendResult($blocks, $entityRefs, $latestResult);

        $latestResult['blocks'] = self::uniqueArrays($blocks);
        if ($entityRefs !== []) {
            $latestResult['entity_refs'] = self::uniqueArrays($entityRefs);
        }

        return $latestResult;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<int, array<string, mixed>> $entityRefs
     * @param array<string, mixed> $result
     */
    private static function appendResult(array &$blocks, array &$entityRefs, array $result): void
    {
        foreach ((array) ($result['blocks'] ?? []) as $block) {
            if (is_array($block)) {
                $blocks[] = $block;
            }
        }

        foreach ((array) ($result['entity_refs'] ?? []) as $entityRef) {
            if (is_array($entityRef)) {
                $entityRefs[] = $entityRef;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private static function uniqueArrays(array $items): array
    {
        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $key = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $key = $key === false ? serialize($item) : $key;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
    }
}
