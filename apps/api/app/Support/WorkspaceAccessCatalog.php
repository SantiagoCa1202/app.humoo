<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

class WorkspaceAccessCatalog
{
    public function ensurePermissions(): Collection
    {
        foreach ($this->permissionDefinitions() as $permission) {
            Permission::query()->updateOrCreate(
                [
                    'key' => $permission['key'],
                ],
                [
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                ]
            );
        }

        return Permission::query()
            ->whereIn('key', array_column($this->permissionDefinitions(), 'key'))
            ->get()
            ->keyBy('key');
    }

    public function ensureRoles(): Collection
    {
        foreach ($this->roleDefinitions() as $role) {
            Role::query()->updateOrCreate(
                [
                    'workspace_id' => null,
                    'key' => $role['key'],
                ],
                [
                    'name' => $role['name'],
                    'is_system' => true,
                ]
            );
        }

        return Role::query()
            ->whereNull('workspace_id')
            ->whereIn('key', $this->roleKeys())
            ->get()
            ->keyBy('key');
    }

    public function ensureSystemCatalog(): array
    {
        $permissions = $this->ensurePermissions();
        $roles = $this->ensureRoles();

        foreach ($this->rolePermissionMap() as $roleKey => $permissionKeys) {
            $role = $roles->get($roleKey);

            if (!$role) {
                continue;
            }

            $role->permissions()->sync(
                collect($permissionKeys)
                    ->map(fn (string $permissionKey): ?string => $permissions->get($permissionKey)?->id)
                    ->filter()
                    ->values()
                    ->all(),
            );
        }

        return [
            'permissions' => $permissions,
            'roles' => $roles,
        ];
    }

    public function roleKeys(): array
    {
        return array_column($this->roleDefinitions(), 'key');
    }

    private function permissionDefinitions(): array
    {
        return [
            ['key' => 'clients.view', 'module' => 'clients', 'action' => 'view'],
            ['key' => 'clients.create', 'module' => 'clients', 'action' => 'create'],
            ['key' => 'clients.edit', 'module' => 'clients', 'action' => 'edit'],
            ['key' => 'clients.delete', 'module' => 'clients', 'action' => 'delete'],
            ['key' => 'contacts.view', 'module' => 'contacts', 'action' => 'view'],
            ['key' => 'contacts.create', 'module' => 'contacts', 'action' => 'create'],
            ['key' => 'contacts.edit', 'module' => 'contacts', 'action' => 'edit'],
            ['key' => 'contacts.delete', 'module' => 'contacts', 'action' => 'delete'],
            ['key' => 'venues.view', 'module' => 'venues', 'action' => 'view'],
            ['key' => 'venues.create', 'module' => 'venues', 'action' => 'create'],
            ['key' => 'venues.edit', 'module' => 'venues', 'action' => 'edit'],
            ['key' => 'venues.delete', 'module' => 'venues', 'action' => 'delete'],
            ['key' => 'events.view', 'module' => 'events', 'action' => 'view'],
            ['key' => 'events.create', 'module' => 'events', 'action' => 'create'],
            ['key' => 'events.edit', 'module' => 'events', 'action' => 'edit'],
            ['key' => 'events.delete', 'module' => 'events', 'action' => 'delete'],
            ['key' => 'menus.view', 'module' => 'menus', 'action' => 'view'],
            ['key' => 'menus.create', 'module' => 'menus', 'action' => 'create'],
            ['key' => 'menus.edit', 'module' => 'menus', 'action' => 'edit'],
            ['key' => 'recipes.view', 'module' => 'recipes', 'action' => 'view'],
            ['key' => 'recipes.create', 'module' => 'recipes', 'action' => 'create'],
            ['key' => 'recipes.edit', 'module' => 'recipes', 'action' => 'edit'],
            ['key' => 'prep_lists.view', 'module' => 'prep_lists', 'action' => 'view'],
            ['key' => 'prep_lists.create', 'module' => 'prep_lists', 'action' => 'create'],
            ['key' => 'prep_lists.edit', 'module' => 'prep_lists', 'action' => 'edit'],
            ['key' => 'tasks.view', 'module' => 'tasks', 'action' => 'view'],
            ['key' => 'tasks.create', 'module' => 'tasks', 'action' => 'create'],
            ['key' => 'tasks.edit', 'module' => 'tasks', 'action' => 'edit'],
            ['key' => 'tasks.delete', 'module' => 'tasks', 'action' => 'delete'],
            ['key' => 'inventory.view', 'module' => 'inventory', 'action' => 'view'],
            ['key' => 'inventory.edit', 'module' => 'inventory', 'action' => 'edit'],
            ['key' => 'purchasing.view', 'module' => 'purchasing', 'action' => 'view'],
            ['key' => 'purchasing.create', 'module' => 'purchasing', 'action' => 'create'],
            ['key' => 'purchasing.edit', 'module' => 'purchasing', 'action' => 'edit'],
            ['key' => 'members.view', 'module' => 'members', 'action' => 'view'],
            ['key' => 'members.invite', 'module' => 'members', 'action' => 'invite'],
            ['key' => 'members.manage', 'module' => 'members', 'action' => 'manage'],
            ['key' => 'billing.view', 'module' => 'billing', 'action' => 'view'],
            ['key' => 'billing.manage', 'module' => 'billing', 'action' => 'manage'],
            ['key' => 'audit.view', 'module' => 'audit', 'action' => 'view'],
        ];
    }

