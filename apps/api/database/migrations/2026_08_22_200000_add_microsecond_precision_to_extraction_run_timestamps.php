<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table): void {
            $table->timestamp('created_at', 6)->change();
            $table->timestamp('updated_at', 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table): void {
            $table->timestamp('created_at')->change();
            $table->timestamp('updated_at')->change();
        });
    }
};
