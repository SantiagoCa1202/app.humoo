<?php

namespace App\AI\Menu;

use Illuminate\Support\Str;

class MenuDraftParser
{
    public function parse(string $message): array
    {
        $sourceText = trim($message);
        $requestedGuestCount = $this->extractGuestCount($sourceText);
        $grouped = $this->requestsHotColdGroups($sourceText);
        [$name, $body] = $this->extractNameAndBody($sourceText);
        $sourceItems = $this->extractItems($body);
        $excludedItems = $this->extractExclusions($sourceText);

        $items = collect($sourceItems)
            ->map(function (string $item) use ($excludedItems, $grouped): array {
                $excluded = $this->matchesExclusion($item, $excludedItems);
                $classification = $grouped
                    ? $this->classify($item)
                    : 'OTHER';

                return [
                    'name' => $item,
                    'classification' => $classification,
                    'included' => !$excluded,
                    'exclusion_reason' => $excluded ? 'user_requested_exclusion' : null,
                ];
            })
            ->filter(fn (array $item): bool => $item['included'])
            ->values();

        $sections = $this->buildSections($items->all(), $grouped);

        return [
            'name' => $name,
            'sections' => $sections,
            'excluded_items' => $excludedItems,
            'requested_guest_count' => $requestedGuestCount,
            'source' => [
                'type' => 'text',
                'text' => $sourceText,
            ],
            'warnings' => $grouped && $items->contains(fn (array $item): bool => $item['classification'] === 'UNKNOWN')
                ? ['Some items could not be classified confidently as hot or cold.']
                : [],
        ];
    }

    private function extractNameAndBody(string $sourceText): array
    {
        if (preg_match('/(?:crea(?:r)?|create|make|build)\s+(?:un\s+|a\s+)?men[uÃº]\s+(?<name>.+?)\s+(?:y\s+)?(?:a[ñn]ade|agrega|incluye|add|include)\s+(?<items>.+)$/iu', $sourceText, $matches) === 1) {
            return [
                $this->cleanName((string) $matches['name']),
                trim((string) $matches['items']),
            ];
        }

        $lines = preg_split('/\R/u', $sourceText) ?: [];
        $commandLineFound = false;

        foreach ($lines as $index => $line) {
            $line = trim((string) $line);

            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }

            [$candidate, $remainder] = array_pad(explode(':', $line, 2), 2, '');
            $candidate = trim($candidate);

            if ($this->looksLikeCommandPrefix($candidate)) {
                $inlineParts = array_pad(explode(':', trim($remainder), 2), 2, '');
                $inlineName = trim((string) $inlineParts[0]);

                if ($inlineName !== '' && !$this->looksLikeCommandPrefix($inlineName)) {
                    $bodyLines = [trim((string) ($inlineParts[1] ?? ''))];

                    foreach (array_slice($lines, $index + 1) as $bodyLine) {
                        $bodyLines[] = (string) $bodyLine;
                    }

                    return [$this->cleanName($inlineName), implode("\n", $bodyLines)];
                }

                $commandLineFound = true;
                continue;
            }

            $name = $this->cleanName($candidate);
            $bodyLines = [];

            if (trim($remainder) !== '') {
                $bodyLines[] = trim($remainder);
            }

            foreach (array_slice($lines, $index + 1) as $bodyLine) {
                $bodyLines[] = (string) $bodyLine;
            }

            return [$name, implode("\n", $bodyLines)];
        }

        if (!$commandLineFound && isset($lines[0])) {
            $firstLine = trim((string) $lines[0]);
            $nameMatch = preg_match('/(?:menu|menú)\s+(?:named|called|llamado|de nombre)\s+(.+)/iu', $firstLine, $matches);

            if ($nameMatch === 1) {
                return [$this->cleanName((string) $matches[1]), ''];
            }
        }

