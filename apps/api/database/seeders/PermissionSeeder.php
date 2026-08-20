<?php

namespace Database\Seeders;

use App\Support\WorkspaceAccessCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(WorkspaceAccessCatalog::class)->ensurePermissions();
    }
}
