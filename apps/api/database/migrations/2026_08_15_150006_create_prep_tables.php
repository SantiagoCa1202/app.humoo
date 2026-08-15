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
        | Prep Lists
        |--------------------------------------------------------------------------
        |
        | Stable logical production/prep list for an event.
        | Actual historical content lives in prep_list_versions.
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_lists', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->string('name', 180);

            /*
            |--------------------------------------------------------------------------
            | Production window
            |--------------------------------------------------------------------------
            |
            | Use timestamps rather than dates so production can be planned
            | at specific times.
            |--------------------------------------------------------------------------
            */
            $table->timestamp('production_starts_at')->nullable();
            $table->timestamp('production_ends_at')->nullable();

            $table->string('timezone', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Current approved/published version
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('current_version')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'active',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Progress snapshot
            |--------------------------------------------------------------------------
            |
            | These fields are optional caches for fast dashboard queries.
            | The source of truth remains prep_items.
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('completed_items')->default(0);
            $table->unsignedInteger('blocked_items')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Completion
            |--------------------------------------------------------------------------
            */
            $table->timestamp('completed_at')->nullable();

            $table->foreignUlid('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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
                'event_id',
            ]);

            $table->index([
                'workspace_id',
                'status',
                'production_starts_at',
            ]);

            $table->index([
                'event_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Prep List Versions
        |--------------------------------------------------------------------------
        |
        | Frozen/referenceable production plan.
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_list_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_list_id')
                ->constrained('prep_lists')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Exact upstream sources
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('menu_version_id')
                ->nullable()
                ->constrained('menu_versions')
                ->nullOnDelete();

            $table->foreignUlid('beo_version_id')
                ->nullable()
                ->constrained('beo_versions')
                ->nullOnDelete();

            $table->unsignedInteger('version');

            /*
            |--------------------------------------------------------------------------
            | Version status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'review',
                'approved',
                'superseded',
                'cancelled',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Generation source
            |--------------------------------------------------------------------------
            |
            | manual
            | ai
            | regeneration
            | import
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('manual');

            /*
            |--------------------------------------------------------------------------
            | Generation configuration / trace
            |--------------------------------------------------------------------------
            |
            | Example:
            | - guest count used
            | - rounding rules
            | - selected menu versions
            | - scaling strategy
            | - warnings
            | - AI run information
            |--------------------------------------------------------------------------
            */
            $table->json('generation_metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Snapshot inputs
            |--------------------------------------------------------------------------
            |
            | Important because event guest count etc. may change later.
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('guest_count_snapshot')->nullable();

            $table->timestamp('event_starts_at_snapshot')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Locking
            |--------------------------------------------------------------------------
            */
            $table->boolean('locked')->default(false);

            $table->timestamp('locked_at')->nullable();

            $table->foreignUlid('locked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Approval
            |--------------------------------------------------------------------------
            */
            $table->timestamp('approved_at')->nullable();

            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Version change note
            |--------------------------------------------------------------------------
            */
            $table->text('change_summary')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optimistic locking for draft editing
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('revision')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'prep_list_id',
                'version',
            ]);

            $table->index([
                'workspace_id',
                'prep_list_id',
            ]);

            $table->index([
                'prep_list_id',
                'status',
            ]);

            $table->index([
                'menu_version_id',
                'beo_version_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Prep Sections
        |--------------------------------------------------------------------------
        |
        | Group items by station/day/category/responsible.
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_sections', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_list_version_id')
                ->constrained('prep_list_versions')
                ->cascadeOnDelete();

            $table->foreignUlid('station_id')
                ->nullable()
                ->constrained('stations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional team ownership
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->string('name', 180);

            /*
            |--------------------------------------------------------------------------
            | Section type
            |--------------------------------------------------------------------------
            |
            | station
            | day
            | category
            | team
            | custom
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64)->default('custom');

            /*
            |--------------------------------------------------------------------------
            | Production timing
            |--------------------------------------------------------------------------
            */
            $table->date('production_date')->nullable();

            $table->timestamp('starts_at')->nullable();

            $table->timestamp('due_at')->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'prep_list_version_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'station_id',
                'production_date',
            ]);

            $table->index([
                'workspace_id',
                'team_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Prep Items
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_section_id')
                ->constrained('prep_sections')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Recipe source
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('recipe_id')
                ->nullable()
                ->constrained('recipes')
                ->nullOnDelete();

            $table->foreignUlid('recipe_version_id')
                ->nullable()
                ->constrained('recipe_versions')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional menu item origin
            |--------------------------------------------------------------------------
            |
            | Allows tracing:
            | prep item -> menu item -> recipe
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('menu_item_id')
                ->nullable()
                ->constrained('menu_items')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Station / team
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('station_id')
                ->nullable()
                ->constrained('stations')
                ->nullOnDelete();

            $table->foreignUlid('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Item identity
            |--------------------------------------------------------------------------
            */
            $table->string('title', 255);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity', 18, 4)->nullable();

            $table->foreignUlid('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Portions / yield
            |--------------------------------------------------------------------------
            */
            $table->decimal('portions', 18, 4)->nullable();

            $table->decimal('yield_quantity', 18, 4)->nullable();

            $table->foreignUlid('yield_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Scaling
            |--------------------------------------------------------------------------
            |
            | Snapshot of scale applied against source recipe.
            |--------------------------------------------------------------------------
            */
            $table->decimal('scale_factor', 18, 6)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Planned vs actual quantity
            |--------------------------------------------------------------------------
            |
            | Actual output allows production variance tracking.
            |--------------------------------------------------------------------------
            */
            $table->decimal('actual_quantity', 18, 4)->nullable();

            $table->foreignUlid('actual_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at')->nullable();

            $table->timestamp('due_at')->nullable();

            $table->timestamp('started_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Priority
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
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'todo',
                'in_progress',
                'blocked',
                'done',
                'skipped',
            ])->default('todo');

            /*
            |--------------------------------------------------------------------------
            | Blocked reason
            |--------------------------------------------------------------------------
            */
            $table->text('blocked_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Completion
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Production behavior
            |--------------------------------------------------------------------------
            */
            $table->boolean('requires_confirmation')->default(false);

            $table->boolean('generated')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | manual
            | recipe
            | menu
            | beo
            | ai
            | system
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('manual');

            /*
            |--------------------------------------------------------------------------
            | Generic source reference
            |--------------------------------------------------------------------------
            */
            $table->string('source_type', 80)->nullable();

            $table->ulid('source_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Instructions / notes
            |--------------------------------------------------------------------------
            */
            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optimistic locking
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('version')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Sort order
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('position')->default(0);

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
                'status',
                'due_at',
            ]);

            $table->index([
                'prep_section_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'station_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'team_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'recipe_version_id',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Prep Item Assignments
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_item_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_item_id')
                ->constrained('prep_items')
                ->cascadeOnDelete();

            $table->foreignUlid('membership_id')
                ->constrained('workspace_memberships')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Assignment state
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'assigned',
                'accepted',
                'declined',
                'completed',
                'cancelled',
            ])->default('assigned');

            $table->boolean('is_primary')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Assignment timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('assigned_at')->nullable();

            $table->timestamp('accepted_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'prep_item_id',
                'membership_id',
            ]);

            $table->index([
                'workspace_id',
                'membership_id',
                'status',
            ]);

            $table->index([
                'prep_item_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Prep Item Dependencies
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_item_dependencies', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_item_id')
                ->constrained('prep_items')
                ->cascadeOnDelete();

            $table->foreignUlid('depends_on_prep_item_id')
                ->constrained('prep_items')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Dependency type
            |--------------------------------------------------------------------------
            |
            | finish_to_start
            | start_to_start
            |--------------------------------------------------------------------------
            */
            $table->string('type', 32)
                ->default('finish_to_start');

            /*
            |--------------------------------------------------------------------------
            | Optional lag
            |--------------------------------------------------------------------------
            |
            | Example:
            | item B starts 60 minutes after A finishes.
            |--------------------------------------------------------------------------
            */
            $table->integer('lag_minutes')->default(0);

            $table->timestamps();

            $table->unique([
                'prep_item_id',
                'depends_on_prep_item_id',
            ], 'prep_item_deps_unique');

            $table->index([
                'workspace_id',
                'prep_item_id',
            ], 'prep_item_deps_lookup_idx');
        });

        /*
        |--------------------------------------------------------------------------
        | Prep Item Updates
        |--------------------------------------------------------------------------
        |
        | Item-level operational audit trail.
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_item_updates', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_item_id')
                ->constrained('prep_items')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Actor
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Action
            |--------------------------------------------------------------------------
            |
            | created
            | started
            | completed
            | blocked
            | quantity_changed
            | assigned
            | reassigned
            | status_changed
            | skipped
            |--------------------------------------------------------------------------
            */
            $table->string('action', 100);

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
            $table->string('source', 32)->default('user');

            /*
            |--------------------------------------------------------------------------
            | Change snapshot
            |--------------------------------------------------------------------------
            */
            $table->json('before_json')->nullable();

            $table->json('after_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            |
            | Useful for chat/action/audit tracing.
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index([
                'prep_item_id',
                'created_at',
            ]);

            $table->index([
                'workspace_id',
                'action',
                'created_at',
            ]);

            $table->index('correlation_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Prep List Version Changes
        |--------------------------------------------------------------------------
        |
        | Allows comparison between generated/approved versions.
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_list_version_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_list_id')
                ->constrained('prep_lists')
                ->cascadeOnDelete();

            $table->foreignUlid('from_version_id')
                ->nullable()
                ->constrained('prep_list_versions')
                ->nullOnDelete();

            $table->foreignUlid('to_version_id')
                ->constrained('prep_list_versions')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Change
            |--------------------------------------------------------------------------
            |
            | item.added
            | item.removed
            | quantity.changed
            | due_at.changed
            | assignment.changed
            | dependency.changed
            |--------------------------------------------------------------------------
            */
            $table->string('change_type', 100);

            $table->string('entity_type', 64)->nullable();

            $table->ulid('entity_id')->nullable();

            $table->json('before_value')->nullable();

            $table->json('after_value')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Severity
            |--------------------------------------------------------------------------
            */
            $table->enum('severity', [
                'info',
                'warning',
                'critical',
            ])->default('info');

            /*
            |--------------------------------------------------------------------------
            | Review
            |--------------------------------------------------------------------------
            */
            $table->boolean('reviewed')->default(false);

            $table->foreignUlid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index([
                'prep_list_id',
                'to_version_id',
            ]);

            $table->index([
                'workspace_id',
                'severity',
                'reviewed',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Prep Generation Runs
        |--------------------------------------------------------------------------
        |
        | Tracks manual/AI/system generation attempts separately from the
        | resulting version.
        |--------------------------------------------------------------------------
        */
        Schema::create('prep_generation_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_list_id')
                ->nullable()
                ->constrained('prep_lists')
                ->nullOnDelete();

            $table->foreignUlid('prep_list_version_id')
                ->nullable()
                ->constrained('prep_list_versions')
                ->nullOnDelete();

            $table->foreignUlid('menu_version_id')
                ->nullable()
                ->constrained('menu_versions')
                ->nullOnDelete();

            $table->foreignUlid('beo_version_id')
                ->nullable()
                ->constrained('beo_versions')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'processing',
                'review_required',
                'completed',
                'failed',
                'cancelled',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Generation type
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('ai');

            /*
            |--------------------------------------------------------------------------
            | AI/system trace
            |--------------------------------------------------------------------------
            */
            $table->string('model_key', 100)->nullable();

            $table->string('prompt_version', 64)->nullable();

            $table->json('input_snapshot')->nullable();

            $table->json('output_summary')->nullable();

            $table->json('usage_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Metrics
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('latency_ms')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Errors
            |--------------------------------------------------------------------------
            */
            $table->string('error_code', 100)->nullable();

            $table->text('error_message')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('started_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Requested by
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'event_id',
                'status',
            ]);

            $table->index([
                'prep_list_id',
                'created_at',
            ]);

            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prep_generation_runs');
        Schema::dropIfExists('prep_list_version_changes');

        Schema::dropIfExists('prep_item_updates');
        Schema::dropIfExists('prep_item_dependencies');
        Schema::dropIfExists('prep_item_assignments');
        Schema::dropIfExists('prep_items');

        Schema::dropIfExists('prep_sections');
        Schema::dropIfExists('prep_list_versions');
        Schema::dropIfExists('prep_lists');
    }
};
