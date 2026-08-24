<?php

namespace App\AI\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    private const SAFE_METADATA_KEYS = [
        'http_status',
        'provider_error_type',
        'provider_error_code',
        'provider_message',
        'request_id',
        'latency_ms',
        'provider',
        'model',
    ];

    public function __construct(
        string $message,
        array $metadata = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->metadata = $this->sanitizeMetadata($metadata);
    }

    private array $metadata = [];

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function internalCode(): string
    {
        return 'AI_PROVIDER_UNAVAILABLE';
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $metadata = array_intersect_key(
            $metadata,
            array_fill_keys(self::SAFE_METADATA_KEYS, true)
        );

        foreach ($metadata as $key => $value) {
            if ($key === 'http_status' || $key === 'latency_ms') {
                $metadata[$key] = is_numeric($value) ? (int) $value : null;
                continue;
            }

            $metadata[$key] = is_scalar($value)
                ? substr(trim((string) $value), 0, 500)
                : null;
        }

        return array_filter($metadata, static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
