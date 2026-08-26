<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_aliases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('entity_type', 80);
            $table->ulid('entity_id');
            $table->string('locale', 8)->default('en');
            $table->string('alias', 180);
            $table->string('normalized_alias', 180);
            $table->string('source', 32)->default('confirmed_selection');
            $table->unsignedInteger('confirmation_count')->default(1);
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'entity_type', 'entity_id', 'normalized_alias'], 'entity_aliases_unique_entity_alias');
            $table->index(['workspace_id', 'entity_type', 'normalized_alias'], 'entity_aliases_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_aliases');
    }
};
