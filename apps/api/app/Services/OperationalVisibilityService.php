<?php

namespace App\Services;

use App\Models\EventFunction;
use App\Models\WorkspaceMembership;

class OperationalVisibilityService
{
    public const DEFAULTS = [
        'show_food_functions' => true,
        'show_beverage_only_functions' => true,
        'show_functions_without_fnb' => false,
        'show_setup_only' => true,
        'show_offices' => false,
        'show_storage' => false,
        'show_registration' => false,
        'show_av_only' => false,
        'show_all' => false,
    ];

    public function settings(WorkspaceMembership $membership): array
    {
        return array_replace(
            self::DEFAULTS,
            $membership->workspace?->operational_visibility_defaults ?? [],
            $membership->operational_visibility_overrides ?? [],
        );
    }

    public function visibleTo(
        WorkspaceMembership $membership,
        EventFunction $eventFunction,
        bool $includeHidden = false
    ): bool {
        if ($includeHidden) {
            return true;
        }

        $settings = $this->settings($membership);

        if ($settings['show_all']) {
            return true;
        }

        $signals = $eventFunction->operational_signals ?? [];
        $category = strtoupper((string) ($eventFunction->operational_category ?? ''));
        $hasFood = (bool) ($signals['has_food'] ?? false);
        $hasBeverage = (bool) ($signals['has_beverage'] ?? false);

        if ($hasFood && $settings['show_food_functions']) {
            return true;
        }

        if ($hasBeverage && !$hasFood && $settings['show_beverage_only_functions']) {
            return true;
        }

        if (!$hasFood && !$hasBeverage) {
            if ($settings['show_functions_without_fnb']) {
                return true;
            }

            return match ($category) {
                'SETUP' => (bool) $settings['show_setup_only'],
                'OFFICE' => (bool) $settings['show_offices'],
                'STORAGE' => (bool) $settings['show_storage'],
                'REGISTRATION' => (bool) $settings['show_registration'],
                'AV' => (bool) $settings['show_av_only'],
                default => false,
            };
        }

        return false;
    }
}
