<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['events', 'view'],
            ['events', 'create'],
            ['events', 'edit'],
            ['events', 'delete'],

            ['menus', 'view'],
            ['menus', 'create'],
            ['menus', 'edit'],

            ['recipes', 'view'],
            ['recipes', 'create'],
            ['recipes', 'edit'],

            ['prep_lists', 'view'],
            ['prep_lists', 'create'],
            ['prep_lists', 'edit'],

            ['inventory', 'view'],
            ['inventory', 'edit'],

            ['purchasing', 'view'],
            ['purchasing', 'create'],
            ['purchasing', 'edit'],

            ['members', 'view'],
            ['members', 'invite'],
            ['members', 'manage'],

            ['billing', 'view'],
            ['billing', 'manage'],

            ['audit', 'view'],
        ];

        foreach ($permissions as [$module, $action]) {
            Permission::updateOrCreate(
                [
                    'key' => "{$module}.{$action}",
                ],
                [
                    'module' => $module,
                    'action' => $action,
                ]
            );
        }
    }
}
