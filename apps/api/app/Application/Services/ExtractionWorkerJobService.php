<?php

namespace App\Application\Services;

use App\Application\Actions\Documents\PersistBeoExtractionResult;
use App\Data\BeoExtraction\V1\BeoExtractionContractValidator;
use App\Models\ExtractionRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ExtractionWorkerJobService
{
    public function __construct(
        private BeoExtractionContractValidator $validator,
        private PersistBeoExtractionResult $persistResult
    ) {
    }

    /** @return array<string, mixed>|null */
    public function claim(string $workerId): ?array
    {
        return DB::transaction(function () use ($workerId): ?array {
            $run = ExtractionRun::query()
                ->with(['document', 'importBatch'])
                ->where(function ($query): void {
                    $query->where('status', 'pending')
                        ->orWhere(function ($stale): void {
                            $stale->where('status', 'processing')
                                ->whereNotNull('lease_expires_at')
                                ->where('lease_expires_at', '<=', now());
                        });
                })
                ->whereHas('document', fn ($query) => $query->whereNull('deleted_at')->where('visibility', 'private'))
                ->orderBy('queued_at')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (!$run) {
                return null;
            }

            $attempt = (int) $run->attempt;
            if ($run->status === 'processing') {
                $attempt++;
            }
            if ($attempt > (int) config('extraction.max_attempts', 3)) {
                $run->forceFill([
                    'status' => 'failed',
                    'error_code' => 'MAX_ATTEMPTS_EXCEEDED',
                    'error_message' => 'Extraction lease expired too many times.',
                    'completed_at' => now(),
                    'lease_expires_at' => null,
                ])->save();

                return null;
            }

            $now = now();
            $run->forceFill([
                'status' => 'processing',
                'attempt' => $attempt,
                'worker_id' => $workerId,
                'claimed_at' => $now,
                'started_at' => $run->started_at ?: $now,
                'last_heartbeat_at' => $now,
                'lease_expires_at' => $now->copy()->addSeconds((int) config('extraction.lease_seconds', 300)),
                'error_code' => null,
                'error_message' => null,
            ])->save();

            if (!$run->correlation_id) {
                $run->forceFill(['correlation_id' => (string) Str::ulid()])->save();
            }

            Log::info('beo.extraction.stage', [
                'stage' => 'claimed',
                'extraction_run_id' => $run->id,
                'document_id' => $run->document_id,
                'correlation_id' => $run->correlation_id,
                'worker_id' => $workerId,
                'attempt' => $run->attempt,
            ]);

            return [
                'run' => [
                    'id' => $run->id,
                    'document_id' => $run->document_id,
                    'attempt' => $run->attempt,
                    'lease_expires_at' => $run->lease_expires_at?->toIso8601String(),
                ],
                'job' => $this->buildJob($run),
            ];
        });
    }

    public function heartbeat(ExtractionRun $run, string $workerId): ExtractionRun
    {
        abort_unless($this->ownedProcessingRun($run, $workerId), 409, 'Extraction run is not owned by this worker.');
        $now = now();
        $run->forceFill([
            'last_heartbeat_at' => $now,
            'lease_expires_at' => $now->copy()->addSeconds((int) config('extraction.lease_seconds', 300)),
        ])->save();

        return $run->fresh();
    }

    public function download(ExtractionRun $run, string $workerId): Response
    {
        abort_unless($this->ownedProcessingRun($run, $workerId), 404);
        $document = $run->document()->firstOrFail();
        abort_unless($document->visibility === 'private' && $document->deleted_at === null, 404);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_filename ?: $document->name
        );
    }

    /** @param array<string, mixed> $result */
    public function submitResult(ExtractionRun $run, string $workerId, array $result): ExtractionRun
    {
        $checksum = hash('sha256', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($run->result_checksum === $checksum
            && $run->result_status !== null
            && $run->worker_id === $workerId) {
            return $run->fresh(['fields', 'requestedBy', 'document', 'importBatch']);
        }
        abort_unless($this->ownedProcessingRun($run, $workerId), 409, 'Extraction run is not owned by this worker.');

        try {
            $this->validator->validateResult($result);
        } catch (ValidationException $exception) {
            abort(response()->json([
                'message' => 'Extraction result contract validation failed.',
                'code' => 'CONTRACT_VALIDATION_FAILED',
                'errors' => $exception->errors(),
            ], 422));
        }

        abort_unless($result['extraction_run_id'] === $run->id, 422, 'Result run id does not match the claimed run.');
        abort_unless($result['document_id'] === $run->document_id, 422, 'Result document id does not match the claimed document.');
        abort_unless($result['correlation_id'] === $run->correlation_id, 422, 'Result correlation id does not match the run.');
        abort_unless(($result['import_batch_id'] ?? null) === $run->beo_import_batch_id, 422, 'Result import batch id does not match the run.');
        abort_unless(strtolower($result['document_analysis']['sha256']) === strtolower((string) $run->document()->value('checksum')), 422, 'Result checksum does not match the source document.');

        if (in_array($run->status, ['review_required', 'completed'], true)) {
            abort(409, 'Extraction result was already accepted with a different checksum.');
        }

        if ($result['status'] === 'failed') {
            $run->forceFill([
                'status' => 'failed',
                'result_status' => 'failed',
                'result_checksum' => $checksum,
                'metadata_json' => ['result_status' => 'failed', 'worker_id' => $workerId],
                'error_code' => $this->firstIssueCode($result) ?: 'EXTRACTION_FAILED',
                'error_message' => $this->firstIssueMessage($result) ?: 'Extractor reported a terminal failure.',
                'completed_at' => now(),
                'lease_expires_at' => null,
            ])->save();
            $run->document()->update(['processing_status' => 'failed', 'processing_error' => $run->error_message]);
            $run->document()->processingJobs()
                ->where('job_type', 'beo_extract')
                ->latest('created_at')
                ->first()?->forceFill([
                    'status' => 'failed',
                    'error_code' => $run->error_code,
                    'error_message' => $run->error_message,
                    'completed_at' => now(),
                ])->save();

            return $run->fresh();
        }

        $persisted = $this->persistResult->execute($run, $result, $workerId);

        Log::info('beo.extraction.stage', [
            'stage' => 'validated_persisted',
            'extraction_run_id' => $persisted->id,
            'document_id' => $persisted->document_id,
            'correlation_id' => $persisted->correlation_id,
            'worker_id' => $workerId,
            'result_status' => $persisted->result_status,
        ]);

        return $persisted->fresh(['fields', 'requestedBy', 'document', 'importBatch']);
    }

    /** @param array<string, mixed> $failure */
    public function submitFailure(ExtractionRun $run, string $workerId, array $failure): ExtractionRun
    {
        abort_unless($this->ownedProcessingRun($run, $workerId), 409, 'Extraction run is not owned by this worker.');
        $retryable = (bool) ($failure['retryable'] ?? false);
        $message = trim((string) ($failure['message'] ?? 'Extractor worker failed.'));
        $run->forceFill([
            'status' => $retryable ? 'pending' : 'failed',
            'attempt' => $retryable ? ((int) $run->attempt + 1) : $run->attempt,
            'error_code' => trim((string) ($failure['code'] ?? 'WORKER_FAILURE')),
            'error_message' => mb_substr($message, 0, 1000),
            'completed_at' => $retryable ? null : now(),
            'queued_at' => $retryable ? now() : $run->queued_at,
            'lease_expires_at' => null,
            'worker_id' => $retryable ? null : $workerId,
        ])->save();
        $run->document()->update([
            'processing_status' => $retryable ? 'processing' : 'failed',
            'processing_error' => $retryable ? null : $run->error_message,
        ]);
        $run->document()->processingJobs()
            ->where('job_type', 'beo_extract')
            ->latest('created_at')
            ->first()?->forceFill([
                'status' => $retryable ? 'pending' : 'failed',
                'error_code' => $retryable ? null : $run->error_code,
                'error_message' => $retryable ? null : $run->error_message,
                'completed_at' => $retryable ? null : now(),
            ])->save();

        return $run->fresh();
    }

    private function ownedProcessingRun(ExtractionRun $run, string $workerId): bool
    {
        return $run->status === 'processing'
            && $run->worker_id === $workerId
            && $run->lease_expires_at?->isFuture() === true;
    }

    /** @return array<string, mixed> */
    private function buildJob(ExtractionRun $run): array
    {
        $document = $run->document;

        return [
            'schema_version' => $run->schema_version ?: config('extraction.schema_version'),
            'extraction_run_id' => $run->id,
            'document_id' => $run->document_id,
            'import_batch_id' => $run->beo_import_batch_id,
            'correlation_id' => $run->correlation_id ?: (string) Str::ulid(),
            'document' => [
                'filename' => $document->original_filename ?: $document->name,
                'mime_type' => $document->mime_type,
                'sha256' => $document->checksum,
                'file_size' => $document->size,
                'source_reference' => null,
                'provider_hint' => $run->provider,
                'language_hints' => [],
                'extensions' => $document->extension ? [$document->extension] : [],
            ],
            'options' => [
                'use_ocr' => false,
                'include_layout' => true,
                'include_source_trace' => true,
                'parser_profile' => null,
            ],
            'requested_at' => ($run->created_at ?: now())->toIso8601String(),
        ];
    }

    private function firstIssueCode(array $result): ?string
    {
        return $result['issues'][0]['code'] ?? null;
    }

    private function firstIssueMessage(array $result): ?string
    {
        return $result['issues'][0]['message'] ?? null;
    }
}
