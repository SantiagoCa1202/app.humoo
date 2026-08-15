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
        | Cost Snapshots
        |--------------------------------------------------------------------------
        |
        | Historical cost snapshot for an entity at a point in time.
        |
        | Examples:
        | event
        | recipe_version
        | menu_version
        | prep_list_version
        | workspace
        |--------------------------------------------------------------------------
        */
        Schema::create('cost_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Entity reference
            |--------------------------------------------------------------------------
            */
            $table->string('entity_type', 80);
            $table->ulid('entity_id');

            /*
            |--------------------------------------------------------------------------
            | Snapshot type
            |--------------------------------------------------------------------------
            |
            | estimate
            | approved
            | actual
            | final
            |--------------------------------------------------------------------------
            */
            $table->string('snapshot_type', 32)->default('estimate');

            /*
            |--------------------------------------------------------------------------
            | Costs
            |--------------------------------------------------------------------------
            */
            $table->decimal('food_cost', 14, 2)->default(0);
            $table->decimal('labor_cost', 14, 2)->default(0);
            $table->decimal('purchasing_cost', 14, 2)->default(0);
            $table->decimal('waste_cost', 14, 2)->default(0);
            $table->decimal('other_cost', 14, 2)->default(0);

            $table->decimal('total_cost', 14, 2)->default(0);

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Revenue / profitability
            |--------------------------------------------------------------------------
            |
            | Nullable because not every entity has revenue.
            */
            $table->decimal('revenue', 14, 2)->nullable();

            $table->decimal('gross_profit', 14, 2)->nullable();

            $table->decimal('food_cost_percentage', 7, 4)->nullable();
            $table->decimal('labor_cost_percentage', 7, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Calculation
            |--------------------------------------------------------------------------
            */
            $table->timestamp('calculated_at');

            /*
            |--------------------------------------------------------------------------
            | Calculation version
            |--------------------------------------------------------------------------
            |
            | Useful if costing logic changes later.
            */
            $table->string('calculation_version', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Breakdown snapshot
            |--------------------------------------------------------------------------
            */
            $table->json('breakdown_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | system
            | user
            | ai
            | report
            */
            $table->string('source', 32)->default('system');

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

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

            $table->index([
                'workspace_id',
                'entity_type',
                'entity_id',
            ]);

            $table->index([
                'workspace_id',
                'snapshot_type',
                'calculated_at',
            ]);

            $table->index([
                'entity_type',
                'entity_id',
                'calculated_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Labor Entries
        |--------------------------------------------------------------------------
        |
        | Actual or manually entered labor.
        |--------------------------------------------------------------------------
        */
        Schema::create('labor_entries', function (Blueprint $table) {
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
            | Optional shift origin
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('shift_id')
                ->nullable()
                ->constrained('shifts')
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
            | Timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            $table->unsignedInteger('break_minutes')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Calculated hours snapshot
            |--------------------------------------------------------------------------
            */
            $table->decimal('hours', 10, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Rate
            |--------------------------------------------------------------------------
            */
            $table->decimal('hourly_rate', 12, 4)->nullable();

            $table->decimal('overtime_rate', 12, 4)->nullable();

            $table->decimal('regular_hours', 10, 4)->nullable();
            $table->decimal('overtime_hours', 10, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cost
            |--------------------------------------------------------------------------
            */
            $table->decimal('labor_cost', 14, 2)->nullable();

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Type
            |--------------------------------------------------------------------------
            |
            | scheduled
            | actual
            | manual
            | adjustment
            */
            $table->string('type', 32)->default('actual');

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'approved',
                'rejected',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Approval
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | shift
            | manual
            | import
            | system
            */
            $table->string('source', 32)->default('manual');

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

            $table->index([
                'workspace_id',
                'membership_id',
                'starts_at',
            ]);

            $table->index([
                'workspace_id',
                'event_id',
            ]);

            $table->index([
                'workspace_id',
                'status',
                'starts_at',
            ]);

            $table->index([
                'shift_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Report Definitions
        |--------------------------------------------------------------------------
        |
        | Saved report templates/configuration.
        |--------------------------------------------------------------------------
        */
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 180);

            /*
            |--------------------------------------------------------------------------
            | Canonical key
            |--------------------------------------------------------------------------
            |
            | event_cost
            | food_cost_trend
            | labor_summary
            | waste_summary
            | purchasing_variance
            */
            $table->string('key', 100);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Report category
            |--------------------------------------------------------------------------
            */
            $table->string('type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Configuration
            |--------------------------------------------------------------------------
            |
            | Selected columns, grouping, sorting, defaults, etc.
            */
            $table->json('configuration_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Access
            |--------------------------------------------------------------------------
            |
            | private
            | workspace
            */
            $table->string('visibility', 32)->default('workspace');

            /*
            |--------------------------------------------------------------------------
            | System report
            |--------------------------------------------------------------------------
            |
            | true = built-in report definition
            */
            $table->boolean('system')->default(false);

            $table->boolean('active')->default(true);

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
                'active',
            ]);

            $table->index([
                'workspace_id',
                'type',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Report Runs
        |--------------------------------------------------------------------------
        |
        | Each execution of a report.
        |--------------------------------------------------------------------------
        */
        Schema::create('report_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('report_definition_id')
                ->constrained('report_definitions')
                ->cascadeOnDelete();

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
            | Filters snapshot
            |--------------------------------------------------------------------------
            */
            $table->json('filters_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Report result
            |--------------------------------------------------------------------------
            |
            | Keep smaller results here.
            | Large exports should go through documents/exports.
            */
            $table->json('result_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Summary
            |--------------------------------------------------------------------------
            */
            $table->json('summary_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Error handling
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

            $table->unsignedInteger('duration_ms')->nullable();

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
            | Source
            |--------------------------------------------------------------------------
            |
            | dashboard
            | chat
            | scheduled
            | api
            */
            $table->string('source', 32)->default('dashboard');

            /*
            |--------------------------------------------------------------------------
            | Correlation
            |--------------------------------------------------------------------------
            */
            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'report_definition_id',
                'created_at',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index('correlation_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Exports
        |--------------------------------------------------------------------------
        |
        | Generated files such as PDF, CSV or XLSX.
        |--------------------------------------------------------------------------
        */
        Schema::create('exports', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional report run
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('report_run_id')
                ->nullable()
                ->constrained('report_runs')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Export type
            |--------------------------------------------------------------------------
            |
            | event_cost
            | recipe_cost
            | prep_list
            | inventory
            | purchasing
            | report
            */
            $table->string('type', 64);

            /*
            |--------------------------------------------------------------------------
            | Format
            |--------------------------------------------------------------------------
            |
            | pdf
            | csv
            | xlsx
            */
            $table->string('format', 32);

            /*
            |--------------------------------------------------------------------------
            | Generated document
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'expired',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Entity source
            |--------------------------------------------------------------------------
            |
            | Optional direct export source:
            | event
            | recipe_version
            | purchase_order
            | etc.
            */
            $table->string('entity_type', 80)->nullable();
            $table->ulid('entity_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Export configuration
            |--------------------------------------------------------------------------
            */
            $table->json('options_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Error handling
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
            | Expiration
            |--------------------------------------------------------------------------
            |
            | Generated documents/links may be temporary.
            */
            $table->timestamp('expires_at')->nullable();

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
            | Source
            |--------------------------------------------------------------------------
            |
            | dashboard
            | chat
            | scheduled
            | api
            */
            $table->string('source', 32)->default('dashboard');

            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'status',
                'created_at',
            ]);

            $table->index([
                'workspace_id',
                'type',
            ]);

            $table->index([
                'entity_type',
                'entity_id',
            ]);

            $table->index('report_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
        Schema::dropIfExists('report_runs');
        Schema::dropIfExists('report_definitions');
        Schema::dropIfExists('labor_entries');
        Schema::dropIfExists('cost_snapshots');
    }
};
