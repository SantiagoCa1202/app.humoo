<?php

namespace App\Application\Actions\Documents;

use App\Models\BeoVersion;
use App\Models\Document;
use App\Models\ExtractedField;
use App\Models\ExtractionRun;
use App\Support\BeoVersionComparer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewDocumentExtraction
{
    public function __construct(
        private BeoVersionComparer $beoVersionComparer
    ) {
    }

    public function execute(
        Document $document,
        string $expectedUpdatedAt,
        array $fields,
        ?string $reviewNotes,
        string $userId
    ): ?ExtractionRun {
        return DB::transaction(function () use (
            $document,
            $expectedUpdatedAt,
            $fields,
            $reviewNotes,
            $userId
        ): ?ExtractionRun {
            $run = ExtractionRun::query()
                ->where('document_id', $document->id)
                ->latest('created_at')
                ->lockForUpdate()
                ->first();

            if (!$run) {
                return null;
            }

            if ($run->updated_at?->toISOString() !== $expectedUpdatedAt) {
                return null;
            }

            $fieldPayloads = collect($fields)->keyBy('id');
            $runFields = ExtractedField::query()
                ->where('extraction_run_id', $run->id)
                ->get();

            $runFields->each(function (ExtractedField $field) use ($fieldPayloads, $userId): void {
                $payload = $fieldPayloads->get($field->id);

                if (!$payload) {
                    return;
                }

                $field->forceFill([
                    'review_status' => $payload['review_status'],
                    'corrected_value_text' => $payload['corrected_value_text'] ?? null,
                    'corrected_value_json' => array_key_exists('corrected_value_json', $payload)
                        ? $payload['corrected_value_json']
                        : null,
                    'review_notes' => $payload['review_notes'] ?? null,
                    'reviewed' => $payload['review_status'] !== 'pending',
                    'reviewed_by' => $payload['review_status'] !== 'pending'
                        ? $userId
                        : null,
                    'reviewed_at' => $payload['review_status'] !== 'pending'
                        ? now()
                        : null,
                ])->save();
            });

            $run->refresh();
            $runFields = $run->fields()->get();
            $pendingCount = $runFields->where('review_status', 'pending')->count();
            $isCompleted = $pendingCount === 0 && $runFields->isNotEmpty();

            $run->forceFill([
                'status' => $isCompleted
                    ? 'completed'
                    : 'review_required',
                'completed_at' => $isCompleted
                    ? now()
                    : null,
            ])->save();

            $document->forceFill([
                'processing_status' => $isCompleted
                    ? 'ready'
                    : 'processing',
                'processing_error' => null,
                'updated_by' => $userId,
            ])->save();

            $version = $run->beoVersion()->first();

            if ($version) {
                $this->updateVersionAfterReview(
                    $version,
                    $runFields,
                    $reviewNotes,
                    $userId,
                    $isCompleted
                );
            }

            return $run->fresh([
                'fields.reviewedBy',
                'requestedBy',
                'beoVersion.document',
                'beoVersion.beo.event.client.primaryContact',
                'beoVersion.beo.event.contact.client',
                'beoVersion.beo.event.venue',
            ]);
        });
    }

    private function updateVersionAfterReview(
        BeoVersion $version,
        Collection $runFields,
        ?string $reviewNotes,
        string $userId,
        bool $isCompleted
    ): void {
        $snapshot = $this->beoVersionComparer->buildSnapshotFromFields($runFields);

        $version->forceFill([
            'snapshot_json' => $snapshot,
            'review_notes' => $reviewNotes,
            'status' => $isCompleted
                ? 'approved'
                : 'review_required',
            'approved_by' => $isCompleted
                ? $userId
                : null,
            'approved_at' => $isCompleted
                ? now()
                : null,
        ])->save();

        if ($isCompleted) {
            BeoVersion::query()
                ->where('beo_id', $version->beo_id)
                ->where('id', '!=', $version->id)
                ->whereIn('status', [
                    'approved',
                    'review_required',
                ])
                ->update([
                    'status' => 'superseded',
                    'updated_at' => now(),
                ]);

            $version->beo()->update([
                'current_version' => $version->version,
                'approved_at' => now(),
                'approved_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        $changes = $this->beoVersionComparer->syncChanges($version);

        $version->forceFill([
            'change_summary' => $changes->isNotEmpty()
                ? sprintf('%d change(s) detected.', $changes->count())
                : 'No changes detected.',
        ])->save();
    }
}
