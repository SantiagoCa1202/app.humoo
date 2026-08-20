<?php

namespace App\Application\Actions\Documents;

use App\Models\Document;
use App\Models\DocumentProcessingJob;
use App\Models\Event;
use App\Models\ExtractionRun;
use App\Support\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

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
                ExtractionRun::query()->create([
                    'workspace_id' => $workspaceId,
                    'document_id' => $document->id,
                    'status' => 'pending',
                    'provider' => null,
                    'model_key' => null,
                    'prompt_version' => null,
                    'schema_version' => null,
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
