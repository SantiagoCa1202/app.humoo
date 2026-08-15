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
        | Event Groups
        |--------------------------------------------------------------------------
        |
        | Allows grouping related events:
        | - Wedding Weekend
        | - Corporate Conference
        | - Multi-day Catering
        |--------------------------------------------------------------------------
        */
        Schema::create('event_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 180);

            $table->text('description')->nullable();

            $table->string('status', 32)
                ->default('active');

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'workspace_id',
                'name',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Clients
        |--------------------------------------------------------------------------
        */
        Schema::create('clients', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */
            $table->string('name', 180);

            $table->string('company_name', 180)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */
            $table->string('email', 255)->nullable();

            // E.164 preferred
            $table->string('phone', 32)->nullable();

            $table->string('website', 2048)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Billing / business info
            |--------------------------------------------------------------------------
            */
            $table->string('tax_id', 80)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            */
            $table->string('status', 32)
                ->default('active');

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'workspace_id',
                'name',
            ]);

            $table->index([
                'workspace_id',
                'company_name',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'email',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Contacts
        |--------------------------------------------------------------------------
        |
        | A client can have multiple contacts.
        |--------------------------------------------------------------------------
        */
        Schema::create('contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Personal info
            |--------------------------------------------------------------------------
            */
            $table->string('first_name', 100);

            $table->string('last_name', 100)->nullable();

            $table->string('display_name', 180)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */
            $table->string('email', 255)->nullable();

            // E.164
            $table->string('phone', 32)->nullable();

            $table->string('job_title', 120)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Contact role
            |--------------------------------------------------------------------------
            |
            | billing
            | event
            | coordinator
            | assistant
            | general
            */
            $table->string('contact_type', 64)->nullable();

            $table->boolean('is_primary')->default(false);

            $table->text('notes')->nullable();

            $table->json('metadata')->nullable();

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'workspace_id',
                'client_id',
            ]);

            $table->index([
                'workspace_id',
                'email',
            ]);

            $table->index([
                'client_id',
                'is_primary',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Venues
        |--------------------------------------------------------------------------
        */
        Schema::create('venues', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 180);

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();

            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timezone
            |--------------------------------------------------------------------------
            */
            $table->string('timezone', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Venue contact
            |--------------------------------------------------------------------------
            */
            $table->string('contact_name', 180)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 32)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Operational information
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('capacity')->nullable();

            $table->text('access_instructions')->nullable();
            $table->text('parking_notes')->nullable();
            $table->text('loading_notes')->nullable();
            $table->text('kitchen_notes')->nullable();

            $table->text('notes')->nullable();

            $table->string('status', 32)
                ->default('active');

            $table->json('metadata')->nullable();

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'workspace_id',
                'name',
            ]);

            $table->index([
                'workspace_id',
                'city',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Events
        |--------------------------------------------------------------------------
        */
        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();

            /*
            |--------------------------------------------------------------------------
            | Tenant
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Relationships
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('event_group_id')
                ->nullable()
                ->constrained('event_groups')
                ->nullOnDelete();

            $table->foreignUlid('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->foreignUlid('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->foreignUlid('venue_id')
                ->nullable()
                ->constrained('venues')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Event identity
            |--------------------------------------------------------------------------
            */
            $table->string('name', 200);

            /*
            |--------------------------------------------------------------------------
            | Optional human-friendly number
            |--------------------------------------------------------------------------
            |
            | Examples:
            | EVT-2026-0001
            | WEDDING-001
            |
            | Unique only inside workspace.
            */
            $table->string('event_number', 64)->nullable();

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Date / time
            |--------------------------------------------------------------------------
            |
            | Store actual timestamps in UTC.
            | timezone represents the event's local timezone.
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at');

            $table->timestamp('ends_at')->nullable();

            $table->string('timezone', 64);

            /*
            |--------------------------------------------------------------------------
            | Production window
            |--------------------------------------------------------------------------
            |
            | Useful because prep may start days before the event.
            |--------------------------------------------------------------------------
            */
            $table->timestamp('production_starts_at')->nullable();

            $table->timestamp('production_ends_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Guest counts
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('guest_count_expected')->nullable();

            $table->unsignedInteger('guest_count_confirmed')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Service
            |--------------------------------------------------------------------------
            |
            | plated
            | buffet
            | family_style
            | cocktail
            | drop_off
            | pickup
            | private_dining
            | etc.
            |
            | Keep string instead of enum to allow expansion.
            */
            $table->string('service_type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Event type
            |--------------------------------------------------------------------------
            |
            | wedding
            | corporate
            | private
            | birthday
            | conference
            | etc.
            */
            $table->string('event_type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'tentative',
                'confirmed',
                'in_production',
                'completed',
                'cancelled',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Priority / risk
            |--------------------------------------------------------------------------
            */
            $table->enum('priority', [
                'low',
                'normal',
                'high',
                'urgent',
            ])->default('normal');

            /*
            |--------------------------------------------------------------------------
            | Primary responsible chef/member
            |--------------------------------------------------------------------------
            |
            | Membership is better than user_id because responsibility exists
            | within a workspace.
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('lead_membership_id')
                ->nullable()
                ->constrained('workspace_memberships')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Location snapshot
            |--------------------------------------------------------------------------
            |
            | Important:
            | venue details can change later.
            | Event may need to preserve what was agreed at booking.
            |--------------------------------------------------------------------------
            */
            $table->string('venue_name_snapshot')->nullable();

            $table->string('address_line_1_snapshot')->nullable();
            $table->string('address_line_2_snapshot')->nullable();
            $table->string('city_snapshot')->nullable();
            $table->string('state_snapshot')->nullable();
            $table->string('postal_code_snapshot', 32)->nullable();
            $table->char('country_code_snapshot', 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Client snapshot
            |--------------------------------------------------------------------------
            */
            $table->string('client_name_snapshot')->nullable();

            $table->string('contact_name_snapshot')->nullable();
            $table->string('contact_email_snapshot')->nullable();
            $table->string('contact_phone_snapshot', 32)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            |
            | Short/general event notes.
            | Additional collaborative notes go in event_notes.
            |--------------------------------------------------------------------------
            */
            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cancellation
            |--------------------------------------------------------------------------
            */
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignUlid('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('cancellation_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Completion
            |--------------------------------------------------------------------------
            */
            $table->timestamp('completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optimistic locking
            |--------------------------------------------------------------------------
            |
            | Prevents silent overwrites during collaborative editing.
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('version')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Unique
            |--------------------------------------------------------------------------
            */
            $table->unique([
                'workspace_id',
                'event_number',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Main indexes
            |--------------------------------------------------------------------------
            */
            $table->index([
                'workspace_id',
                'status',
                'starts_at',
            ]);

            $table->index([
                'workspace_id',
                'event_group_id',
            ]);

            $table->index([
                'workspace_id',
                'client_id',
            ]);

            $table->index([
                'workspace_id',
                'venue_id',
            ]);

            $table->index([
                'workspace_id',
                'lead_membership_id',
            ]);

            $table->index([
                'workspace_id',
                'starts_at',
                'ends_at',
            ]);

            $table->index([
                'workspace_id',
                'priority',
                'starts_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Event Staff
        |--------------------------------------------------------------------------
        */
        Schema::create('event_staff', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignUlid('membership_id')
                ->constrained('workspace_memberships')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Event-specific role
            |--------------------------------------------------------------------------
            |
            | This is NOT RBAC.
            |
            | Examples:
            | executive_chef
            | sous_chef
            | prep_cook
            | server
            | bartender
            | event_captain
            |
            */
            $table->string('role', 64)->nullable();

            $table->boolean('is_lead')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Scheduling
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'assigned',
                'confirmed',
                'declined',
                'cancelled',
            ])->default('assigned');

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'event_id',
                'membership_id',
            ]);

            $table->index([
                'workspace_id',
                'event_id',
            ]);

            $table->index([
                'workspace_id',
                'membership_id',
            ]);

            $table->index([
                'event_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Event Notes
        |--------------------------------------------------------------------------
        */
        Schema::create('event_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Note category
            |--------------------------------------------------------------------------
            |
            | general
            | kitchen
            | service
            | client
            | venue
            | production
            | internal
            */
            $table->string('type', 64)
                ->default('general');

            $table->text('content');

            /*
            |--------------------------------------------------------------------------
            | Visibility
            |--------------------------------------------------------------------------
            |
            | internal = only workspace staff
            */
            $table->enum('visibility', [
                'internal',
                'shared',
            ])->default('internal');

            $table->boolean('pinned')->default(false);

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'workspace_id',
                'event_id',
                'type',
            ]);

            $table->index([
                'event_id',
                'pinned',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Event Status History
        |--------------------------------------------------------------------------
        */
        Schema::create('event_status_history', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('from_status', 32)->nullable();

            $table->string('to_status', 32);

            $table->foreignUlid('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | user
            | ai
            | system
            | automation
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)
                ->default('user');

            $table->text('reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            |
            | Useful for tracing:
            | Chat message -> AI action -> Event update
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional snapshot
            |--------------------------------------------------------------------------
            */
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'event_id',
            ]);

            $table->index([
                'event_id',
                'created_at',
            ]);

            $table->index([
                'workspace_id',
                'to_status',
                'created_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Event Tags
        |--------------------------------------------------------------------------
        |
        | Tags allow flexible categorization without adding columns.
        |--------------------------------------------------------------------------
        */
        Schema::create('event_tags', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 100);

            $table->string('key', 100);

            $table->timestamps();

            $table->unique([
                'workspace_id',
                'key',
            ]);

            $table->index([
                'workspace_id',
                'name',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Event Tag Assignments
        |--------------------------------------------------------------------------
        */
        Schema::create('event_tag_assignments', function (Blueprint $table) {
            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignUlid('event_tag_id')
                ->constrained('event_tags')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->primary([
                'event_id',
                'event_tag_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tag_assignments');
        Schema::dropIfExists('event_tags');

        Schema::dropIfExists('event_status_history');
        Schema::dropIfExists('event_notes');
        Schema::dropIfExists('event_staff');

        Schema::dropIfExists('events');

        Schema::dropIfExists('venues');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('event_groups');
    }
};
