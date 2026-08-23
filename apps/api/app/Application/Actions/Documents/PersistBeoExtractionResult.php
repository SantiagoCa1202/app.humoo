<?php

namespace App\Application\Actions\Documents;

use App\Application\Actions\Beo\CreateBeoImportBatch;
use App\Models\BeoVersion;
use App\Models\Document;
use App\Models\ExtractedField;
use App\Models\ExtractionRun;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersistBeoExtractionResult
{
    public function __construct(private CreateBeoImportBatch $createBeoImportBatch)
    {
    }

    /** @param array<string, mixed> $result */
    public function execute(ExtractionRun $run, array $result, string $workerId): ExtractionRun
    {
        return DB::transaction(function () use ($result, $run, $workerId): ExtractionRun {
            $run->loadMissing('document', 'importBatch');
            $document = $run->document;
            $batch = $run->importBatch;

            $payload = [
                'import_batch_id' => $batch?->id,
                'document_id' => $document->id,
                'original_filename' => $document->original_filename ?: $document->name,
                'source_system' => 'humoo-beo-extractor',
                'status' => 'review_required',
                'source_metadata' => $this->batchMetadata($result, $workerId),
                'event_orders' => array_map(
                    fn (array $order): array => $this->mapOrder($order, $document),
                    $result['event_orders'] ?? []
                ),
            ];

            $persistedBatch = $this->createBeoImportBatch->execute(
                $run->workspace_id,
                $run->requested_by,
                $payload
            );

            $fields = $this->buildReviewFields($run, $result);
            foreach ($fields as $field) {
                ExtractedField::query()->create([
                    'workspace_id' => $run->workspace_id,
                    'extraction_run_id' => $run->id,
                    ...$field,
                ]);
            }

            $version = $persistedBatch->eventOrders->first()?->latestVersion;
            $metadata = [
                'result_status' => $result['status'],
                'extractor' => Arr::only($result['extractor'] ?? [], [
                    'extractor_name', 'extractor_version', 'parser_version', 'layout_engine',
                ]),
                'document_analysis' => Arr::only($result['document_analysis'] ?? [], [
                    'page_count', 'text_mode', 'ocr_used', 'overall_confidence',
                ]),
                'issue_count' => count($result['issues'] ?? []),
                'warning_count' => count($result['warnings'] ?? []),
                'unresolved_count' => count($result['unresolved_items'] ?? []),
                'worker_id' => $workerId,
            ];
            $resultChecksum = hash(
                'sha256',
                json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $run->forceFill([
                'beo_import_batch_id' => $persistedBatch->id,
                'beo_version_id' => $version?->id,
                'status' => 'review_required',
                'result_status' => $result['status'],
                'result_checksum' => $resultChecksum,
                'extractor_version' => $result['extractor']['extractor_version'] ?? null,
                'latency_ms' => $result['processing']['duration_ms'] ?? null,
                'metadata_json' => $metadata,
                'error_code' => null,
                'error_message' => null,
                'completed_at' => now(),
                'worker_id' => $workerId,
                'claimed_at' => $run->claimed_at ?: now(),
                'lease_expires_at' => null,
                'last_heartbeat_at' => now(),
            ])->save();

            $document->forceFill([
                'processing_status' => 'processing',
                'processing_error' => null,
            ])->save();

            $document->processingJobs()
                ->where('job_type', 'beo_extract')
                ->latest('created_at')
                ->first()?->forceFill([
                    'status' => 'completed',
                    'result_json' => $metadata,
                    'completed_at' => now(),
                ])->save();

            $persistedBatch->forceFill(['status' => 'review_required'])->save();

            Log::info('beo.extraction.stage', [
                'stage' => 'awaiting_review',
                'extraction_run_id' => $run->id,
                'document_id' => $document->id,
                'correlation_id' => $run->correlation_id,
                'worker_id' => $workerId,
                'event_order_count' => count($result['event_orders'] ?? []),
            ]);

            return $run->fresh(['fields', 'requestedBy', 'document', 'importBatch']);
        });
    }

    /** @return array<string, mixed> */
    private function mapOrder(array $order, Document $document): array
    {
        return [
            'event_order_number' => trim((string) $order['event_order_number']),
            'quote_number' => $order['quote_number'] ?? null,
            'folio_number' => $order['folio_number'] ?? null,
            'source_organization' => $order['organization'] ?? null,
            'source_system' => 'humoo-beo-extractor',
            'property_id' => null,
            'event_id' => null,
            'versions' => [[
                'document_id' => $document->id,
                'revision_number' => $order['revision']['number'] ?? null,
                'revision_label' => $order['revision']['raw_label'] ?? null,
                'revision_type' => $order['revision']['kind'] ?? null,
                'date_printed' => $this->dateOrNull($order['event_date'] ?? null),
                'source_pages' => $order['source_pages'] ?? [],
                'source_metadata' => [
                    'program_name' => $order['program_name'] ?? null,
                    'property_name' => $order['property_name'] ?? null,
                    'location_text' => $order['location_text'] ?? null,
                    'source_trace' => $order['source_trace'] ?? null,
                ],
                'status' => 'review_required',
                'review_status' => 'pending',
                'functions' => array_map(fn (array $function): array => $this->mapFunction($function), $order['functions'] ?? []),
                'references' => array_map(fn (array $reference): array => [
                    'source_event_function_key' => $reference['source_function_key'] ?? null,
                    'target_event_order_number' => $reference['target_event_order_number'],
                    'reference_type' => $reference['reference_type'] ?? null,
                    'raw_text' => $reference['source_text'],
                    'source_reference' => $reference['source_trace'] ?? null,
                ], $order['references'] ?? []),
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function mapFunction(array $function): array
    {
        $attendance = $function['attendance'] ?? [];
        $menu = $function['menu'] ?? [];

        return [
            'source_function_key' => $function['source_key'] ?? null,
            'source_function_name' => $function['source_function_name'],
            'function_type' => $function['normalized_type'] ?? null,
            'post_as' => $function['post_as'] ?? null,
            'source_start_time' => $function['start_time'] ?? null,
            'source_end_time' => $function['end_time'] ?? null,
            'start_at' => $this->dateOrNull($function['start_datetime'] ?? null),
            'end_at' => $this->dateOrNull($function['end_datetime'] ?? null),
            'source_location_text' => $function['source_location_text'] ?? null,
            'expected_count' => $attendance['expected_count'] ?? null,
            'guaranteed_count' => $attendance['guaranteed_count'] ?? null,
            'set_count' => $attendance['set_count'] ?? null,
            'production_count' => null,
            'menu_status' => $menu['status'] ?? 'none',
            'operational_signals' => $function['relevance_signals'] ?? null,
            'source_metadata' => [
                'menu' => $menu,
                'staffing' => $function['staffing'] ?? [],
                'setup' => $function['setup'] ?? [],
                'av' => $function['av'] ?? [],
                'attachments' => $function['attachments'] ?? [],
                'source_trace' => $function['source_trace'] ?? null,
            ],
            'review_metadata' => ['confidence' => $function['confidence'] ?? null],
            'dietary_requirements' => array_map(fn (array $item): array => [
                'guest_name' => $item['guest_name'] ?? null,
                'count' => $item['count'] ?? null,
                'raw_restriction' => $item['source_restriction'],
                'normalized_restriction' => $item['normalized_restriction'] ?? null,
                'category' => strtoupper((string) ($item['category'] ?? 'other')),
                'source_text' => $item['source_restriction'],
                'source_reference' => $item['source_trace'] ?? null,
            ], $function['dietary_requirements'] ?? []),
            'instructions' => array_map(fn (array $item): array => [
                'category' => $item['category'] ?? 'general',
                'raw_text' => $item['source_text'],
                'normalized_text' => $item['normalized_text'] ?? null,
                'source_reference' => $item['source_trace'] ?? null,
            ], $function['operational_instructions'] ?? []),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildReviewFields(ExtractionRun $run, array $result): array
    {
        $fields = [];
        foreach ($result['event_orders'] ?? [] as $orderIndex => $order) {
            $prefix = "event_orders.{$orderIndex}";
            $this->addField($fields, "{$prefix}.event_order_number", $order['event_order_number'], $order['source_trace'] ?? null);
            foreach ([
                'organization' => 'string',
                'program_name' => 'string',
                'event_date' => 'date',
                'property_name' => 'string',
                'location_text' => 'string',
            ] as $key => $type) {
                if (($order[$key] ?? null) !== null) {
                    $this->addField($fields, "{$prefix}.{$key}", $order[$key], $order['source_trace'] ?? null, $type);
                }
            }
            foreach ($order['functions'] ?? [] as $functionIndex => $function) {
                $functionPrefix = "{$prefix}.functions.{$functionIndex}";
                $trace = $function['source_trace'] ?? $order['source_trace'] ?? null;
                $this->addField($fields, "{$functionPrefix}.source_function_name", $function['source_function_name'], $trace);
                foreach (['expected_count', 'guaranteed_count', 'set_count'] as $attendanceKey) {
                    if (($function['attendance'][$attendanceKey] ?? null) !== null) {
                        $this->addField($fields, "{$functionPrefix}.attendance.{$attendanceKey}", $function['attendance'][$attendanceKey], $function['attendance']['source_trace'] ?? $trace, 'integer');
                    }
                }
                $this->addField($fields, "{$functionPrefix}.menu.status", $function['menu']['status'] ?? 'none', $function['menu']['source_trace'] ?? $trace);
            }
        }
        foreach ($result['unresolved_items'] ?? [] as $index => $item) {
            $this->addField($fields, "unresolved_items.{$index}.source_text", $item['source_text'], $item['source_trace'] ?? null);
        }

        return $fields;
    }

    private function addField(array &$fields, string $key, mixed $value, ?array $trace, string $type = 'string'): void
    {
        $jsonValue = is_array($value) ? $value : null;
        $textValue = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value === null ? null : (string) $value);
        $fields[] = [
            'field_key' => $key,
            'value_type' => $type,
            'value_text' => $textValue,
            'value_json' => $jsonValue,
            'raw_value' => $textValue,
            'confidence' => $trace['confidence'] ?? null,
            'page_number' => $trace['page_numbers'][0] ?? null,
            'source_location' => $trace,
            'review_status' => 'pending',
            'reviewed' => false,
        ];
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function batchMetadata(array $result, string $workerId): array
    {
        return [
            'source' => 'beo-extractor',
            'worker_id' => $workerId,
            'result_status' => $result['status'] ?? null,
            'document_analysis' => Arr::only($result['document_analysis'] ?? [], ['page_count', 'text_mode', 'ocr_used']),
            'issue_count' => count($result['issues'] ?? []),
            'warning_count' => count($result['warnings'] ?? []),
            'unresolved_count' => count($result['unresolved_items'] ?? []),
        ];
    }
}
