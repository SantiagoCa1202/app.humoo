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
        | Documents
        |--------------------------------------------------------------------------
        |
        | Generic workspace files:
        | - BEO
        | - menu
        | - recipe
        | - contract
        | - invoice
        | - image
        | - attachment
        | - export
        |
        | Files themselves live in S3/object storage.
        |--------------------------------------------------------------------------
        */
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */
            $table->string('name', 255);

            // Original filename sent by the user.
            $table->string('original_filename', 255)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Business type
            |--------------------------------------------------------------------------
            |
            | Suggested:
            | beo
            | menu
            | recipe
            | contract
            | invoice
            | photo
            | export
            | attachment
            | other
            |
            | Keep as string for extensibility.
            */
            $table->string('type', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Storage
            |--------------------------------------------------------------------------
            */
            $table->string('disk', 64)->default('s3');

            // Internal object-storage path/key.
            // Never expose directly as a public permanent URL.
            $table->string('path', 2048);

            /*
            |--------------------------------------------------------------------------
            | File information
            |--------------------------------------------------------------------------
            */
            $table->string('mime_type', 150)->nullable();

            $table->string('extension', 32)->nullable();

            $table->unsignedBigInteger('size')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Integrity
            |--------------------------------------------------------------------------
            |
            | Prefer SHA-256.
            */
            $table->string('checksum', 128)->nullable();

            $table->string('checksum_algorithm', 32)
                ->default('sha256');

            /*
            |--------------------------------------------------------------------------
            | Security
            |--------------------------------------------------------------------------
            |
            | pending
            | scanning
            | clean
            | infected
            | failed
            */
            $table->string('scan_status', 32)
                ->default('pending');

            $table->timestamp('scanned_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Processing
            |--------------------------------------------------------------------------
            |
            | uploaded
            | processing
            | ready
            | failed
            */
            $table->string('processing_status', 32)
                ->default('uploaded');

            $table->text('processing_error')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Visibility
            |--------------------------------------------------------------------------
            |
            | private should be the normal default.
            | Access should happen through signed/temporary URLs.
            */
            $table->string('visibility', 32)
                ->default('private');

            /*
            |--------------------------------------------------------------------------
            | Flexible metadata
            |--------------------------------------------------------------------------
            |
            | Examples:
            | page_count
            | image dimensions
            | source
            | upload client
            | OCR language
            |
            */
            $table->json('metadata')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('uploaded_by')
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
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->index([
                'workspace_id',
                'type',
            ]);

            $table->index([
                'workspace_id',
                'processing_status',
            ]);

            $table->index([
                'workspace_id',
                'scan_status',
            ]);

            $table->index([
                'workspace_id',
                'created_at',
            ]);

            $table->index('checksum');
        });

        /*
        |--------------------------------------------------------------------------
        | Document Links
        |--------------------------------------------------------------------------
        |
        | Generic many-to-many-style relationship.
        |
        | Allows one document to be linked to:
        | event
        | client
        | recipe
        | menu
        | purchase_order
        | conversation
        | etc.
        |--------------------------------------------------------------------------
        */
        Schema::create('document_links', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Polymorphic entity reference
            |--------------------------------------------------------------------------
            |
            | Use canonical application keys, not PHP class names.
            |
            | event
            | recipe
            | menu
            | client
            | conversation
            */
            $table->string('entity_type', 80);

            $table->ulid('entity_id');

            /*
            |--------------------------------------------------------------------------
            | Relationship
            |--------------------------------------------------------------------------
            |
            | attachment
            | source
            | contract
            | beo
            | menu
            | reference
            |
            */
            $table->string('relationship_type', 64)
                ->default('attachment');

            /*
            |--------------------------------------------------------------------------
            | Display / behavior
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_primary')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('linked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'document_id',
                'entity_type',
                'entity_id',
                'relationship_type',
            ]);

            $table->index([
                'workspace_id',
                'entity_type',
                'entity_id',
            ]);

            $table->index([
                'document_id',
                'relationship_type',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | BEOs
        |--------------------------------------------------------------------------
        |
        | Stable logical BEO entity.
        | Actual contents live in beo_versions.
        |--------------------------------------------------------------------------
        */
        Schema::create('beos', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('event_id')
                ->constrained('events')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Current version pointer
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('current_version')->default(0);

            /*
            |--------------------------------------------------------------------------
            | State
            |--------------------------------------------------------------------------
            |
            | draft
            | active
            | superseded
            | archived
            */
            $table->string('status', 32)
                ->default('active');

            /*
            |--------------------------------------------------------------------------
            | Approval
            |--------------------------------------------------------------------------
            |
            | Useful because extracted/imported data should not necessarily
            | become operational truth immediately.
            */
            $table->timestamp('approved_at')->nullable();

            $table->foreignUlid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

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

            $table->unique([
                'workspace_id',
                'event_id',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | BEO Versions
        |--------------------------------------------------------------------------
        |
        | Immutable-ish historical versions.
        |
        | A new BEO upload creates a new version rather than silently
        | replacing the version already used by production.
        |--------------------------------------------------------------------------
        */
        Schema::create('beo_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('beo_id')
                ->constrained('beos')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Source document
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('document_id')
                ->nullable()
                ->constrained('documents')
                ->nullOnDelete();

            $table->unsignedInteger('version');

            /*
            |--------------------------------------------------------------------------
            | Version state
            |--------------------------------------------------------------------------
            |
            | processing
            | review_required
            | approved
            | superseded
            | rejected
            */
            $table->string('status', 32)
                ->default('processing');

            /*
            |--------------------------------------------------------------------------
            | Complete normalized snapshot
            |--------------------------------------------------------------------------
            |
            | This preserves what this exact version meant at the time.
            |
            | JSON is appropriate here because this is a historical snapshot,
            | not the only queryable representation of operational data.
            */
            $table->json('snapshot_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Version notes
            |--------------------------------------------------------------------------
            */
            $table->text('change_summary')->nullable();

            $table->text('review_notes')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source
            |--------------------------------------------------------------------------
            |
            | upload
            | manual
            | ai
            | import
            */
            $table->string('source', 32)
                ->default('upload');

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
            | Audit
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique([
                'beo_id',
                'version',
            ]);

            $table->index([
                'workspace_id',
                'beo_id',
            ]);

            $table->index([
                'beo_id',
                'status',
            ]);

            $table->index('document_id');
        });

        /*
        |--------------------------------------------------------------------------
        | BEO Version Changes
        |--------------------------------------------------------------------------
        |
        | Stores comparison results between BEO versions.
        |
        | Useful for:
        | BEOFieldComparison
        | BEOChangeAlert
        |--------------------------------------------------------------------------
        */
        Schema::create('beo_version_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('beo_id')
                ->constrained('beos')
                ->cascadeOnDelete();

            $table->foreignUlid('from_version_id')
                ->nullable()
                ->constrained('beo_versions')
                ->nullOnDelete();

            $table->foreignUlid('to_version_id')
                ->constrained('beo_versions')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Changed field
            |--------------------------------------------------------------------------
            |
            | Examples:
            | guest_count
            | event.starts_at
            | venue.address
            | menu.items
            */
            $table->string('field_key', 255);

            /*
            |--------------------------------------------------------------------------
            | Values
            |--------------------------------------------------------------------------
            */
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Impact
            |--------------------------------------------------------------------------
            |
            | info
            | warning
            | critical
            */
            $table->string('severity', 32)
                ->default('info');

            /*
            |--------------------------------------------------------------------------
            | Operational impact
            |--------------------------------------------------------------------------
            */
            $table->boolean('affects_production')->default(false);

            $table->boolean('reviewed')->default(false);

            $table->foreignUlid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index([
                'beo_id',
                'to_version_id',
            ]);

            $table->index([
                'workspace_id',
                'severity',
            ]);

            $table->index([
                'to_version_id',
                'affects_production',
            ]);
        });

        /*
        |--------------------------------------------------------------------------
        | Extraction Runs
        |--------------------------------------------------------------------------
        |
        | One document may be processed multiple times:
        | - different model
        | - retry
        | - improved prompt
        | - new extraction schema
        |--------------------------------------------------------------------------
        */
        Schema::create('extraction_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional BEO version target
            |--------------------------------------------------------------------------
            |
            | Makes it easy to know which BEO version this extraction produced.
            */
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
            | Extraction engine
            |--------------------------------------------------------------------------
            */
            $table->string('provider', 64)->nullable();

            $table->string('model_key', 100)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Prompt/schema versioning
            |--------------------------------------------------------------------------
            */
            $table->string('prompt_version', 64)->nullable();

            $table->string('schema_version', 64)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Processing metrics
            |--------------------------------------------------------------------------
            */
            $table->unsignedInteger('attempt')->default(1);

            $table->unsignedInteger('latency_ms')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Usage / cost
            |--------------------------------------------------------------------------
            |
            | Flexible because different AI providers report different usage.
            */
            $table->json('usage_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Extraction metadata
            |--------------------------------------------------------------------------
            */
            $table->json('metadata_json')->nullable();

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
            | Correlation
            |--------------------------------------------------------------------------
            |
            | API -> job -> AI -> audit trail.
            */
            $table->ulid('correlation_id')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Requested by
            |--------------------------------------------------------------------------
            */
            $table->foreignUlid('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'workspace_id',
                'document_id',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);

            $table->index([
                'document_id',
                'created_at',
            ]);

            $table->index('beo_version_id');
        });

        /*
        |--------------------------------------------------------------------------
        | Extracted Fields
        |--------------------------------------------------------------------------
        |
        | Individual extracted values with confidence/review information.
        |--------------------------------------------------------------------------
        */
        Schema::create('extracted_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('extraction_run_id')
                ->constrained('extraction_runs')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Field identification
            |--------------------------------------------------------------------------
            |
            | Examples:
            | event.name
            | event.starts_at
            | guest_count
            | client.name
            | venue.address
            */
            $table->string('field_key', 255);

            /*
            |--------------------------------------------------------------------------
            | Field type
            |--------------------------------------------------------------------------
            |
            | string
            | integer
            | decimal
            | boolean
            | date
            | datetime
            | object
            | array
            */
            $table->string('value_type', 32)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Extracted value
            |--------------------------------------------------------------------------
            |
            | value_text is useful for simple/searchable values.
            | value_json supports structured values.
            */
            $table->longText('value_text')->nullable();

            $table->json('value_json')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Original raw value
            |--------------------------------------------------------------------------
            |
            | Useful if normalization converts:
            | "250 ppl" -> 250
            */
            $table->text('raw_value')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Confidence
            |--------------------------------------------------------------------------
            |
            | 0.0000 - 1.0000
            */
            $table->decimal('confidence', 5, 4)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Source location
            |--------------------------------------------------------------------------
            |
            | Allows UI to show where this value came from in a PDF.
            */
            $table->unsignedInteger('page_number')->nullable();

            $table->json('source_location')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Review
            |--------------------------------------------------------------------------
            */
            $table->boolean('reviewed')->default(false);

            $table->enum('review_status', [
                'pending',
                'accepted',
                'corrected',
                'rejected',
            ])->default('pending');

            /*
            |--------------------------------------------------------------------------
            | Corrected value
            |--------------------------------------------------------------------------
            */
            $table->longText('corrected_value_text')->nullable();

            $table->json('corrected_value_json')->nullable();

            $table->foreignUlid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Review notes
            |--------------------------------------------------------------------------
            */
            $table->text('review_notes')->nullable();

            $table->timestamps();

            $table->index([
                'extraction_run_id',
                'field_key',
            ]);

            $table->index([
                'workspace_id',
                'review_status',
            ]);

            $table->index([
                'extraction_run_id',
                'reviewed',
            ]);

            $table->index('confidence');
        });

        /*
        |--------------------------------------------------------------------------
        | Document Processing Jobs
        |--------------------------------------------------------------------------
        |
        | Optional but highly useful for tracking asynchronous operations.
        |
        | Examples:
        | virus_scan
        | text_extract
        | preview
        | thumbnail
        | beo_extract
        |--------------------------------------------------------------------------
        */
        Schema::create('document_processing_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('workspace_id')
                ->constrained('workspaces')
                ->cascadeOnDelete();

            $table->foreignUlid('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();

            $table->string('job_type', 64);

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
            ])->default('pending');

            $table->unsignedSmallInteger('attempts')->default(0);

            $table->json('result_json')->nullable();

            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index([
                'document_id',
                'job_type',
            ]);

            $table->index([
                'workspace_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_processing_jobs');
        Schema::dropIfExists('extracted_fields');
        Schema::dropIfExists('extraction_runs');

        Schema::dropIfExists('beo_version_changes');
        Schema::dropIfExists('beo_versions');
        Schema::dropIfExists('beos');

        Schema::dropIfExists('document_links');
        Schema::dropIfExists('documents');
    }
};
