<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy migration intentionally disabled.
        // Identity and billing now live in the workspace-based migrations from 2026-08-15.
    }

    public function down(): void
    {
        // No-op for the disabled legacy migration.
    }
};
