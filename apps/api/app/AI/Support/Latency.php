<?php

namespace App\AI\Support;

final class Latency
{
    public static function fromSeconds(float $startedAt, float $endedAt): int
    {
        if (!is_finite($startedAt) || !is_finite($endedAt)) {
            return 0;
        }

        return self::normalize(($endedAt - $startedAt) * 1000);
    }

    public static function fromNanoseconds(int $startedAt, int $endedAt): int
    {
        return self::normalize(($endedAt - $startedAt) / 1_000_000);
    }

    public static function normalize(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        $numeric = (float) $value;

        if (!is_finite($numeric)) {
            return 0;
        }

        return max(0, (int) round($numeric));
    }
}