    private function roleDefinitions(): array
    {
        return [
            [
                'key' => 'owner',
                'name' => 'Owner',
            ],
            [
                'key' => 'admin',
                'name' => 'Admin',
            ],
            [
                'key' => 'executive_chef',
                'name' => 'Executive Chef',
            ],
            [
                'key' => 'sous_chef',
                'name' => 'Sous Chef',
            ],
            [
                'key' => 'chef',
                'name' => 'Chef / Prep Cook',
            ],
            [
                'key' => 'viewer',
                'name' => 'Viewer / Client',
            ],
        ];
    }

    private function rolePermissionMap(): array
    {
        return [
            'owner' => [
                'clients.view',
                'clients.create',
                'clients.edit',
                'clients.delete',
                'contacts.view',
                'contacts.create',
                'contacts.edit',
                'contacts.delete',
                'venues.view',
                'venues.create',
                'venues.edit',
                'venues.delete',
                'events.view',
                'events.create',
                'events.edit',
                'events.delete',
                'menus.view',
                'menus.create',
                'menus.edit',
                'recipes.view',
                'recipes.create',
                'recipes.edit',
                'prep_lists.view',
                'prep_lists.create',
                'prep_lists.edit',
                'tasks.view',
                'tasks.create',
                'tasks.edit',
                'tasks.delete',
                'inventory.view',
                'inventory.edit',
                'purchasing.view',
                'purchasing.create',
                'purchasing.edit',
                'members.view',
                'members.invite',
                'members.manage',
                'billing.view',
                'billing.manage',
                'audit.view',
            ],
            'admin' => [
                'clients.view',
                'clients.create',
                'clients.edit',
                'clients.delete',
                'contacts.view',
                'contacts.create',
                'contacts.edit',
                'contacts.delete',
                'venues.view',
                'venues.create',
                'venues.edit',
                'venues.delete',
                'events.view',
                'events.create',
                'events.edit',
                'events.delete',
                'menus.view',
                'menus.create',
                'menus.edit',
                'recipes.view',
                'recipes.create',
                'recipes.edit',
                'prep_lists.view',
                'prep_lists.create',
                'prep_lists.edit',
                'tasks.view',
                'tasks.create',
                'tasks.edit',
                'tasks.delete',
                'inventory.view',
                'inventory.edit',
                'purchasing.view',
                'purchasing.create',
                'purchasing.edit',
                'members.view',
                'members.invite',
                'members.manage',
                'billing.view',
                'audit.view',
            ],
            'executive_chef' => [
                'clients.view',
                'clients.create',
                'clients.edit',
                'contacts.view',
                'contacts.create',
                'contacts.edit',
                'venues.view',
                'venues.create',
                'venues.edit',
                'events.view',
                'events.create',
                'events.edit',
                'menus.view',
                'menus.create',
                'menus.edit',
                'recipes.view',
                'recipes.create',
                'recipes.edit',
                'prep_lists.view',
                'prep_lists.create',
                'prep_lists.edit',
                'tasks.view',
                'tasks.create',
                'tasks.edit',
                'inventory.view',
                'inventory.edit',
                'purchasing.view',
                'purchasing.create',
                'purchasing.edit',
                'members.view',
                'audit.view',
            ],
            'sous_chef' => [
                'clients.view',
                'clients.create',
                'clients.edit',
                'contacts.view',
                'contacts.create',
                'contacts.edit',
                'venues.view',
                'venues.create',
                'venues.edit',
                'events.view',
                'events.create',
                'menus.view',
                'recipes.view',
                'recipes.edit',
                'prep_lists.view',
                'prep_lists.create',
                'prep_lists.edit',
                'tasks.view',
                'tasks.create',
                'tasks.edit',
                'inventory.view',
                'inventory.edit',
                'purchasing.view',
                'purchasing.create',
            ],
            'chef' => [
                'clients.view',
                'contacts.view',
                'venues.view',
                'events.view',
                'menus.view',
                'recipes.view',
                'prep_lists.view',
                'prep_lists.edit',
                'tasks.view',
                'tasks.edit',
                'inventory.view',
            ],
            'viewer' => [
                'clients.view',
                'contacts.view',
                'venues.view',
                'events.view',
                'menus.view',
                'recipes.view',
                'tasks.view',
            ],
        ];
    }
}
