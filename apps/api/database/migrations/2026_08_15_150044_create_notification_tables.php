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
        | Notification Preferences
        |--------------------------------------------------------------------------
        */
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Event key
            |--------------------------------------------------------------------------
            |
            | Examples:
            | prep.assigned
            | prep.due_soon
            | beo.changed
            | inventory.low_stock
            | inventory.expiring
            | invitation.received
            | purchase_order.received
            */
            $table->string('event_key', 120);

            /*
            |--------------------------------------------------------------------------
            | Channels
            |--------------------------------------------------------------------------
            */
            $table->boolean('in_app')->default(true);
            $table->boolean('push')->default(true);
            $table->boolean('email')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Optional importance filter
            |--------------------------------------------------------------------------
            |
            | all
            | important
            | critical
            */
            $table->string('minimum_priority', 32)->default('all');

            /*
            |--------------------------------------------------------------------------
            | Quiet hours
            |--------------------------------------------------------------------------
            */
            $table->boolean('quiet_hours_enabled')->default(false);

            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            $table->string('timezone', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            */
            $table->boolean('enabled')->default(true);

            $table->timestamps();

            $table->unique([
                'workspace_id',
                'user_id',
                'event_key',
            ]);

            $table->index([
                'workspace_id',
                'user_id',
                'enabled',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        |
        | Canonical notification record.
        |--------------------------------------------------------------------------
        */
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Event / category
            |--------------------------------------------------------------------------
            */
            $table->string('event_key', 120);

            /*
            |--------------------------------------------------------------------------
            | Notification type
            |--------------------------------------------------------------------------
            |
            | info
            | success
            | warning
            | error
            | action_required
            */
            $table->string('type', 32)->default('info');

            /*
            |--------------------------------------------------------------------------
            | Priority
            |--------------------------------------------------------------------------
            */
            $table->enum('priority', [
                'low',
                'normal',
                'high',
                'critical',
            ])->default('normal');

            /*
            |--------------------------------------------------------------------------
            | Content
            |--------------------------------------------------------------------------
            */
            $table->string('title', 255);

            $table->text('body')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Related entity
            |--------------------------------------------------------------------------
            |
            | event
            | prep_item
            | beo_version
            | purchase_order
            | inventory_item
            | invitation
            */
            $table->string('entity_type', 80)->nullable();

            $table->ulid('entity_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Action
            |--------------------------------------------------------------------------
            |
            | Example:
            | open_event
            | review_beo
            | open_prep_item
            */
            $table->string('action_key', 100)->nullable();

            $table->json('action_payload')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Flexible payload
            |--------------------------------------------------------------------------
            */
            $table->json('payload')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Read / dismissed state
            |--------------------------------------------------------------------------
            */
            $table->timestamp('read_at')->nullable();

            $table->timestamp('dismissed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Expiration
            |--------------------------------------------------------------------------
            */
            $table->timestamp('expires_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | system
            | user
            | ai
            | automation
            */
            $table->string('source', 32)->default('system');

            /*
            |--------------------------------------------------------------------------
            | Deduplication
            |--------------------------------------------------------------------------
            |
            | Prevents creating the same notification repeatedly.
            */
            $table->string('deduplication_key', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'user_id',
                'read_at',
            ]);

            $table->index([
                'workspace_id',
                'user_id',
                'created_at',
            ]);

            $table->index([
                'workspace_id',
                'event_key',
            ]);

            $table->index([
                'entity_type',
                'entity_id',
            ]);

            $table->index([
                'workspace_id',
                'priority',
                'created_at',
            ]);

            $table->unique([
                'workspace_id',
                'user_id',
                'deduplication_key',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Notification Deliveries
        |--------------------------------------------------------------------------
        |
        | One notification can have multiple delivery attempts/channels.
        |--------------------------------------------------------------------------
        */
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete();

            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Channel
            |--------------------------------------------------------------------------
            |
            | in_app
            | push
            | email
            */
            $table->enum('channel', [
                'in_app',
                'push',
                'email',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Delivery status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'processing',
                'sent',
                'delivered',
                'failed',
                'cancelled',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Provider
            |--------------------------------------------------------------------------
            |
            | mailgun
            | ses
            | fcm
            | apns
            | internal
            */
            $table->string('provider', 64)->nullable();

            $table->string('provider_message_id', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Target snapshot
            |--------------------------------------------------------------------------
            |
            | Push token/email may change later.
            */
            $table->string('destination', 512)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Attempts
            |--------------------------------------------------------------------------
            */
            $table->unsignedSmallInteger('attempts')->default(0);

            $table->timestamp('last_attempt_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Delivery timestamps
            |--------------------------------------------------------------------------
            */
            $table->timestamp('sent_at')->nullable();

            $table->timestamp('delivered_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Failure
            |--------------------------------------------------------------------------
            */
            $table->string('error_code', 100)->nullable();

            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Provider metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'notification_id',
                'channel',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'user_id',
                'created_at',
            ]);

            $table->index([
                'provider',
                'provider_message_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_preferences');
    }
};
