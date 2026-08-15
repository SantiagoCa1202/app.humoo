<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();

            $table->string('default_locale', 5)->default('en');
            $table->string('timezone')->default('UTC');
            $table->char('currency', 3)->default('USD');

            $table->enum('status', [
                'active',
                'suspended',
                'closed',
            ])->default('active');

            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('key');
            $table->string('name');
            $table->boolean('is_system')->default(false);

            $table->timestamps();

            $table->unique(['workspace_id', 'key']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('key')->unique();
            $table->string('module');
            $table->string('action');

            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUlid('role_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('permission_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('workspace_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('role_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->enum('status', [
                'pending',
                'active',
                'suspended',
                'removed',
            ])->default('active');

            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('auth_identities', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider');
            $table->string('provider_subject');
            $table->string('email')->nullable();

            $table->json('metadata_json')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_subject']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('role_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('email');

            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();

            $table->foreignUlid('invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('platform', 30);
            $table->string('name')->nullable();

            $table->text('push_token')->nullable();

            $table->string('app_version')->nullable();
            $table->string('last_ip', 45)->nullable();

            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('workspace_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignUlid('device_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('token_id')->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action');

            $table->string('entity_type');
            $table->ulid('entity_id')->nullable();

            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();

            $table->enum('source', [
                'web',
                'mobile',
                'api',
                'ai',
                'system',
            ])->default('api');

            $table->string('correlation_id')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'entity_type', 'entity_id']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('auth_identities');
        Schema::dropIfExists('workspace_memberships');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('workspaces');
    }
};
