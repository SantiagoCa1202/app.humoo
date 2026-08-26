<?php

namespace App\AI\Recipes;

/** Parses the quantity formats commonly found in pasted recipes without AI. */
class FractionNormalizer
{
    private const UNICODE_FRACTIONS = [
        '½' => '1/2', '¼' => '1/4', '¾' => '3/4', '⅓' => '1/3', '⅔' => '2/3',
        '⅛' => '1/8', '⅜' => '3/8', '⅝' => '5/8', '⅞' => '7/8',
    ];

    public function parse(string $value): ?float
    {
        $value = trim(strtr($value, self::UNICODE_FRACTIONS));
        $value = preg_replace('/(?<=\d)(?=\d\/\d)/', ' ', $value) ?? $value;
        $value = preg_replace('/^(\d+)\s*-\s*(\d+\/\d+)$/', '$1 $2', $value) ?? $value;

        if (preg_match('/^(\d+(?:[.,]\d+)?)\s+(\d+)\/(\d+)$/', $value, $matches)) {
            return (float) str_replace(',', '.', $matches[1]) + ((float) $matches[2] / (float) $matches[3]);
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $value, $matches)) {
            return (float) $matches[1] / (float) $matches[2];
        }
        if (is_numeric(str_replace(',', '.', $value))) {
            return (float) str_replace(',', '.', $value);
        }

        return null;
    }

    /** @return array{min: float, max: float}|null */
    public function parseRange(string $value): ?array
    {
        $value = trim(strtr($value, self::UNICODE_FRACTIONS));
        $value = preg_replace('/(?<=\d)(?=\d\/\d)/', ' ', $value) ?? $value;

        if (!preg_match('/^(.+?)\s*(?:–|—|\bto\b|\ba\b|-)\s*(.+)$/iu', $value, $matches)) {
            return null;
        }
        $min = $this->parse(trim($matches[1]));
        $max = $this->parse(trim($matches[2]));

        return $min !== null && $max !== null && $max >= $min ? ['min' => $min, 'max' => $max] : null;
    }

    public function normalize(string $value): ?float
    {
        return $this->parse($value);
    }
}
