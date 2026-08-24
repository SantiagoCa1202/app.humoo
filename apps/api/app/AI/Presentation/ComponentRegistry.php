<?php

namespace App\AI\Presentation;

class ComponentRegistry
{
    public const COMPONENTS = [
        'clarification.options@1',

        'events.list@1',
        'events.summary@1',

        'prep.list@1',
        'prep.preview@1',
        'prep.weekly-board@1',

        'action.preview@1',
        'action.confirm@1',
        'action.result@1',

        'tasks.mine@1',

        'inventory.missing@1',

        'menus.detail@1',
        'menus.list@1',

        'error.recovery@1',
    ];

    public static function canonicalKey(
        string $component,
        int $schemaVersion
    ): string {
        return "{$component}@{$schemaVersion}";
    }

    public static function supports(
        string $key
    ): bool {
        return in_array(
            $key,
            self::COMPONENTS,
            true
        );
    }

    public static function supportsComponent(
        string $component,
        int $schemaVersion
    ): bool {
        return self::supports(
            self::canonicalKey($component, $schemaVersion)
        );
    }
}
