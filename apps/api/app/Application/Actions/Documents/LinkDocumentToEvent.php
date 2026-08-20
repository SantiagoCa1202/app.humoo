<?php

namespace App\Application\Actions\Documents;

use App\Models\Beo;
use App\Models\BeoVersion;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\Event;
use App\Support\BeoVersionComparer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkDocumentToEvent
{
    public function __construct(
        private BeoVersionComparer $beoVersionComparer
    ) {
    }

    public function execute(
        Document $document,
        Event $event,
        string $userId
    ): Document {
        if ($document->workspace_id !== $event->workspace_id) {
            throw ValidationException::withMessages([
                'event_id' => [
                    'The selected event does not belong to the same workspace.',
                ],
            ]);
        }

        return DB::transaction(function () use ($document, $event, $userId): Document {
            $existingPrimaryLink = DocumentLink::query()
                ->where('document_id', $document->id)
                ->where('entity_type', 'event')
                ->where('is_primary', true)
                ->first();

            if (
                $existingPrimaryLink
                && $existingPrimaryLink->entity_id !== $event->id
                && $document->beoVersions()->exists()
            ) {
                throw ValidationException::withMessages([
                    'event_id' => [
                        'This document is already versioned against a different event.',
                    ],
                ]);
            }

            DocumentLink::query()
                ->where('document_id', $document->id)
                ->where('entity_type', 'event')
                ->update(['is_primary' => false]);

            DocumentLink::query()->updateOrCreate([
                'document_id' => $document->id,
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'relationship_type' => $document->type === 'beo'
                    ? 'beo'
                    : 'attachment',
            ], [
                'workspace_id' => $document->workspace_id,
                'is_primary' => true,
                'linked_by' => $userId,
                'sort_order' => 0,
            ]);

            if ($document->type !== 'beo') {
                return $document->fresh();
            }

            $beo = Beo::query()->firstOrCreate([
                'workspace_id' => $document->workspace_id,
                'event_id' => $event->id,
            ], [
                'current_version' => 0,
                'status' => 'active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $existingVersion = BeoVersion::query()
                ->where('beo_id', $beo->id)
                ->where('document_id', $document->id)
                ->first();

            if ($existingVersion) {
                return $document->fresh();
            }

            $nextVersionNumber = (int) BeoVersion::query()
                ->where('beo_id', $beo->id)
                ->max('version') + 1;

            $latestExtractionRun = $document->latestExtractionRun()->first();
            $latestFields = $latestExtractionRun?->fields()->get() ?? collect();
            $snapshot = $latestFields->isNotEmpty()
                ? $this->beoVersionComparer->buildSnapshotFromFields($latestFields)
                : null;

            $version = BeoVersion::query()->create([
                'workspace_id' => $document->workspace_id,
                'beo_id' => $beo->id,
                'document_id' => $document->id,
                'version' => $nextVersionNumber,
                'status' => $snapshot ? 'review_required' : 'processing',
                'snapshot_json' => $snapshot,
                'source' => (string) ($document->metadata['source'] ?? 'upload'),
                'created_by' => $userId,
            ]);

            if ($latestExtractionRun && !$latestExtractionRun->beo_version_id) {
                $latestExtractionRun->forceFill([
                    'beo_version_id' => $version->id,
                    'status' => $latestFields->isNotEmpty()
                        ? 'review_required'
                        : $latestExtractionRun->status,
                ])->save();
            }

            $beo->forceFill([
                'current_version' => $nextVersionNumber,
                'updated_by' => $userId,
            ])->save();

            if ($snapshot) {
                $this->beoVersionComparer->syncChanges($version);
            }

            return $document->fresh();
        });
    }
}
