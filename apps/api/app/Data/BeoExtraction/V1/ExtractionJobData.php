<?php

namespace App\Data\BeoExtraction\V1;

final class ExtractionJobData
{
    public function __construct(
        public string $schemaVersion,
        public string $extractionRunId,
        public string $documentId,
        public ?string $importBatchId,
        public string $correlationId,
        public array $document,
        public array $options,
        public string $requestedAt,
        public array $payload,
    ) {
    }

    public static function fromValidated(array $payload): self
    {
        return new self(
            $payload['schema_version'],
            $payload['extraction_run_id'],
            $payload['document_id'],
            $payload['import_batch_id'],
            $payload['correlation_id'],
            $payload['document'],
            $payload['options'],
            $payload['requested_at'],
            $payload,
        );
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
