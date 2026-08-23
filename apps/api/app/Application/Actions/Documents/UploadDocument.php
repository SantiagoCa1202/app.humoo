<?php

namespace App\Application\Actions\Documents;

use App\Models\Document;
use App\Models\DocumentProcessingJob;
use App\Models\Event;
use App\Models\BeoImportBatch;
use App\Models\ExtractionRun;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UploadDocument
{
    public function __construct(
        private DocumentStorage $documentStorage,
        private LinkDocumentToEvent $linkDocumentToEvent
    ) {
    }

    public function execute(
        string $workspaceId,
        string $userId,
        UploadedFile $file,
        array $data,
        ?Event $event = null
    ): Document {
        return DB::transaction(function () use ($data, $event, $file, $userId, $workspaceId): Document {
            $stored = $this->documentStorage->storeUploadedFile(
                $file,
                $workspaceId
            );

            $duplicate = Document::query()
                ->where('workspace_id', $workspaceId)
                ->where('type', $data['type'] ?? 'beo')
                ->where('checksum', $stored['checksum'])
                ->latest('created_at')
                ->first();

            if ($duplicate) {
                $this->documentStorage->deleteStored($stored['disk'], $stored['path']);

                if ($event) {
                    $duplicate = $this->linkDocumentToEvent->execute($duplicate, $event, $userId);
                }

                return $duplicate;
            }

            $document = Document::query()->create([
                ...$stored,
                'workspace_id' => $workspaceId,
                'type' => $data['type'] ?? 'beo',
                'metadata' => [
                    'source' => $data['source'] ?? 'upload',
                ],
                'processing_status' => 'uploaded',
                'scan_status' => 'pending',
                'visibility' => 'private',
                'uploaded_by' => $userId,
                'updated_by' => $userId,
            ]);

            if (($data['type'] ?? 'beo') === 'beo') {
                $batch = BeoImportBatch::query()->create([
                    'workspace_id' => $workspaceId,
                    'document_id' => $document->id,
                    'original_filename' => $document->original_filename ?: $document->name,
                    'source_system' => 'humoo-upload',
                    'status' => 'received',
                    'source_metadata' => [
                        'upload_source' => $data['source'] ?? 'upload',
                        'checksum' => $document->checksum,
                    ],
                    'created_by' => $userId,
                ]);

                ExtractionRun::query()->create([
                    'workspace_id' => $workspaceId,
                    'document_id' => $document->id,
                    'beo_import_batch_id' => $batch->id,
                    'status' => 'pending',
                    'result_status' => null,
                    'provider' => config('extraction.provider'),
                    'model_key' => null,
                    'prompt_version' => config('extraction.prompt_version'),
                    'schema_version' => config('extraction.schema_version'),
                    'queued_at' => now(),
                    'correlation_id' => (string) Str::ulid(),
                    'attempt' => 1,
                    'requested_by' => $userId,
                ]);

                DocumentProcessingJob::query()->create([
                    'workspace_id' => $workspaceId,
                    'document_id' => $document->id,
                    'job_type' => 'beo_extract',
                    'status' => 'pending',
                    'attempts' => 0,
                ]);
            }

            if ($event) {
                $document = $this->linkDocumentToEvent->execute(
                    $document,
                    $event,
                    $userId
                );
            }

            return $document;
        });
    }
}
