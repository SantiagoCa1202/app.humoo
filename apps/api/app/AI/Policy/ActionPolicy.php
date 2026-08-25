<?php

namespace App\AI\Policy;

class ActionPolicy
{
    private const DEFAULT_POLICY = [
        'risk' => 'impactful_write',
        'confirmation_required' => true,
    ];

    private const POLICIES = [
        'events.list' => ['risk' => 'read', 'confirmation_required' => false],
        'prep.list' => ['risk' => 'read', 'confirmation_required' => false],
        'tasks.mine' => ['risk' => 'read', 'confirmation_required' => false],
        'menus.search' => ['risk' => 'read', 'confirmation_required' => false],
        'menus.show' => ['risk' => 'read', 'confirmation_required' => false],
        'menus.rename' => ['risk' => 'low_write', 'confirmation_required' => false],
        'menus.items.add' => ['risk' => 'low_write', 'confirmation_required' => false],
        'menus.items.move_section' => ['risk' => 'low_write', 'confirmation_required' => false],
        'prep_items.update' => ['risk' => 'low_write', 'confirmation_required' => true],
        'menus.create' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'tasks.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'tasks.create' => ['risk' => 'impactful_write', 'confirmation_required' => true],
    ];

    public function resolve(string $actionKey): array
    {
        if (isset(self::POLICIES[$actionKey])) {
            return [
                'action_key' => $actionKey,
                ...self::POLICIES[$actionKey],
            ];
        }

        if (str_ends_with($actionKey, '.delete') || str_contains($actionKey, '.delete.')) {
            return [
                'action_key' => $actionKey,
                'risk' => 'destructive',
                'confirmation_required' => true,
            ];
        }

        return [
            'action_key' => $actionKey,
            ...self::DEFAULT_POLICY,
        ];
    }

    public function requiresConfirmation(string $actionKey): bool
    {
        return (bool) $this->resolve($actionKey)['confirmation_required'];
    }
}
