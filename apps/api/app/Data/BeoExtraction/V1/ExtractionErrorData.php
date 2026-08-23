<?php

namespace App\Data\BeoExtraction\V1;

final class ExtractionErrorData
{
    public function __construct(
        public string $schemaVersion,
        public string $correlationId,
        public array $error,
        public array $payload,
    ) {
    }

    public static function fromValidated(array $payload): self
    {
        return new self(
            $payload['schema_version'],
            $payload['correlation_id'],
            $payload['error'],
            $payload,
        );
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
