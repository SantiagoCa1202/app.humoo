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
        | Teams
        |--------------------------------------------------------------------------
        |
        | Logical groups inside a workspace.
        |
        | Examples:
        | Kitchen
        | Pastry
        | Service
        | Event Crew
        | Purchasing
        |--------------------------------------------------------------------------
        */
        Schema::create('teams', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 150);

            $table->string('key', 100)->nullable();

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Type
            |--------------------------------------------------------------------------
            |
            | kitchen
            | service
            | production
            | purchasing
            | management
            | custom
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64)->nullable();

            $table->string('status', 32)->default('active');

            /*
            |--------------------------------------------------------------------------
            | Optional lead
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('lead_membership_id')
                ->nullable()
                ->constrained('workspace_memberships')
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

            $table->unique([
                'workspace_id',
                'key',
            ]);

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
        | Team Members
        |--------------------------------------------------------------------------
        |
        | A workspace member can belong to multiple teams.
        |--------------------------------------------------------------------------
        */
        Schema::create('team_members', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignUlid('membership_id')
                ->constrained('workspace_memberships')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Team-specific role
            |--------------------------------------------------------------------------
            |
            | Does NOT replace RBAC role.
            |
            | Examples:
            | lead
            | member
            | supervisor
            |--------------------------------------------------------------------------
            */
            $table->string('role', 64)->nullable();

            $table->boolean('is_lead')->default(false);

            $table->string('status', 32)->default('active');

            $table->timestamp('joined_at')->nullable();

            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            $table->unique([
                'team_id',
                'membership_id',
            ]);

            $table->index([
                'workspace_id',
                'membership_id',
            ]);

            $table->index([
                'team_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Stations
        |--------------------------------------------------------------------------
        |
        | Operational work areas.
        |
        | Examples:
        | hot_line
        | cold_prep
        | pastry
        | garde_manger
        | grill
        | plating
        |--------------------------------------------------------------------------
        */
        Schema::create('stations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 150);

            $table->string('key', 100)->nullable();

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional team
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Type
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Capacity
            |--------------------------------------------------------------------------
            |
            | Optional rough simultaneous staffing capacity.
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('capacity')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Sort order for operational UI
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('position')->default(0);

            $table->string('status', 32)->default('active');

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

            $table->unique([
                'workspace_id',
                'key',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'team_id',
            ]);

            $table->index([
                'workspace_id',
                'position',
            ]);
        });

        Schema::table('recipe_steps', function (Blueprint $table) {
            $table->foreign('station_id')
                ->references('id')
                ->on('stations')
                ->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Tasks
        |--------------------------------------------------------------------------
        */
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional event scope
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional station/team
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
            | Task identity
            |--------------------------------------------------------------------------
            */
            $table->string('title', 255);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Type
            |--------------------------------------------------------------------------
            |
            | general
            | production
            | prep
            | inventory
            | purchasing
            | cleaning
            | service
            | administrative
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64)->default('general');

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
                'cancelled',
            ])->default('todo');

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
            | Scheduling
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at')->nullable();

            $table->timestamp('due_at')->nullable();

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
            | Blocking
            |--------------------------------------------------------------------------
            */
            $table->text('blocked_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | user
            | ai
            | system
            | automation
            | prep
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('user');

            /*
            |--------------------------------------------------------------------------
            | Generic source entity
            |--------------------------------------------------------------------------
            |
            | Allows task to originate from:
            | prep_item
            | purchase_order
            | inventory_item
            | BEO change
            |
            | Use canonical keys, not PHP class names.
            |--------------------------------------------------------------------------
            */
            $table->string('source_type', 80)->nullable();

            $table->ulid('source_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optimistic locking
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

            $table->index([
                'workspace_id',
                'status',
                'due_at',
            ]);

            $table->index([
                'workspace_id',
                'event_id',
            ]);

            $table->index([
                'workspace_id',
                'station_id',
            ]);

            $table->index([
                'workspace_id',
                'team_id',
            ]);

            $table->index([
                'workspace_id',
                'priority',
                'due_at',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Task Assignments
        |--------------------------------------------------------------------------
        |
        | Tasks may have one or multiple assignees.
        |--------------------------------------------------------------------------
        */
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('task_id')
                ->constrained('tasks')
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

            /*
            |--------------------------------------------------------------------------
            | Primary owner
            |--------------------------------------------------------------------------
            */
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
            | Assigned by
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'task_id',
                'membership_id',
            ]);

            $table->index([
                'workspace_id',
                'membership_id',
                'status',
            ]);

            $table->index([
                'task_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Task Dependencies
        |--------------------------------------------------------------------------
        |
        | Enables:
        |
        | Task B cannot start until Task A is complete.
        |--------------------------------------------------------------------------
        */
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();

            $table->foreignUlid('depends_on_task_id')
                ->constrained('tasks')
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

            $table->timestamps();

            $table->unique([
                'task_id',
                'depends_on_task_id',
            ]);

            $table->index([
                'workspace_id',
                'task_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Shifts
        |--------------------------------------------------------------------------
        */
        Schema::create('shifts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('membership_id')
                ->constrained('workspace_memberships')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional event
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional team/station
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->foreignUlid('station_id')
                ->nullable()
                ->constrained('stations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Timing
            |--------------------------------------------------------------------------
            |
            | Store timestamps in UTC.
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at');

            $table->timestamp('ends_at');

            /*
            |--------------------------------------------------------------------------
            | Timezone snapshot
            |--------------------------------------------------------------------------
            |
            | Useful if venue/workspace timezone later changes.
            |--------------------------------------------------------------------------
            */
            $table->string('timezone', 64);

            /*
            |--------------------------------------------------------------------------
            | Break
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('break_minutes')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Shift role
            |--------------------------------------------------------------------------
            |
            | chef
            | sous_chef
            | prep_cook
            | server
            | bartender
            | captain
            |--------------------------------------------------------------------------
            */
            $table->string('role', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'scheduled',
                'confirmed',
                'in_progress',
                'completed',
                'cancelled',
                'no_show',
            ])->default('scheduled');

            /*
            |--------------------------------------------------------------------------
            | Actual clock times
            |--------------------------------------------------------------------------
            */
            $table->timestamp('clocked_in_at')->nullable();

            $table->timestamp('clocked_out_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */
            $table->text('notes')->nullable();

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
                'membership_id',
                'starts_at',
            ]);

            $table->index([
                'workspace_id',
                'event_id',
                'starts_at',
            ]);

            $table->index([
                'workspace_id',
                'station_id',
                'starts_at',
            ]);

            $table->index([
                'workspace_id',
                'status',
                'starts_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Availability
        |--------------------------------------------------------------------------
        |
        | Concrete availability/unavailability windows.
        |--------------------------------------------------------------------------
        */
        Schema::create('availability', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('membership_id')
                ->constrained('workspace_memberships')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Time range
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at');

            $table->timestamp('ends_at');

            $table->string('timezone', 64);

            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            */
            $table->boolean('available')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Type
            |--------------------------------------------------------------------------
            |
            | available
            | unavailable
            | preferred
            | time_off
            |--------------------------------------------------------------------------
            */
            $table->string('type', 32)->default('available');

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | user
            | manager
            | system
            | recurrence
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('user');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'membership_id',
                'starts_at',
            ]);

            $table->index([
                'workspace_id',
                'membership_id',
                'available',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Availability Rules
        |--------------------------------------------------------------------------
        |
        | Reusable weekly availability.
        |
        | Example:
        | Monday-Friday 08:00-17:00
        |--------------------------------------------------------------------------
        */
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('membership_id')
                ->constrained('workspace_memberships')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | ISO weekday
            |--------------------------------------------------------------------------
            |
            | 1 = Monday
            | 7 = Sunday
            |--------------------------------------------------------------------------
            */
            $table->unsignedTinyInteger('day_of_week');

            $table->time('starts_at');

            $table->time('ends_at');

            $table->string('timezone', 64);

            $table->boolean('available')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Effective date range
            |--------------------------------------------------------------------------
            */
            $table->date('effective_from')->nullable();

            $table->date('effective_until')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index([
                'workspace_id',
                'membership_id',
                'day_of_week',
            ]);

            $table->index([
                'membership_id',
                'active',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Shift Conflicts
        |--------------------------------------------------------------------------
        |
        | Optional persistent record of detected scheduling problems.
        |
        | Useful for:
        | EventConflictAlert
        | TeamWorkloadCard
        |--------------------------------------------------------------------------
        */
        Schema::create('shift_conflicts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('membership_id')
                ->constrained('workspace_memberships')
                ->cascadeOnDelete();

            $table->foreignUlid('shift_id')
                ->nullable()
                ->constrained('shifts')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Conflict
            |--------------------------------------------------------------------------
            |
            | overlap
            | unavailable
            | overtime
            | station_capacity
            | event_overlap
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64);

            $table->enum('severity', [
                'info',
                'warning',
                'critical',
            ])->default('warning');

            $table->text('message')->nullable();

            $table->json('details')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Resolution
            |--------------------------------------------------------------------------
            */
            $table->boolean('resolved')->default(false);

            $table->foreignUlid('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();

            $table->text('resolution_notes')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'membership_id',
                'resolved',
            ]);

            $table->index([
                'workspace_id',
                'severity',
                'resolved',
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('recipe_steps')) {
            Schema::table('recipe_steps', function (Blueprint $table) {
                $table->dropForeign(['station_id']);
            });
        }

        Schema::dropIfExists('shift_conflicts');

        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('availability');

        Schema::dropIfExists('shifts');

        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');

        Schema::dropIfExists('stations');

        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
