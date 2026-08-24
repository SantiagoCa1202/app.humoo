<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capability_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('conversation_id')
                ->nullable()
                ->constrained('conversations')
                ->nullOnDelete();

            $table->foreignUlid('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->string('detected_intent', 120);
            $table->string('module', 80)->nullable();
            $table->string('requested_action', 180);
            $table->string('normalized_key', 191);
            $table->enum('status', [
                'unsupported',
                'planned',
                'supported',
                'rejected',
            ])->default('unsupported');
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_requested_at');
            $table->timestamp('last_requested_at');
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique([
                'workspace_id',
                'normalized_key',
            ]);

            $table->index([
                'workspace_id',
                'status',
                'last_requested_at',
            ]);

            $table->index([
                'workspace_id',
                'module',
                'last_requested_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_requests');
    }
};
