<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intent_patterns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')
                ->nullable()
                ->constrained('workspaces')
                ->nullOnDelete();
            $table->string('scope', 16);
            $table->string('action_key', 120);
            $table->string('normalized_key', 191);
            $table->json('pattern_json');
            $table->json('slot_schema')->nullable();
            $table->json('examples')->nullable();
            $table->unsignedInteger('occurrences')->default(0);
            $table->unsignedInteger('successful_executions')->default(0);
            $table->unsignedInteger('failed_executions')->default(0);
            $table->decimal('confidence', 7, 6)->default(0);
            $table->decimal('ambiguity_rate', 7, 6)->default(0);
            $table->string('status', 16)->default('observed');
            $table->string('router_version', 64);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'last_seen_at']);
            $table->index(['scope', 'status', 'normalized_key']);
            $table->unique(
                ['workspace_id', 'action_key', 'normalized_key'],
                'intent_patterns_workspace_action_key_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intent_patterns');
    }
};
