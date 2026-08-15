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
        | Suppliers
        |--------------------------------------------------------------------------
        */
        Schema::create('suppliers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->string('name', 180);

            /*
            |--------------------------------------------------------------------------
            | Supplier identity
            |--------------------------------------------------------------------------
            */
            $table->string('code', 64)->nullable();

            $table->string('company_name', 180)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Contact
            |--------------------------------------------------------------------------
            */
            $table->string('email', 255)->nullable();

            $table->string('phone', 32)->nullable();

            $table->string('website', 2048)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Primary contact
            |--------------------------------------------------------------------------
            */
            $table->string('contact_name', 180)->nullable();

            $table->string('contact_email', 255)->nullable();

            $table->string('contact_phone', 32)->nullable();

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
            | Tax / business
            |--------------------------------------------------------------------------
            */
            $table->string('tax_id', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Purchase defaults
            |--------------------------------------------------------------------------
            */
            $table->char('currency', 3)->default('USD');

            $table->unsignedInteger('lead_time_days')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Minimum order
            |--------------------------------------------------------------------------
            */
            $table->decimal('minimum_order_amount', 14, 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Payment terms
            |--------------------------------------------------------------------------
            |
            | Examples:
            | due_on_receipt
            | net_15
            | net_30
            | net_60
            */
            $table->string('payment_terms', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->string('status', 32)->default('active');

            /*
            |--------------------------------------------------------------------------
            | Preferred supplier
            |--------------------------------------------------------------------------
            */
            $table->boolean('preferred')->default(false);

            $table->text('notes')->nullable();

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
                'code',
            ]);

            $table->index([
                'workspace_id',
                'name',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'preferred',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Supplier Items
        |--------------------------------------------------------------------------
        |
        | Supplier-specific catalog entry for an inventory item.
        |--------------------------------------------------------------------------
        */
        Schema::create('supplier_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Supplier catalog info
            |--------------------------------------------------------------------------
            */
            $table->string('supplier_sku', 100)->nullable();

            $table->string('supplier_name', 180)->nullable();

            $table->string('brand', 120)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Purchase unit
            |--------------------------------------------------------------------------
            |
            | Example:
            | Inventory base = gram
            | Supplier sells = case
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Pack size
            |--------------------------------------------------------------------------
            |
            | Example:
            | 1 case = 12 units
            */
            $table->decimal('pack_quantity', 18, 4)->nullable();

            $table->foreignUlid('pack_unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Conversion to inventory base unit
            |--------------------------------------------------------------------------
            */
            $table->decimal('base_unit_factor', 18, 8)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Current supplier price
            |--------------------------------------------------------------------------
            */
            $table->decimal('price', 14, 4)->nullable();

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Minimum purchase
            |--------------------------------------------------------------------------
            */
            $table->decimal('minimum_order_quantity', 18, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Lead time override
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('lead_time_days')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Supplier priority
            |--------------------------------------------------------------------------
            */
            $table->boolean('preferred')->default(false);

            $table->boolean('active')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Last price update
            |--------------------------------------------------------------------------
            */
            $table->timestamp('price_updated_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique([
                'supplier_id',
                'inventory_item_id',
            ]);

            $table->index([
                'workspace_id',
                'inventory_item_id',
                'active',
            ]);

            $table->index([
                'supplier_id',
                'supplier_sku',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Purchase Orders
        |--------------------------------------------------------------------------
        */
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Destination location
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Human readable number
            |--------------------------------------------------------------------------
            |
            | Example:
            | PO-2026-00142
            */
            $table->string('number', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Supplier reference
            |--------------------------------------------------------------------------
            */
            $table->string('supplier_reference')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'submitted',
                'confirmed',
                'partially_received',
                'received',
                'cancelled',
                'closed',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Ordering timeline
            |--------------------------------------------------------------------------
            */
            $table->timestamp('ordered_at')->nullable();

            $table->timestamp('submitted_at')->nullable();

            $table->timestamp('confirmed_at')->nullable();

            $table->timestamp('expected_at')->nullable();

            $table->timestamp('received_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Monetary totals
            |--------------------------------------------------------------------------
            */
            $table->decimal('subtotal', 14, 2)->default(0);

            $table->decimal('discount', 14, 2)->default(0);

            $table->decimal('shipping', 14, 2)->default(0);

            $table->decimal('tax', 14, 2)->default(0);

            $table->decimal('total', 14, 2)->default(0);

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Payment terms snapshot
            |--------------------------------------------------------------------------
            */
            $table->string('payment_terms', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Event/source trace
            |--------------------------------------------------------------------------
            |
            | A PO can be created because of shortages from one event.
            */
            $table->foreignUlid('event_id')
                ->nullable()
                ->constrained('events')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Generic source
            |--------------------------------------------------------------------------
            |
            | manual
            | par_level
            | event_shortage
            | prep
            | ai
            */
            $table->string('source', 32)->default('manual');

            $table->string('source_type', 80)->nullable();

            $table->ulid('source_id')->nullable();

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
            | Cancellation
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();

            $table->text('cancellation_reason')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Notes
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
                'number',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'supplier_id',
                'created_at',
            ]);

            $table->index([
                'workspace_id',
                'expected_at',
            ]);

            $table->index([
                'event_id',
                'status',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Items
        |--------------------------------------------------------------------------
        */
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Supplier catalog link
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('supplier_item_id')
                ->nullable()
                ->constrained('supplier_items')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Snapshot display
            |--------------------------------------------------------------------------
            |
            | Keep historical item name even if inventory item is renamed.
            */
            $table->string('item_name', 180)->nullable();

            $table->string('supplier_sku', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Ordered quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Received quantity
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity_received', 18, 4)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Cancelled / outstanding
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity_cancelled', 18, 4)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Price
            |--------------------------------------------------------------------------
            */
            $table->decimal('unit_price', 14, 4);

            $table->decimal('discount', 14, 2)->default(0);

            $table->decimal('tax', 14, 2)->default(0);

            $table->decimal('total', 14, 2);

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Line status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'open',
                'partially_received',
                'received',
                'cancelled',
            ])->default('open');

            /*
            |--------------------------------------------------------------------------
            | Optional need/source
            |--------------------------------------------------------------------------
            |
            | prep_item
            | inventory_shortage
            | par_level
            */
            $table->string('source_type', 80)->nullable();

            $table->ulid('source_id')->nullable();

            $table->text('notes')->nullable();

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index([
                'purchase_order_id',
                'position',
            ]);

            $table->index([
                'workspace_id',
                'inventory_item_id',
            ]);

            $table->index([
                'purchase_order_id',
                'status',
            ]);

            $table->index([
                'source_type',
                'source_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Receipts
        |--------------------------------------------------------------------------
        |
        | A PO can have multiple receipts / deliveries.
        |--------------------------------------------------------------------------
        */
        Schema::create('receipts', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Receiving location
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Receipt number/reference
            |--------------------------------------------------------------------------
            */
            $table->string('number', 64)->nullable();

            $table->string('supplier_delivery_number')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'receiving',
                'completed',
                'cancelled',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Receiving time
            |--------------------------------------------------------------------------
            */
            $table->timestamp('received_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Received by
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Delivery condition
            |--------------------------------------------------------------------------
            |
            | accepted
            | accepted_with_issues
            | rejected
            */
            $table->string('condition_status', 32)->nullable();

            $table->text('notes')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique([
                'workspace_id',
                'number',
            ]);

            $table->index([
                'workspace_id',
                'purchase_order_id',
                'received_at',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Receipt Items
        |--------------------------------------------------------------------------
        |
        | Critical for partial deliveries and lot creation.
        |--------------------------------------------------------------------------
        */
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('receipt_id')
                ->constrained('receipts')
                ->cascadeOnDelete();

            $table->foreignUlid('purchase_order_item_id')
                ->constrained('purchase_order_items')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Quantity received
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity_received', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Accepted/rejected quantities
            |--------------------------------------------------------------------------
            */
            $table->decimal('quantity_accepted', 18, 4)->nullable();

            $table->decimal('quantity_rejected', 18, 4)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Actual received price
            |--------------------------------------------------------------------------
            |
            | Can differ from PO.
            */
            $table->decimal('unit_cost', 14, 4)->nullable();

            $table->char('currency', 3)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Lot info
            |--------------------------------------------------------------------------
            */
            $table->string('lot_number', 120)->nullable();

            $table->date('manufactured_at')->nullable();

            $table->date('expires_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Created stock lot
            |--------------------------------------------------------------------------
            |
            | This can be linked after the inventory movement/lot is created.
            */
            $table->foreignUlid('stock_lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Quality / rejection
            |--------------------------------------------------------------------------
            */
            $table->string('condition_status', 32)->nullable();

            $table->string('rejection_reason', 150)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'receipt_id',
                'purchase_order_item_id',
            ]);

            $table->index([
                'workspace_id',
                'inventory_item_id',
            ]);

            $table->index([
                'stock_lot_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Price History
        |--------------------------------------------------------------------------
        */
        Schema::create('price_history', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('supplier_id')
                ->constrained('suppliers')
                ->cascadeOnDelete();

            $table->foreignUlid('supplier_item_id')
                ->nullable()
                ->constrained('supplier_items')
                ->nullOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Price
            |--------------------------------------------------------------------------
            */
            $table->decimal('price', 14, 4);

            $table->foreignUlid('unit_id')
                ->nullable()
                ->constrained('units')
                ->nullOnDelete();

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Effective period
            |--------------------------------------------------------------------------
            */
            $table->timestamp('effective_at');

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | manual
            | quote
            | purchase_order
            | receipt
            | supplier_import
            */
            $table->string('source', 32)->default('manual');

            $table->string('reference_type', 80)->nullable();

            $table->ulid('reference_id')->nullable();

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
                'effective_at',
            ]);

            $table->index([
                'supplier_id',
                'inventory_item_id',
                'effective_at',
            ]);

            $table->index([
                'reference_type',
                'reference_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Purchase Order Status History
        |--------------------------------------------------------------------------
        */
        Schema::create('purchase_order_status_history', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();

            $table->string('from_status', 32)->nullable();

            $table->string('to_status', 32);

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            */
            $table->string('source', 32)->default('user');

            $table->foreignUlid('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reason')->nullable();

            $table->ulid('correlation_id')->nullable();

            $table->timestamps();

            $table->index([
                'purchase_order_id',
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
        | Supplier Returns
        |--------------------------------------------------------------------------
        */
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->foreignUlid('purchase_order_id')
                ->nullable()
                ->constrained('purchase_orders')
                ->nullOnDelete();

            $table->foreignUlid('receipt_id')
                ->nullable()
                ->constrained('receipts')
                ->nullOnDelete();

            $table->string('number', 64)->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'accepted',
                'completed',
                'cancelled',
            ])->default('draft');

            $table->string('reason', 100)->nullable();

            $table->timestamp('returned_at')->nullable();

            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'supplier_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Supplier Return Items
        |--------------------------------------------------------------------------
        */
        Schema::create('supplier_return_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('supplier_return_id')
                ->constrained('supplier_returns')
                ->cascadeOnDelete();

            $table->foreignUlid('inventory_item_id')
                ->constrained('inventory_items')
                ->restrictOnDelete();

            $table->foreignUlid('receipt_item_id')
                ->nullable()
                ->constrained('receipt_items')
                ->nullOnDelete();

            $table->foreignUlid('stock_lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            $table->decimal('quantity', 18, 4);

            $table->foreignUlid('unit_id')
                ->constrained('units')
                ->restrictOnDelete();

            $table->string('reason', 100)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index([
                'supplier_return_id',
                'inventory_item_id',
            ]);
        });

        /*
|--------------------------------------------------------------------------
| Complete Inventory Foreign Keys
|--------------------------------------------------------------------------
|
| These columns were created in the Inventory migration without constraints
| because suppliers and receipts did not exist yet.
|
*/
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();

            $table->foreign('receipt_id')
                ->references('id')
                ->on('receipts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        /*
    |--------------------------------------------------------------------------
    | Remove Inventory Foreign Keys
    |--------------------------------------------------------------------------
    |
    | stock_lots belongs to the previous Inventory migration, but these
    | constraints point to tables created in this migration.
    |
    | They must be removed before dropping receipts/suppliers.
    |--------------------------------------------------------------------------
    */
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['receipt_id']);
        });

        /*
    |--------------------------------------------------------------------------
    | Purchasing Tables
    |--------------------------------------------------------------------------
    */
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');

        Schema::dropIfExists('purchase_order_status_history');

        Schema::dropIfExists('price_history');

        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');

        Schema::dropIfExists('supplier_items');
        Schema::dropIfExists('suppliers');
    }
};
