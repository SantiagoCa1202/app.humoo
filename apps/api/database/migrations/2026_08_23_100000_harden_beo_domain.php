<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('source_system', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('beo_import_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignUlid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('original_filename', 255);
            $table->string('source_system', 80)->nullable();
            $table->string('status', 32)->default('received');
            $table->json('source_metadata')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->json('operational_visibility_defaults')->nullable();
        });

        Schema::table('workspace_memberships', function (Blueprint $table): void {
            $table->json('operational_visibility_overrides')->nullable();
        });

        Schema::table('venues', function (Blueprint $table): void {
            $table->foreignUlid('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->index(['workspace_id', 'property_id']);
        });

        Schema::table('beos', function (Blueprint $table): void {
            $table->foreignUlid('import_batch_id')->nullable()->constrained('beo_import_batches')->nullOnDelete();
            $table->foreignUlid('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('event_order_number', 100)->nullable();
            $table->string('quote_number', 100)->nullable();
            $table->string('folio_number', 100)->nullable();
            $table->string('source_organization', 180)->nullable();
            $table->string('source_system', 80)->nullable();
            $table->dropUnique(['workspace_id', 'event_id']);
            $table->foreignUlid('event_id')->nullable()->change();
            $table->index(['workspace_id', 'event_order_number', 'source_system']);
        });

        Schema::table('beo_versions', function (Blueprint $table): void {
            $table->unsignedInteger('revision_number')->nullable();
            $table->string('revision_label', 120)->nullable();
            $table->string('revision_type', 40)->nullable();
            $table->timestamp('date_printed')->nullable();
            $table->json('source_pages')->nullable();
            $table->json('source_metadata')->nullable();
            $table->string('review_status', 40)->nullable();
            $table->index(['beo_id', 'revision_number']);
        });

        Schema::table('document_links', function (Blueprint $table): void {
            $table->unsignedInteger('source_page')->nullable();
            $table->string('attachment_type', 64)->nullable();
            $table->json('source_reference')->nullable();
            $table->index(['entity_type', 'entity_id', 'attachment_type']);
        });

        Schema::create('event_functions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('beo_version_id')->constrained('beo_versions')->cascadeOnDelete();
            $table->string('source_function_key', 120)->nullable();
            $table->string('source_function_name', 180);
            $table->string('function_type', 80)->nullable();
            $table->string('operational_category', 80)->nullable();
            $table->string('post_as', 120)->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('source_start_time', 80)->nullable();
            $table->string('source_end_time', 80)->nullable();
            $table->text('source_location_text')->nullable();
            $table->unsignedInteger('expected_count')->nullable();
            $table->unsignedInteger('guaranteed_count')->nullable();
            $table->unsignedInteger('set_count')->nullable();
            $table->unsignedInteger('production_count')->nullable();
            $table->string('menu_status', 32)->default('none');
            $table->json('operational_signals')->nullable();
            $table->json('source_metadata')->nullable();
            $table->json('review_metadata')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'beo_version_id']);
            $table->index(['workspace_id', 'operational_category']);
        });

        Schema::create('event_function_venues', function (Blueprint $table): void {
            $table->foreignUlid('event_function_id')->constrained('event_functions')->cascadeOnDelete();
            $table->foreignUlid('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['event_function_id', 'venue_id']);
            $table->index(['workspace_id', 'venue_id']);
        });

        Schema::create('event_function_dietary_requirements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('event_function_id')->constrained('event_functions')->cascadeOnDelete();
            $table->string('guest_name', 180)->nullable();
            $table->unsignedInteger('count')->nullable();
            $table->text('raw_restriction');
            $table->string('normalized_restriction', 180)->nullable();
            $table->string('category', 40)->default('OTHER');
            $table->text('source_text')->nullable();
            $table->json('source_reference')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'event_function_id']);
        });

        Schema::create('event_function_instructions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('event_function_id')->constrained('event_functions')->cascadeOnDelete();
            $table->string('category', 40)->default('general');
            $table->text('raw_text');
            $table->text('normalized_text')->nullable();
            $table->json('source_reference')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'event_function_id', 'category']);
        });

        Schema::create('event_order_references', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUlid('source_beo_id')->constrained('beos')->cascadeOnDelete();
            $table->foreignUlid('source_beo_version_id')->nullable()->constrained('beo_versions')->nullOnDelete();
            $table->foreignUlid('source_event_function_id')->nullable()->constrained('event_functions')->nullOnDelete();
            $table->string('target_event_order_number', 100);
            $table->foreignUlid('target_beo_id')->nullable()->constrained('beos')->nullOnDelete();
            $table->string('reference_type', 80)->nullable();
            $table->text('raw_text');
            $table->json('source_reference')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'target_event_order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_order_references');
        Schema::dropIfExists('event_function_instructions');
        Schema::dropIfExists('event_function_dietary_requirements');
        Schema::dropIfExists('event_function_venues');
        Schema::dropIfExists('event_functions');

        Schema::table('document_links', function (Blueprint $table): void {
            $table->dropIndex(['entity_type', 'entity_id', 'attachment_type']);
            $table->dropColumn(['source_page', 'attachment_type', 'source_reference']);
        });

        Schema::table('beo_versions', function (Blueprint $table): void {
            $table->dropIndex(['beo_id', 'revision_number']);
            $table->dropColumn([
                'revision_number', 'revision_label', 'revision_type', 'date_printed',
                'source_pages', 'source_metadata', 'review_status',
            ]);
        });

        Schema::table('beos', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'event_order_number', 'source_system']);
            $table->dropForeign(['import_batch_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn([
                'import_batch_id', 'property_id', 'event_order_number', 'quote_number',
                'folio_number', 'source_organization', 'source_system',
            ]);
        });

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'property_id']);
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });

        Schema::table('workspace_memberships', function (Blueprint $table): void {
            $table->dropColumn('operational_visibility_overrides');
        });

        Schema::table('workspaces', function (Blueprint $table): void {
            $table->dropColumn('operational_visibility_defaults');
        });

        Schema::dropIfExists('beo_import_batches');
        Schema::dropIfExists('properties');
    }
};
