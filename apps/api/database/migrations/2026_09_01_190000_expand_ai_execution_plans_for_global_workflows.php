<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_execution_plans', function (Blueprint $table): void {
            $table->string('status', 32)->default('draft')->change();
            $table->string('objective', 180)->nullable()->after('title');
            $table->unsignedSmallInteger('revision')->default(1)->after('objective');
            $table->unsignedSmallInteger('needs_review_count')->default(0)->after('failed_count');
            $table->foreignUlid('progress_message_id')->nullable()->after('confirmation_id')
                ->constrained('messages')->nullOnDelete();

            $table->index(['workspace_id', 'conversation_id', 'status']);
        });

        Schema::table('ai_execution_plan_items', function (Blueprint $table): void {
            $table->string('status', 32)->default('previewed')->change();
            $table->string('step_key', 100)->nullable()->after('position');
            $table->json('depends_on_json')->nullable()->after('label');
            $table->json('input_json')->nullable()->after('depends_on_json');
            $table->json('input_bindings_json')->nullable()->after('input_json');
            $table->string('idempotency_key', 120)->nullable()->after('attempts');
            $table->boolean('is_required')->default(true)->after('idempotency_key');

            $table->unique(['execution_plan_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_execution_plan_items', function (Blueprint $table): void {
            $table->dropUnique(['execution_plan_id', 'step_key']);
            $table->dropColumn([
                'step_key',
                'depends_on_json',
                'input_json',
                'input_bindings_json',
                'idempotency_key',
                'is_required',
            ]);
            $table->enum('status', ['previewed', 'queued', 'running', 'completed', 'failed', 'cancelled'])
                ->default('previewed')->change();
        });

        Schema::table('ai_execution_plans', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'conversation_id', 'status']);
            $table->dropConstrainedForeignId('progress_message_id');
            $table->dropColumn(['objective', 'revision', 'needs_review_count']);
            $table->enum('status', ['draft', 'pending_confirmation', 'queued', 'running', 'completed', 'partial', 'failed', 'cancelled'])
                ->default('draft')->change();
        });
    }
};
