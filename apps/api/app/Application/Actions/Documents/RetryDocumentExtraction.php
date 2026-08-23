<?php

namespace App\Application\Actions\Documents;

use App\Models\Document;
use App\Models\DocumentProcessingJob;
use App\Models\ExtractionRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RetryDocumentExtraction
{
    public function execute(Document $document, string $userId): ExtractionRun
    {
        return DB::transaction(function () use ($document, $userId): ExtractionRun {
            $latest = $document->extractionRuns()->latest('created_at')->lockForUpdate()->first();
            if ($latest && !in_array($latest->status, ['failed', 'cancelled'], true)) {
                abort(409, 'Only failed or cancelled extractions can be retried.');
            }

            $run = ExtractionRun::query()->create([
                'workspace_id' => $document->workspace_id,
                'document_id' => $document->id,
                'beo_import_batch_id' => $latest?->beo_import_batch_id
                    ?: $document->beoImportBatches()->latest('created_at')->value('id'),
                'status' => 'pending',
                'provider' => config('extraction.provider'),
                'prompt_version' => config('extraction.prompt_version'),
                'schema_version' => config('extraction.schema_version'),
                'attempt' => 1,
                'queued_at' => now(),
                'correlation_id' => (string) Str::ulid(),
                'requested_by' => $userId,
            ]);

            DocumentProcessingJob::query()->create([
                'workspace_id' => $document->workspace_id,
                'document_id' => $document->id,
                'job_type' => 'beo_extract',
                'status' => 'pending',
                'attempts' => 0,
            ]);

            $document->forceFill([
                'processing_status' => 'uploaded',
                'processing_error' => null,
                'updated_by' => $userId,
            ])->save();

            return $run;
        });
    }
}
