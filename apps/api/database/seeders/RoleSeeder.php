<?php

namespace Database\Seeders;

use App\Support\WorkspaceAccessCatalog;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(WorkspaceAccessCatalog::class)->ensureRoles();
    }
}
