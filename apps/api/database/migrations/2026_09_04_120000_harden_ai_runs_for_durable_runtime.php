<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->foreignUlid('conversation_id')->nullable()->after('workspace_id')->constrained('conversations')->nullOnDelete();
            $table->foreignUlid('actor_id')->nullable()->after('conversation_id')->constrained('users')->nullOnDelete();
            $table->foreignUlid('execution_plan_id')->nullable()->after('input_message_id')->constrained('ai_execution_plans')->nullOnDelete();
            $table->string('current_stage', 64)->nullable()->after('status');
            $table->unsignedInteger('progress_current')->nullable()->after('current_stage');
            $table->unsignedInteger('progress_total')->nullable()->after('progress_current');
            $table->json('progress_meta')->nullable()->after('progress_total');
            $table->unsignedInteger('sequence')->default(0)->after('progress_meta');
            $table->unsignedSmallInteger('tool_loop_iteration')->default(0)->after('sequence');
            $table->unsignedSmallInteger('infrastructure_retry_count')->default(0)->after('attempt');
            $table->timestamp('queued_at')->nullable()->after('infrastructure_retry_count');
            $table->timestamp('failed_at')->nullable()->after('completed_at');

            $table->index(['conversation_id', 'status', 'updated_at'], 'ai_runs_conversation_status_updated_idx');
            $table->index(['workspace_id', 'actor_id'], 'ai_runs_workspace_actor_idx');
        });

        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->string('status', 32)->default('queued')->change();
        });

        DB::table('ai_runs')->where('status', 'pending')->update([
            'status' => 'queued',
            'current_stage' => 'queued',
            'queued_at' => DB::raw('created_at'),
        ]);

        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->string('provider_tool_call_id', 180)->nullable()->after('ai_run_id');
            $table->string('idempotency_key', 180)->nullable()->after('provider_tool_call_id');
            $table->unsignedSmallInteger('attempt')->default(1)->after('position');
            $table->unique(['ai_run_id', 'idempotency_key'], 'ai_tool_calls_run_idempotency_unique');
        });

        Schema::table('ai_execution_plans', function (Blueprint $table): void {
            $table->foreignUlid('ai_run_id')->nullable()->after('conversation_id')->constrained('ai_runs')->nullOnDelete();
            $table->index(['ai_run_id', 'status'], 'ai_execution_plans_run_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_execution_plans', function (Blueprint $table): void {
            $table->dropIndex('ai_execution_plans_run_status_idx');
            $table->dropConstrainedForeignId('ai_run_id');
        });

        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->dropUnique('ai_tool_calls_run_idempotency_unique');
            $table->dropColumn(['provider_tool_call_id', 'idempotency_key', 'attempt']);
        });

        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->dropIndex('ai_runs_conversation_status_updated_idx');
            $table->dropIndex('ai_runs_workspace_actor_idx');
            $table->dropConstrainedForeignId('execution_plan_id');
            $table->dropConstrainedForeignId('actor_id');
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn([
                'current_stage',
                'progress_current',
                'progress_total',
                'progress_meta',
                'sequence',
                'tool_loop_iteration',
                'infrastructure_retry_count',
                'queued_at',
                'failed_at',
            ]);
        });
    }
};
