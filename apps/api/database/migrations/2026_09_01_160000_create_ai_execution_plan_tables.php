<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_confirmations', function (Blueprint $table): void {
            $table->boolean('is_execution_plan_item')->default(false)->index();
        });

        Schema::create('ai_execution_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('confirmation_id')->nullable()->unique()->constrained('action_confirmations')->nullOnDelete();
            $table->string('title', 180)->nullable();
            $table->enum('status', ['draft', 'pending_confirmation', 'queued', 'running', 'completed', 'partial', 'failed', 'cancelled'])
                ->default('draft');
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->unsignedSmallInteger('completed_count')->default(0);
            $table->unsignedSmallInteger('failed_count')->default(0);
            $table->unsignedTinyInteger('block_size')->default(5);
            $table->json('summary_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('ai_execution_plan_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('execution_plan_id')->constrained('ai_execution_plans')->cascadeOnDelete();
            $table->foreignUlid('action_confirmation_id')->nullable()->unique()->constrained('action_confirmations')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('action_key', 120);
            $table->string('label', 180)->nullable();
            $table->enum('status', ['previewed', 'queued', 'running', 'completed', 'failed', 'cancelled'])->default('previewed');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('preview_json')->nullable();
            $table->json('result_ref_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['execution_plan_id', 'position']);
            $table->index(['execution_plan_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_execution_plan_items');
        Schema::dropIfExists('ai_execution_plans');
        Schema::table('action_confirmations', function (Blueprint $table): void {
            $table->dropIndex(['is_execution_plan_item']);
            $table->dropColumn('is_execution_plan_item');
        });
    }
};
