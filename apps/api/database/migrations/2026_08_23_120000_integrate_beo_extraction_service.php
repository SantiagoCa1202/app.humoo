<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table): void {
            $table->foreignUlid('beo_import_batch_id')
                ->nullable()
                ->after('document_id')
                ->constrained('beo_import_batches')
                ->nullOnDelete();
            $table->string('result_status', 32)->nullable()->after('status');
            $table->string('extractor_version', 64)->nullable()->after('schema_version');
            $table->string('worker_id', 120)->nullable()->after('attempt');
            $table->string('result_checksum', 64)->nullable()->after('worker_id');
            $table->timestamp('queued_at')->nullable()->after('started_at');
            $table->timestamp('claimed_at')->nullable()->after('queued_at');
            $table->timestamp('lease_expires_at')->nullable()->after('claimed_at');
            $table->timestamp('last_heartbeat_at')->nullable()->after('lease_expires_at');

            $table->index(['status', 'lease_expires_at']);
            $table->index(['worker_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table): void {
            $table->dropIndex(['status', 'lease_expires_at']);
            $table->dropIndex(['worker_id', 'status']);
            $table->dropForeign(['beo_import_batch_id']);
            $table->dropColumn([
                'beo_import_batch_id',
                'result_status',
                'extractor_version',
                'worker_id',
                'result_checksum',
                'queued_at',
                'claimed_at',
                'lease_expires_at',
                'last_heartbeat_at',
            ]);
        });
    }
};
