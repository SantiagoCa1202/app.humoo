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
        | Plans
        |--------------------------------------------------------------------------
        */
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // free, basic, pro, business
            $table->string('key', 64)->unique();

            $table->string('name', 120);
            $table->text('description')->nullable();

            // Allows ordering plans in UI.
            $table->unsignedInteger('sort_order')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            |
            | Keep these as simple/default pricing fields.
            | Provider-specific prices live in plan_prices.
            |
            */
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_yearly', 12, 2)->nullable();

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Trial
            |--------------------------------------------------------------------------
            */
            $table->unsignedSmallInteger('trial_days')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Visibility / lifecycle
            |--------------------------------------------------------------------------
            */
            $table->boolean('active')->default(true)->index();
            $table->boolean('public')->default(true);

            // Useful when a plan should no longer accept new subscriptions
            // but existing subscribers must remain on it.
            $table->timestamp('retired_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['active', 'sort_order']);
        });

        /*
        |--------------------------------------------------------------------------
        | Features
        |--------------------------------------------------------------------------
        |
        | Code should check features/limits instead of:
        | if ($plan === 'pro')
        |
        */
        Schema::create('features', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // monthly_ai_actions
            // team_members
            // active_events
            // inventory
            // custom_roles
            // audit_history_days
            $table->string('key', 100)->unique();

            $table->string('name', 150);

            $table->text('description')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Feature type
            |--------------------------------------------------------------------------
            |
            | boolean   => inventory enabled/disabled
            | limit     => 100 AI calls/month
            | quantity  => 5 team members
            | duration  => 90 audit-history days
            |
            */
            $table->enum('type', [
                'boolean',
                'limit',
                'quantity',
                'duration',
            ])->default('boolean');

            // ai, events, team, inventory, billing, etc.
            $table->string('module', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Measurement
            |--------------------------------------------------------------------------
            |
            | actions, users, events, days, gb, documents, etc.
            */
            $table->string('unit', 32)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Reset period
            |--------------------------------------------------------------------------
            |
            | none
            | daily
            | weekly
            | monthly
            | yearly
            */
            $table->string('reset_period', 32)->default('none');

            $table->boolean('active')->default(true)->index();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['module', 'active']);
        });

        /*
        |--------------------------------------------------------------------------
        | Plan Features / Entitlements
        |--------------------------------------------------------------------------
        */
        Schema::create('plan_features', function (Blueprint $table) {
            $table->foreignUlid('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete();

            $table->foreignUlid('feature_id')
                ->constrained('features')
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(true);

            /*
            |--------------------------------------------------------------------------
            | limit_value
            |--------------------------------------------------------------------------
            |
            | null = unlimited / not applicable depending on feature.
            |
            | Examples:
            | team_members       => 5
            | monthly_ai_actions => 1000
            | audit_days         => 90
            |
            */
            $table->decimal('limit_value', 18, 4)->nullable();

            $table->json('config')->nullable();

            $table->timestamps();

            $table->primary([
                'plan_id',
                'feature_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Provider prices
        |--------------------------------------------------------------------------
        |
        | Allows Stripe/other providers to have separate monthly/yearly IDs
        | without storing those IDs directly in plans.
        |--------------------------------------------------------------------------
        */
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete();

            // stripe, paddle, etc.
            $table->string('provider', 32);

            $table->string('provider_product_id')->nullable();
            $table->string('provider_price_id');

            $table->char('currency', 3)->default('USD');

            $table->decimal('amount', 12, 2);

            $table->enum('billing_interval', [
                'month',
                'year',
            ]);

            $table->unsignedSmallInteger('interval_count')->default(1);

            $table->boolean('active')->default(true);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique([
                'provider',
                'provider_price_id',
            ]);

            $table->index([
                'plan_id',
                'provider',
                'billing_interval',
                'active',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Billing Customers
        |--------------------------------------------------------------------------
        |
        | A workspace can theoretically have customer records in more than
        | one provider.
        |--------------------------------------------------------------------------
        */
        Schema::create('billing_customers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            // stripe, paddle, etc.
            $table->string('provider', 32);

            $table->string('provider_customer_id');

            /*
            |--------------------------------------------------------------------------
            | Billing identity
            |--------------------------------------------------------------------------
            */
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();

            $table->string('tax_id')->nullable();

            $table->char('country_code', 2)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique([
                'provider',
                'provider_customer_id',
            ]);

            // One customer per provider per workspace.
            $table->unique([
                'workspace_id',
                'provider',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Subscriptions
        |--------------------------------------------------------------------------
        */
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Keep plan reference
            |--------------------------------------------------------------------------
            |
            | Restrict deleting plans that are referenced historically.
            */
            $table->foreignUlid('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Provider
            |--------------------------------------------------------------------------
            */
            $table->string('provider', 32)->nullable();

            $table->string('provider_subscription_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'incomplete',
                'incomplete_expired',
                'trialing',
                'active',
                'past_due',
                'unpaid',
                'paused',
                'cancelled',
                'expired',
            ])->default('active');

            /*
            |--------------------------------------------------------------------------
            | Billing cycle
            |--------------------------------------------------------------------------
            */
            $table->enum('billing_interval', [
                'month',
                'year',
            ])->nullable();

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Period
            |--------------------------------------------------------------------------
            */
            $table->timestamp('starts_at')->nullable();

            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Trial
            |--------------------------------------------------------------------------
            */
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cancellation
            |--------------------------------------------------------------------------
            */
            $table->boolean('cancel_at_period_end')->default(false);

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('cancel_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | End
            |--------------------------------------------------------------------------
            */
            $table->timestamp('ends_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Grace period
            |--------------------------------------------------------------------------
            */
            $table->timestamp('grace_ends_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Provider sync
            |--------------------------------------------------------------------------
            */
            $table->timestamp('provider_synced_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'current_period_end',
            ]);

            $table->index('provider_subscription_id');

            /*
            |--------------------------------------------------------------------------
            | Provider subscription uniqueness
            |--------------------------------------------------------------------------
            |
            | MySQL allows multiple NULL values.
            */
            $table->unique([
                'provider',
                'provider_subscription_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Subscription Items
        |--------------------------------------------------------------------------
        |
        | Useful even if Humoo starts with one price per subscription.
        | Later allows add-ons / seats / metered features.
        |--------------------------------------------------------------------------
        */
        Schema::create('subscription_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();

            $table->foreignUlid('plan_price_id')
                ->nullable()
                ->constrained('plan_prices')
                ->nullOnDelete();

            $table->string('provider_item_id')->nullable();

            $table->string('provider_price_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional linked feature
            |--------------------------------------------------------------------------
            |
            | Useful for add-ons such as:
            | extra_ai_actions
            | extra_team_members
            */
            $table->foreignUlid('feature_id')
                ->nullable()
                ->constrained('features')
                ->nullOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            /*
            |--------------------------------------------------------------------------
            | Metering
            |--------------------------------------------------------------------------
            */
            $table->boolean('metered')->default(false);

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'subscription_id',
                'feature_id',
            ]);

            $table->unique([
                'subscription_id',
                'provider_item_id',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Usage Counters
        |--------------------------------------------------------------------------
        */
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Link to feature when possible
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('feature_id')
                ->nullable()
                ->constrained('features')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Keep immutable key too.
            |--------------------------------------------------------------------------
            |
            | This allows historical usage to remain readable even if
            | a feature record is renamed/deactivated.
            */
            $table->string('feature_key', 100);

            /*
            |--------------------------------------------------------------------------
            | Period
            |--------------------------------------------------------------------------
            */
            $table->timestamp('period_start');
            $table->timestamp('period_end');

            /*
            |--------------------------------------------------------------------------
            | Usage
            |--------------------------------------------------------------------------
            */
            $table->decimal('usage', 18, 4)->default(0);

            /*
            |--------------------------------------------------------------------------
            | Optional cached entitlement limit
            |--------------------------------------------------------------------------
            |
            | Snapshot of what the limit was during this period.
            */
            $table->decimal('limit_value', 18, 4)->nullable();

            $table->timestamp('last_incremented_at')->nullable();

            $table->timestamps();

            $table->unique([
                'workspace_id',
                'feature_key',
                'period_start',
                'period_end',
            ]);

            $table->index([
                'workspace_id',
                'feature_key',
                'period_end',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Invoices
        |--------------------------------------------------------------------------
        */
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->string('provider', 32);
            $table->string('provider_invoice_id');

            /*
            |--------------------------------------------------------------------------
            | Invoice number
            |--------------------------------------------------------------------------
            */
            $table->string('invoice_number')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Amounts
            |--------------------------------------------------------------------------
            */
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);

            $table->decimal('total', 12, 2)->default(0);

            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */
            $table->enum('status', [
                'draft',
                'open',
                'paid',
                'void',
                'uncollectible',
                'cancelled',
            ])->default('draft');

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Provider documents
            |--------------------------------------------------------------------------
            |
            | Save URLs/references instead of storing invoice PDF itself
            | in MySQL.
            |
            */
            $table->text('hosted_invoice_url')->nullable();
            $table->text('invoice_pdf_url')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique([
                'provider',
                'provider_invoice_id',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'workspace_id',
                'issued_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Invoice Items
        |--------------------------------------------------------------------------
        |
        | Important for displaying exactly what was charged.
        |--------------------------------------------------------------------------
        */
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->string('provider_item_id')->nullable();

            $table->string('description')->nullable();

            $table->decimal('quantity', 12, 4)->default(1);

            $table->decimal('unit_amount', 12, 2)->default(0);

            $table->decimal('amount', 12, 2)->default(0);

            $table->char('currency', 3)->default('USD');

            /*
            |--------------------------------------------------------------------------
            | Historical reference
            |--------------------------------------------------------------------------
            */
            $table->string('feature_key')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Payment Transactions
        |--------------------------------------------------------------------------
        |
        | An invoice and a payment are not the same thing.
        | A payment may fail, retry, be refunded, etc.
        |--------------------------------------------------------------------------
        */
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();

            $table->string('provider', 32);

            // payment_intent / transaction / charge ID
            $table->string('provider_transaction_id');

            $table->enum('type', [
                'payment',
                'refund',
                'adjustment',
            ])->default('payment');

            $table->enum('status', [
                'pending',
                'processing',
                'succeeded',
                'failed',
                'cancelled',
                'refunded',
                'partially_refunded',
            ]);

            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);

            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();

            $table->timestamp('processed_at')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique([
                'provider',
                'provider_transaction_id',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index('invoice_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Billing Events / Webhook Idempotency
        |--------------------------------------------------------------------------
        |
        | VERY important.
        |
        | Stripe and other providers can send the same webhook multiple times.
        | Never process the same provider event twice.
        |--------------------------------------------------------------------------
        */
        Schema::create('billing_events', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('provider', 32);

            $table->string('provider_event_id');

            // invoice.paid
            // customer.subscription.updated
            // payment_intent.payment_failed
            $table->string('event_type', 150);

            $table->enum('status', [
                'pending',
                'processing',
                'processed',
                'failed',
                'ignored',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Provider payload
            |--------------------------------------------------------------------------
            |
            | Consider retention/redaction depending on PII.
            |
            */
            $table->json('payload')->nullable();

            $table->unsignedSmallInteger('attempts')->default(0);

            $table->text('last_error')->nullable();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'provider',
                'provider_event_id',
            ]);

            $table->index([
                'provider',
                'event_type',
            ]);

            $table->index([
                'status',
                'received_at',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Subscription History
        |--------------------------------------------------------------------------
        |
        | Makes plan upgrades/downgrades auditable.
        |--------------------------------------------------------------------------
        */
        Schema::create('subscription_history', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('from_plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();

            $table->foreignUlid('to_plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Reasons
            |--------------------------------------------------------------------------
            |
            | created
            | upgrade
            | downgrade
            | cancelled
            | reactivated
            | expired
            | admin_change
            */
            $table->string('action', 64);

            $table->string('source', 32)->default('system');

            /*
            |--------------------------------------------------------------------------
            | Actor
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->timestamp('effective_at')->nullable();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_history');
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('billing_customers');
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plans');
    }
};
