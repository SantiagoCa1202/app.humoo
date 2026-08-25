<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prep_items', function (Blueprint $table): void {
            $table->string('unit_label', 64)->nullable()->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('prep_items', function (Blueprint $table): void {
            $table->dropColumn('unit_label');
        });
    }
};
