<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_objectives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('source_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignUlid('confirmation_id')->nullable()->constrained('action_confirmations')->nullOnDelete();
            $table->text('description');
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 40)->default('analyzing');
            $table->json('required_facts_json')->nullable();
            $table->json('resolved_facts_json')->nullable();
            $table->json('blockers_json')->nullable();
            $table->json('expected_results_json')->nullable();
            $table->json('verification_rules_json')->nullable();
            $table->char('approval_digest', 64)->nullable();
            $table->unsignedInteger('operation_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('blocked_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('needs_review_count')->default(0);
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message_safe')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'conversation_id', 'status'], 'ai_objectives_workspace_conversation_status_idx');
            $table->index(['workspace_id', 'created_by', 'updated_at'], 'ai_objectives_workspace_actor_updated_idx');
        });

        Schema::create('ai_objective_operations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('objective_id')->constrained('ai_objectives')->cascadeOnDelete();
            $table->string('operation_key', 100);
            $table->string('module', 100)->nullable();
            $table->string('action_key', 120);
            $table->string('kind', 32)->default('write');
            $table->string('status', 40)->default('pending');
            $table->boolean('is_required')->default(true);
            $table->json('expected_result_keys_json')->nullable();
            $table->json('depends_on_json')->nullable();
            $table->json('input_json')->nullable();
            $table->json('bindings_json')->nullable();
            $table->json('retry_policy_json')->nullable();
            $table->json('atomicity_policy_json')->nullable();
            $table->json('result_ref_json')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message_safe')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['objective_id', 'operation_key'], 'ai_objective_operations_key_unique');
            $table->index(['objective_id', 'status'], 'ai_objective_operations_status_idx');
            $table->index(['workspace_id', 'action_key'], 'ai_objective_operations_workspace_action_idx');
        });

        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->foreignUlid('objective_id')->nullable()->after('conversation_id')->constrained('ai_objectives')->nullOnDelete();
            $table->timestamp('deadline_at')->nullable()->after('failed_at');
            $table->timestamp('last_heartbeat_at')->nullable()->after('deadline_at');
            $table->timestamp('next_retry_at')->nullable()->after('last_heartbeat_at');
            $table->index(['objective_id', 'status'], 'ai_runs_objective_status_idx');
        });

        Schema::table('ai_execution_plans', function (Blueprint $table): void {
            $table->foreignUlid('objective_id')->nullable()->after('conversation_id')->constrained('ai_objectives')->nullOnDelete();
            $table->index(['objective_id', 'revision'], 'ai_execution_plans_objective_revision_idx');
        });

        Schema::table('ai_execution_plan_items', function (Blueprint $table): void {
            $table->foreignUlid('objective_operation_id')->nullable()->after('execution_plan_id')->constrained('ai_objective_operations')->nullOnDelete();
            $table->index(['objective_operation_id', 'status'], 'ai_plan_items_objective_operation_idx');
        });

        Schema::table('action_confirmations', function (Blueprint $table): void {
            $table->foreignUlid('objective_id')->nullable()->after('workspace_id')->constrained('ai_objectives')->nullOnDelete();
            $table->unsignedInteger('manifest_revision')->nullable()->after('objective_id');
            $table->char('approval_digest', 64)->nullable()->after('manifest_revision');
            $table->index(['objective_id', 'status'], 'action_confirmations_objective_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('action_confirmations', function (Blueprint $table): void {
            $table->dropIndex('action_confirmations_objective_status_idx');
            $table->dropConstrainedForeignId('objective_id');
            $table->dropColumn(['manifest_revision', 'approval_digest']);
        });
        Schema::table('ai_execution_plan_items', function (Blueprint $table): void {
            $table->dropIndex('ai_plan_items_objective_operation_idx');
            $table->dropConstrainedForeignId('objective_operation_id');
        });
        Schema::table('ai_execution_plans', function (Blueprint $table): void {
            $table->dropIndex('ai_execution_plans_objective_revision_idx');
            $table->dropConstrainedForeignId('objective_id');
        });
        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->dropIndex('ai_runs_objective_status_idx');
            $table->dropConstrainedForeignId('objective_id');
            $table->dropColumn(['deadline_at', 'last_heartbeat_at', 'next_retry_at']);
        });
        Schema::dropIfExists('ai_objective_operations');
        Schema::dropIfExists('ai_objectives');
    }
};
