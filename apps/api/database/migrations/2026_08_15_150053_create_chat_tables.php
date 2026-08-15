<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Conversations
        |--------------------------------------------------------------------------
        */
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Conversation scope
            |--------------------------------------------------------------------------
            |
            | event
            | prep_list
            | purchase_order
            | inventory
            | general
            */
            $table->string('scope_type', 80)->nullable();
            $table->ulid('scope_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Visibility
            |--------------------------------------------------------------------------
            |
            | private
            | workspace
            | participants
            */
            $table->string('visibility', 32)->default('private');

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'active',
                'archived',
                'closed',
            ])->default('active');

            $table->timestamp('last_message_at')->nullable();

            $table->timestamp('archived_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'workspace_id',
                'last_message_at',
            ]);

            $table->index([
                'workspace_id',
                'status',
                'last_message_at',
            ]);

            $table->index([
                'scope_type',
                'scope_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Conversation Participants
        |--------------------------------------------------------------------------
        */
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Conversation role
            |--------------------------------------------------------------------------
            |
            | owner
            | member
            | viewer
            */
            $table->string('role', 32)->default('member');

            /*
            |--------------------------------------------------------------------------
            | Read tracking
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('last_read_message_id')
                ->nullable();

            $table->timestamp('last_read_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            */
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->boolean('muted')->default(false);

            $table->timestamps();

            $table->unique([
                'conversation_id',
                'user_id',
            ]);

            $table->index([
                'workspace_id',
                'user_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Messages
        |--------------------------------------------------------------------------
        */
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Sender
            |--------------------------------------------------------------------------
            */
            $table->enum('sender_type', [
                'user',
                'assistant',
                'system',
                'tool',
            ]);

            $table->foreignUlid('sender_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'streaming',
                'completed',
                'failed',
                'cancelled',
            ])->default('completed');

            $table->string('locale', 8)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Plain text fallback / searchable content
            |--------------------------------------------------------------------------
            */
            $table->longText('content_text')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Message hierarchy
            |--------------------------------------------------------------------------
            |
            | Useful for reply/regeneration relationships.
            */
            $table->foreignUlid('parent_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Client idempotency
            |--------------------------------------------------------------------------
            */
            $table->string('client_message_id', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Error
            |--------------------------------------------------------------------------
            */
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at',
            ]);

            $table->index([
                'workspace_id',
                'created_at',
            ]);

            $table->index([
                'conversation_id',
                'status',
            ]);

            $table->index('correlation_id');

            $table->unique([
                'conversation_id',
                'client_message_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Complete participant read FK
        |--------------------------------------------------------------------------
        */
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->foreign('last_read_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Message Blocks
        |--------------------------------------------------------------------------
        */
        Schema::create('message_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->unsignedInteger('position');

            /*
            |--------------------------------------------------------------------------
            | Block type
            |--------------------------------------------------------------------------
            |
            | text
            | component
            | citation
            | status
            | error
            */
            $table->string('block_type', 64);

            /*
            |--------------------------------------------------------------------------
            | Remote component
            |--------------------------------------------------------------------------
            */
            $table->string('component_key', 120)->nullable();

            $table->unsignedInteger('schema_version')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Stable component instance
            |--------------------------------------------------------------------------
            */
            $table->ulid('instance_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Payload
            |--------------------------------------------------------------------------
            */
            $table->json('payload_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Refresh behavior
            |--------------------------------------------------------------------------
            */
            $table->boolean('refreshable')->default(false);

            $table->timestamp('generated_at')->nullable();

            $table->timestamp('stale_at')->nullable();

            $table->timestamps();

            $table->unique([
                'message_id',
                'position',
            ]);

            $table->index([
                'message_id',
                'block_type',
            ]);

            $table->index([
                'component_key',
                'schema_version',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | AI Runs
        |--------------------------------------------------------------------------
        */
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Input/output message references
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->foreignUlid('input_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Provider/model
            |--------------------------------------------------------------------------
            */
            $table->string('provider', 64)->nullable();

            $table->string('model_key', 120);

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'running',
                'completed',
                'failed',
                'cancelled',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Prompt / orchestration version
            |--------------------------------------------------------------------------
            */
            $table->string('prompt_version', 64)->nullable();

            $table->string('orchestrator_version', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('started_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('latency_ms')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Usage / cost
            |--------------------------------------------------------------------------
            */
            $table->json('usage_json')->nullable();

            $table->decimal('estimated_cost', 14, 6)->nullable();

            $table->char('cost_currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Retry
            |--------------------------------------------------------------------------
            */
            $table->unsignedSmallInteger('attempt')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Error
            |--------------------------------------------------------------------------
            */
            $table->string('error_code', 100)->nullable();

            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'status',
                'created_at',
            ]);

            $table->index([
                'message_id',
                'created_at',
            ]);

            $table->index([
                'model_key',
                'created_at',
            ]);

            $table->index('correlation_id');
        });

        /*
        |--------------------------------------------------------------------------
        | AI Tool Calls
        |--------------------------------------------------------------------------
        */
        Schema::create('ai_tool_calls', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('ai_run_id')
                ->constrained('ai_runs')
                ->cascadeOnDelete();

            $table->string('tool_key', 120);

            /*
            |--------------------------------------------------------------------------
            | Tool call order
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('position')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Input
            |--------------------------------------------------------------------------
            */
            $table->json('arguments_json');

            /*
            |--------------------------------------------------------------------------
            | Result
            |--------------------------------------------------------------------------
            |
            | Prefer stable references/minimal snapshots over huge DB payloads.
            */
            $table->json('result_ref_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'running',
                'completed',
                'failed',
                'cancelled',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Permission / confirmation
            |--------------------------------------------------------------------------
            */
            $table->boolean('requires_confirmation')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('started_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('latency_ms')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Error
            |--------------------------------------------------------------------------
            */
            $table->string('error_code', 100)->nullable();

            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'ai_run_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'tool_key',
                'created_at',
            ]);

            $table->index([
                'status',
                'created_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Action Confirmations
        |--------------------------------------------------------------------------
        |
        | Supports:
        | draft -> preview -> confirm -> commit
        |--------------------------------------------------------------------------
        */
        Schema::create('action_confirmations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->foreignUlid('ai_tool_call_id')
                ->nullable()
                ->constrained('ai_tool_calls')
                ->nullOnDelete();

            $table->string('action_key', 120);

            /*
            |--------------------------------------------------------------------------
            | Secure confirmation token
            |--------------------------------------------------------------------------
            */
            $table->string('token_hash', 128)->unique();

            /*
            |--------------------------------------------------------------------------
            | Draft
            |--------------------------------------------------------------------------
            */
            $table->json('draft_json');

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled',
                'expired',
                'executed',
                'failed',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Expiration
            |--------------------------------------------------------------------------
            */
            $table->timestamp('expires_at');

            /*
            |--------------------------------------------------------------------------
            | Confirmation
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('confirmed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cancellation
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Execution
            |--------------------------------------------------------------------------
            */
            $table->timestamp('executed_at')->nullable();

            $table->json('result_ref_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Idempotency
            |--------------------------------------------------------------------------
            */
            $table->string('idempotency_key', 120)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Error
            |--------------------------------------------------------------------------
            */
            $table->string('error_code', 100)->nullable();

            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'status',
                'expires_at',
            ]);

            $table->index([
                'message_id',
                'action_key',
            ]);

            $table->unique([
                'workspace_id',
                'idempotency_key',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Conversation Summaries
        |--------------------------------------------------------------------------
        */
        Schema::create('conversation_summaries', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->longText('summary');

            /*
            |--------------------------------------------------------------------------
            | Summary coverage
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('through_message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Generation information
            |--------------------------------------------------------------------------
            */
            $table->string('model_key', 120)->nullable();

            $table->string('prompt_version', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'active',
                'superseded',
                'failed',
            ])->default('active');

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at',
            ]);

            $table->index([
                'conversation_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Remove delayed FK first
        |--------------------------------------------------------------------------
        */
        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropForeign(['last_read_message_id']);
        });

        Schema::dropIfExists('conversation_summaries');
        Schema::dropIfExists('action_confirmations');
        Schema::dropIfExists('ai_tool_calls');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('message_blocks');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
