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
        'prep.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'prep.items.list' => ['risk' => 'read', 'confirmation_required' => false],
        'prep.items.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'prep.generate' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'prep.regenerate' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'prep.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'prep.items.update' => ['risk' => 'low_write', 'confirmation_required' => true],
        'prep.items.complete' => ['risk' => 'low_write', 'confirmation_required' => true],
        'prep.items.reopen' => ['risk' => 'low_write', 'confirmation_required' => true],
        'prep.items.assign' => ['risk' => 'low_write', 'confirmation_required' => true],
        'prep.items.unassign' => ['risk' => 'low_write', 'confirmation_required' => true],
        'tasks.mine' => ['risk' => 'read', 'confirmation_required' => false],
        'tasks.list' => ['risk' => 'read', 'confirmation_required' => false],
        'tasks.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'tasks.search' => ['risk' => 'read', 'confirmation_required' => false],
        'tasks.read' => ['risk' => 'read', 'confirmation_required' => false],
        'menus.search' => ['risk' => 'read', 'confirmation_required' => false],
        'menus.show' => ['risk' => 'read', 'confirmation_required' => false],
        'menus.rename' => ['risk' => 'low_write', 'confirmation_required' => false],
        'menus.items.add' => ['risk' => 'low_write', 'confirmation_required' => false],
        'menus.items.move_section' => ['risk' => 'low_write', 'confirmation_required' => false],
        'recipes.list' => ['risk' => 'read', 'confirmation_required' => false],
        'recipes.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'recipes.versions' => ['risk' => 'read', 'confirmation_required' => false],
        'recipes.scale' => ['risk' => 'read', 'confirmation_required' => false],
        'prep_items.update' => ['risk' => 'low_write', 'confirmation_required' => true],
        'menus.create' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'tasks.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'tasks.create' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'tasks.assign' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'tasks.status.update' => ['risk' => 'low_write', 'confirmation_required' => true],
        'tasks.complete' => ['risk' => 'low_write', 'confirmation_required' => true],
        'tasks.delete' => ['risk' => 'destructive', 'confirmation_required' => true],
        'documents.list' => ['risk' => 'read', 'confirmation_required' => false],
        'documents.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'beos.list' => ['risk' => 'read', 'confirmation_required' => false],
        'beos.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'beos.versions' => ['risk' => 'read', 'confirmation_required' => false],
        'notifications.list' => ['risk' => 'read', 'confirmation_required' => false],
        'notifications.unread_count' => ['risk' => 'read', 'confirmation_required' => false],
        'notifications.read_all' => ['risk' => 'low_write', 'confirmation_required' => false],
        'notification_preferences.list' => ['risk' => 'read', 'confirmation_required' => false],
        'notification_preferences.update' => ['risk' => 'low_write', 'confirmation_required' => true],
        'workspace.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'workspace.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'members.list' => ['risk' => 'read', 'confirmation_required' => false],
        'members.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'members.invite' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'members.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'members.remove' => ['risk' => 'destructive', 'confirmation_required' => true],
        'teams.list' => ['risk' => 'read', 'confirmation_required' => false],
        'teams.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'teams.create' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'teams.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'teams.delete' => ['risk' => 'destructive', 'confirmation_required' => true],
        'teams.members.sync' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'stations.list' => ['risk' => 'read', 'confirmation_required' => false],
        'stations.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'stations.create' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'stations.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'stations.delete' => ['risk' => 'destructive', 'confirmation_required' => true],
        'shifts.list' => ['risk' => 'read', 'confirmation_required' => false],
        'shifts.detail' => ['risk' => 'read', 'confirmation_required' => false],
        'shifts.create' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'shifts.update' => ['risk' => 'impactful_write', 'confirmation_required' => true],
        'shifts.delete' => ['risk' => 'destructive', 'confirmation_required' => true],
        'availability.list' => ['risk' => 'read', 'confirmation_required' => false],
        'availability.sync' => ['risk' => 'impactful_write', 'confirmation_required' => true],
    ];

    public function resolve(string $actionKey): array
    {
        if (isset(self::POLICIES[$actionKey])) {
            return [
                'action_key' => $actionKey,
                ...self::POLICIES[$actionKey],
            ];
        }

        if (str_ends_with($actionKey, '.list') || str_ends_with($actionKey, '.detail')) {
            return [
                'action_key' => $actionKey,
                'risk' => 'read',
                'confirmation_required' => false,
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
