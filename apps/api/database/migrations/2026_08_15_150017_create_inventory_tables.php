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
        | Inventory Locations
        |--------------------------------------------------------------------------
        |
        | Examples:
        | Main Walk-in
        | Freezer
        | Dry Storage
        | Event Truck
        | Commissary
        |--------------------------------------------------------------------------
        */
        Schema::create('inventory_locations', function (Blueprint $table) {
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
            | dry_storage
            | refrigerator
            | freezer
            | warehouse
            | vehicle
            | event
            | other
            */
            $table->string('type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional venue relation
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('venue_id')
                ->nullable()
                ->constrained('venues')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Parent location
            |--------------------------------------------------------------------------
            |
            | Allows:
            | Main Kitchen
            | ├── Walk-in
            | ├── Freezer
            | └── Dry Storage
            */
            $table->foreignUlid('parent_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Environment
            |--------------------------------------------------------------------------
            */
            $table->decimal('temperature_min', 10, 2)->nullable();

            $table->decimal('temperature_max', 10, 2)->nullable();

            $table->foreignUlid('temperature_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->string('timezone', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            */
            $table->string('status', 32)->default('active');

            $table->unsignedInteger('position')->default(0);

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
                'status',
            ]);

            $table->index([
                'workspace_id',
                'type',
            ]);

            $table->index([
                'workspace_id',
                'parent_location_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Inventory Items
        |--------------------------------------------------------------------------
        */
        Schema::create('inventory_items', function (Blueprint $table) {
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

            $table->string('sku', 100)->nullable();

            $table->string('barcode', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Item type/category
            |--------------------------------------------------------------------------
            */
            $table->string('category', 100)->nullable();

            $table->string('type', 64)->default('ingredient');

            /*
            |--------------------------------------------------------------------------
            | Base unit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('base_unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Purchase defaults
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('purchase_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->decimal('purchase_to_base_factor', 18, 8)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cost
            |--------------------------------------------------------------------------
            */
            $table->decimal('current_cost', 14, 4)->nullable();

            $table->char('cost_currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Tracking behavior
            |--------------------------------------------------------------------------
            */
            $table->boolean('track_lots')->default(false);

            $table->boolean('track_expiration')->default(false);

            $table->boolean('allow_negative_stock')->default(false);

            /*
            |--------------------------------------------------------------------------
            | Shelf life
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('default_shelf_life_days')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->boolean('active')->default(true);

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
                'sku',
            ]);

            $table->index([
                'workspace_id',
                'name',
            ]);

            $table->index([
                'workspace_id',
                'category',
            ]);

            $table->index([
                'workspace_id',
                'active',
            ]);

            $table->index([
                'workspace_id',
                'barcode',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Stock Lots
        |--------------------------------------------------------------------------
        */
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_location_id')
                ->constrained('inventory_locations')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Lot identity
            |--------------------------------------------------------------------------
            */
            $table->string('lot_number', 120)->nullable();

            $table->string('supplier_lot_number', 120)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity_received', 18, 4)->nullable();

            $table->decimal('quantity_on_hand', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cost snapshot
            |--------------------------------------------------------------------------
            */
            $table->decimal('unit_cost', 14, 4)->nullable();

            $table->char('currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */
            $table->date('received_date')->nullable();

            $table->date('manufactured_at')->nullable();

            $table->date('expires_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Supplier relation
            |--------------------------------------------------------------------------
            |
            | Only use this FK if suppliers migration runs before inventory.
            */
            $table->foreignUlid('supplier_id')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Receipt relation
            |--------------------------------------------------------------------------
            |
            | If receipts table is created later, FK can be added later.
            */
            $table->foreignUlid('receipt_id')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | available
            | reserved
            | depleted
            | expired
            | quarantined
            */
            $table->string('status', 32)->default('available');

            $table->text('notes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'inventory_item_id',
                'inventory_location_id',
            ]);

            $table->index([
                'workspace_id',
                'expires_at',
            ]);

            $table->index([
                'inventory_item_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'lot_number',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Stock Movements
        |--------------------------------------------------------------------------
        |
        | This should be the immutable operational ledger.
        |--------------------------------------------------------------------------
        */
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Lot
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('stock_lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Transfer destination/source
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('from_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->foreignUlid('to_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Type
            |--------------------------------------------------------------------------
            */
            $table->enum('type', [
                'receive',
                'consume',
                'adjustment_in',
                'adjustment_out',
                'transfer',
                'waste',
                'count_adjustment',
                'return_to_supplier',
                'return_from_event',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            |
            | Prefer positive quantities and let type indicate direction.
            */
            $table->decimal('quantity', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Normalized base quantity
            |--------------------------------------------------------------------------
            |
            | Useful for inventory totals regardless of movement unit.
            */
            $table->decimal('base_quantity', 18, 4)->nullable();

            $table->foreignUlid('base_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cost snapshot
            |--------------------------------------------------------------------------
            */
            $table->decimal('unit_cost', 14, 4)->nullable();

            $table->decimal('total_cost', 14, 4)->nullable();

            $table->char('currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Generic reference
            |--------------------------------------------------------------------------
            |
            | purchase_order
            | receipt
            | prep_item
            | event
            | waste_entry
            | stock_count
            */
            $table->string('reference_type', 80)->nullable();

            $table->ulid('reference_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | user
            | system
            | ai
            | receiving
            | production
            | count
            */
            $table->string('source', 32)->default('user');

            /*
            |--------------------------------------------------------------------------
            | Timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('occurred_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Reason
            |--------------------------------------------------------------------------
            */
            $table->string('reason', 150)->nullable();

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

            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'inventory_item_id',
                'occurred_at',
            ]);

            $table->index([
                'workspace_id',
                'inventory_location_id',
                'occurred_at',
            ]);

            $table->index([
                'stock_lot_id',
                'occurred_at',
            ]);

            $table->index([
                'reference_type',
                'reference_id',
            ]);

            $table->index('correlation_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Stock Counts
        |--------------------------------------------------------------------------
        |
        | Header for one inventory counting session.
        |--------------------------------------------------------------------------
        */
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_location_id')
                ->constrained('inventory_locations')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Count timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('started_at')->nullable();

            $table->timestamp('counted_at')->nullable();

            $table->timestamp('completed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Snapshot behavior
            |--------------------------------------------------------------------------
            |
            | Useful to know if adjustments were posted after completion.
            */
            $table->boolean('adjustments_posted')->default(false);

            $table->timestamp('adjustments_posted_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('started_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('counted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUlid('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'inventory_location_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'counted_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Stock Count Items
        |--------------------------------------------------------------------------
        |
        | Without this table stock_counts is only a header and cannot store
        | what was actually counted.
        |--------------------------------------------------------------------------
        */
        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('stock_count_id')
                ->constrained('stock_counts')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->foreignUlid('stock_lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Expected quantity snapshot
            |--------------------------------------------------------------------------
            */
            $table->decimal('expected_quantity', 18, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Actual counted quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('counted_quantity', 18, 4)->nullable();

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Variance
            |--------------------------------------------------------------------------
            */
            $table->decimal('variance_quantity', 18, 4)->nullable();

            $table->decimal('variance_cost', 14, 4)->nullable();

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

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'stock_count_id',
                'inventory_item_id',
                'stock_lot_id',
            ]);

            $table->index([
                'stock_count_id',
                'reviewed',
            ]);

            $table->index([
                'workspace_id',
                'inventory_item_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Par Levels
        |--------------------------------------------------------------------------
        */
        Schema::create('par_levels', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_location_id')
                ->constrained('inventory_locations')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Reorder point
            |--------------------------------------------------------------------------
            */
            $table->decimal('minimum_quantity', 18, 4);

            /*
            |--------------------------------------------------------------------------
            | Target stock
            |--------------------------------------------------------------------------
            */
            $table->decimal('target_quantity', 18, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional maximum
            |--------------------------------------------------------------------------
            */
            $table->decimal('maximum_quantity', 18, 4)->nullable();

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional reorder quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('reorder_quantity', 18, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Lead time
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('lead_time_days')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique([
                'inventory_item_id',
                'inventory_location_id',
            ]);

            $table->index([
                'workspace_id',
                'active',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Inventory Reservations
        |--------------------------------------------------------------------------
        |
        | Important distinction:
        |
        | on hand != available
        |
        | Example:
        | 100 kg on hand
        | 40 kg reserved for Event A
        | available = 60 kg
        |--------------------------------------------------------------------------
        */
        Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->foreignUlid('stock_lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Reservation source
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('event_id')
                ->nullable()
                ->constrained('events')
                ->cascadeOnDelete();

            $table->foreignUlid('prep_item_id')
                ->nullable()
                ->constrained('prep_items')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'reserved',
                'partially_consumed',
                'consumed',
                'released',
                'cancelled',
            ])->default('reserved');

            /*
            |--------------------------------------------------------------------------
            | Expiration
            |--------------------------------------------------------------------------
            |
            | Optional reservation expiration to avoid abandoned reservations.
            */
            $table->timestamp('expires_at')->nullable();

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
                'inventory_item_id',
                'status',
            ]);

            $table->index([
                'event_id',
                'status',
            ]);

            $table->index([
                'prep_item_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Waste Entries
        |--------------------------------------------------------------------------
        */
        Schema::create('waste_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->foreignUlid('stock_lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Event / prep source
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            $table->foreignUlid('prep_item_id')
                ->nullable()
                ->constrained('prep_items')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Cost snapshot
            |--------------------------------------------------------------------------
            */
            $table->decimal('unit_cost', 14, 4)->nullable();

            $table->decimal('total_cost', 14, 4)->nullable();

            $table->char('currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Waste reason
            |--------------------------------------------------------------------------
            |
            | spoilage
            | overproduction
            | trimming
            | expired
            | damaged
            | dropped
            | quality
            | returned
            | other
            */
            $table->string('reason', 64)->nullable();

            $table->text('notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Timing
            |--------------------------------------------------------------------------
            */
            $table->timestamp('occurred_at')->nullable();

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
                'inventory_item_id',
                'occurred_at',
            ]);

            $table->index([
                'workspace_id',
                'event_id',
            ]);

            $table->index([
                'workspace_id',
                'reason',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Inventory Item Cost History
        |--------------------------------------------------------------------------
        |
        | current_cost is only the latest value.
        | Historical recipe/event costing requires cost history.
        |--------------------------------------------------------------------------
        */
        Schema::create('inventory_item_cost_history', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->decimal('unit_cost', 14, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->char('currency', 3);

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | receipt
            | purchase_order
            | manual
            | supplier
            */
            $table->string('source', 32)->default('manual');

            $table->string('reference_type', 80)->nullable();

            $table->ulid('reference_id')->nullable();

            $table->timestamp('effective_at');

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'inventory_item_id',
                'effective_at',
            ]);

            $table->index([
                'workspace_id',
                'effective_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_cost_history');
        Schema::dropIfExists('waste_entries');
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('par_levels');

        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_lots');

        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_locations');
    }
};
