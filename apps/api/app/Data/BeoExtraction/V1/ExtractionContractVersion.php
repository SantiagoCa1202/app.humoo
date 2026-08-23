<?php

namespace App\Data\BeoExtraction\V1;

final class ExtractionContractVersion
{
    public const INITIAL = '1.0.0';

    public static function supports(string $version): bool
    {
        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches)) {
            return false;
        }

        return (int) $matches[1] === 1;
    }
}
