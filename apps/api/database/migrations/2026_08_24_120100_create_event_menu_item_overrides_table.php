<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_menu_item_overrides', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('event_menu_id')->constrained('event_menus')->cascadeOnDelete();
            $table->foreignUlid('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->decimal('planned_quantity', 12, 4)->nullable();
            $table->string('serving_unit', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['event_menu_id', 'menu_item_id'], 'event_menu_item_override_unique');
            $table->index(['workspace_id', 'event_menu_id'], 'event_menu_override_workspace_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_menu_item_overrides');
    }
};
