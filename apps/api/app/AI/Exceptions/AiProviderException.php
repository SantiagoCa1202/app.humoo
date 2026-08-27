<?php

namespace App\AI\Exceptions;

use App\AI\Support\Latency;
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
                $metadata[$key] = $key === 'latency_ms'
                    ? Latency::normalize($value)
                    : (is_numeric($value) ? max(0, (int) $value) : null);
                continue;
            }

            $metadata[$key] = is_scalar($value)
                ? substr(trim((string) $value), 0, 500)
                : null;
        }

        return array_filter($metadata, static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
