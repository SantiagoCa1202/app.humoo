<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_entity_refs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();
            $table->foreignUlid('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->string('entity_type', 80);
            $table->ulid('entity_id');
            $table->string('role', 32)->default('recent');
            $table->timestamp('last_referenced_at');
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(
                ['conversation_id', 'entity_type', 'entity_id', 'role'],
                'conv_entity_refs_unique'
            );
            $table->index(
                ['workspace_id', 'conversation_id', 'role', 'last_referenced_at'],
                'conv_entity_refs_context_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_entity_refs');
    }
};