        return [null, $sourceText];
    }

    private function extractItems(string $body): array
    {
        $items = [];

        foreach (preg_split('/\R/u', $body) ?: [] as $line) {
            $line = trim((string) $line);
            $line = preg_replace('/^[-*•]\s*/u', '', $line) ?? $line;
            $line = preg_replace('/^\d+[.)]\s*/u', '', $line) ?? $line;

            if ($line === '' || $this->isSectionHeading($line)) {
                continue;
            }

            foreach (preg_split('/\s*[,;]\s*/u', $line) ?: [] as $candidate) {
                $candidate = trim((string) $candidate, " \t\n\r\0\x0B.-");

                if ($candidate !== '' && !$this->isInstructionText($candidate)) {
                    $items[] = $candidate;
                }
            }
        }

        return collect($items)
            ->unique(fn (string $item): string => $this->normalize($item))
            ->values()
            ->all();
    }

    private function extractExclusions(string $sourceText): array
    {
        if (preg_match('/(?:omita|omite|omit|exclude|excluir)\s+(.+?)(?=\.|$)/iu', $sourceText, $matches) !== 1) {
            return [];
        }

        return collect(preg_split('/\s*(?:,|\by\b|\band\b)\s*/iu', (string) $matches[1]) ?: [])
            ->map(fn (string $item): string => trim($item, " \t\n\r\0\x0B.,;:"))
            ->filter()
            ->unique(fn (string $item): string => $this->normalize($item))
            ->values()
            ->all();
    }

    private function buildSections(array $items, bool $grouped): array
    {
        if (!$grouped) {
            return [[
                'name' => 'Menu items',
                'type' => 'other',
                'items' => array_map(fn (array $item): array => $this->toMenuItem($item), $items),
            ]];
        }

        $groups = [
            'COLD' => ['name' => 'Cold food', 'type' => 'cold', 'items' => []],
            'HOT' => ['name' => 'Hot food', 'type' => 'hot', 'items' => []],
            'OTHER' => ['name' => 'Other', 'type' => 'other', 'items' => []],
            'UNKNOWN' => ['name' => 'Unclassified', 'type' => 'unknown', 'items' => []],
        ];

        foreach ($items as $item) {
            $classification = $item['classification'] ?? 'UNKNOWN';
            $groups[$classification]['items'][] = $this->toMenuItem($item);
        }

        return array_values(array_filter($groups, fn (array $group): bool => $group['items'] !== []));
    }

    private function toMenuItem(array $item): array
    {
        [$name, $quantity, $unit] = $this->extractPerGuestQuantity($item['name']);

        return [
            'name' => $name,
            'type' => 'dish',
            'quantity_per_guest' => $quantity,
            'serving_unit' => $unit,
            'metadata' => [
                'ai_classification' => $item['classification'] ?? 'OTHER',
                'source' => 'chat_text',
            ],
        ];
    }

    private function extractPerGuestQuantity(string $value): array
    {
        $quantity = null;
        $unit = null;
        $name = trim($value);

        if (preg_match('/\b((?:\d+(?:\.\d+)?|\.\d+))\s*(lb|lbs|pound|pounds|kg|kilogram|kilograms|oz|ounce|ounces|piece|pieces|portion|portions)\s*(?:\/|per)\s*(?:person|people|guest|guests|pax)\b/iu', $name, $matches) === 1) {
            $quantity = (float) $matches[1];
            $unit = Str::lower($matches[2]);
            $name = trim(str_replace($matches[0], '', $name));
        }

        return [$name, $quantity, $unit];
    }

    private function classify(string $item): string
    {
        $normalized = $this->normalize($item);
        $hotMarkers = ['bacon', 'egg', 'eggs', 'potato', 'potatoes', 'roast', 'hot', 'soup', 'grill', 'chicken', 'beef', 'pasta', 'rice'];
        $coldMarkers = ['fruit', 'salad', 'danish', 'croissant', 'biscuit', 'jam', 'cold', 'yogurt', 'cheese', 'sandwich'];

        $hot = collect($hotMarkers)->contains(fn (string $marker): bool => str_contains($normalized, $marker));
        $cold = collect($coldMarkers)->contains(fn (string $marker): bool => str_contains($normalized, $marker));

        if ($hot && !$cold) {
            return 'HOT';
        }

        if ($cold && !$hot) {
            return 'COLD';
        }

        return $hot || $cold ? 'OTHER' : 'UNKNOWN';
    }

    private function matchesExclusion(string $item, array $excludedItems): bool
    {
        $itemTokens = $this->tokens($item);

        foreach ($excludedItems as $excludedItem) {
            $excludedTokens = $this->tokens($excludedItem);

            if ($excludedTokens !== [] && count(array_intersect($excludedTokens, $itemTokens)) === count($excludedTokens)) {
                return true;
            }
        }

        return false;
    }

    private function requestsHotColdGroups(string $sourceText): bool
    {
        $normalized = $this->normalize($sourceText);

        return (str_contains($normalized, 'fria') || str_contains($normalized, 'frio') || str_contains($normalized, 'cold'))
            && (str_contains($normalized, 'caliente') || str_contains($normalized, 'calientes') || str_contains($normalized, 'hot'));
    }

    private function extractGuestCount(string $sourceText): ?int
    {
        if (preg_match('/\b(?:para|for)\s+(\d+)\s+(?:personas|people|guests|pax)\b/iu', $sourceText, $matches) !== 1) {
            return null;
        }

        $count = (int) ($matches[1] ?? 0);

        return $count > 0 ? $count : null;
    }

    private function cleanName(string $name): ?string
    {
        $cleaned = trim($name, " \t\n\r\0\x0B.-:");
        $cleaned = preg_replace('/^(?:menu|menú)\s*(?:de|named|called|llamado)?\s*/iu', '', $cleaned) ?? $cleaned;

        return $cleaned !== '' ? $cleaned : null;
    }

    private function looksLikeCommandPrefix(string $value): bool
    {
        $normalized = $this->normalize($value);

        return str_contains($normalized, 'crea')
            || str_contains($normalized, 'crear')
            || str_contains($normalized, 'create')
            || str_contains($normalized, 'menu con')
            || str_contains($normalized, 'menu como')
            || str_contains($normalized, 'siguiente')
            || str_contains($normalized, 'following');
    }

    private function isSectionHeading(string $line): bool
    {
        return preg_match('/^(?:cold|hot|other|unknown|comida\s+(?:fr[ií]a|caliente)|fr[ií]a|caliente)\s*:?$/iu', $line) === 1;
    }

    private function isInstructionText(string $value): bool
    {
        $normalized = $this->normalize($value);

        return str_starts_with($normalized, 'crea un menu')
            || str_starts_with($normalized, 'create a menu')
            || str_starts_with($normalized, 'este menu')
            || str_starts_with($normalized, 'this menu');
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim(Str::ascii($value)));
    }

    private function tokens(string $value): array
    {
        return array_values(array_filter(preg_split('/\s+/u', $this->normalize($value)) ?: []));
    }
}
