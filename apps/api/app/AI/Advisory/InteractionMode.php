<?php

namespace App\AI\Advisory;

final class InteractionMode
{
    public const READ = 'read';
    public const ACTION = 'action';
    public const ADVISORY = 'advisory';
    public const GENERATIVE = 'generative';

    public static function isAdvisory(string $mode): bool
    {
        return in_array($mode, [self::ADVISORY, self::GENERATIVE], true);
    }
}
