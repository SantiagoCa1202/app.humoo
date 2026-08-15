<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
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

        foreach ($roles as $role) {
            Role::updateOrCreate(
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
    }
}
