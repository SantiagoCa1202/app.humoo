<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Documents\ReviewDocumentExtraction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ReviewDocumentExtractionRequest;
use App\Http\Resources\BeoVersionChangeResource;
use App\Http\Resources\BeoVersionResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ExtractedFieldResource;
use App\Http\Resources\ExtractionRunResource;
use App\Models\BeoVersion;
use App\Models\Document;
use App\Models\Event;
use App\Support\BeoVersionComparer;
use App\Support\DocumentStorage;

class BeoController extends Controller
{
    public function versions(Document $document)
    {
        $workspace = app('currentWorkspace');

        abort_unless($document->workspace_id === $workspace->id, 404);

        $this->authorize('view', $document);

        return response()->json([
            'data' => BeoVersionResource::collection($this->resolveVersions($document)),
        ]);
    }

    public function extraction(Document $document)
    {
        $workspace = app('currentWorkspace');

        abort_unless($document->workspace_id === $workspace->id, 404);

        $this->authorize('view', $document);

        $document = $this->loadDocument($document);
        $run = $document->latestExtractionRun;

        return response()->json([
            'data' => [
                'document' => new DocumentResource($document),
                'run' => $run ? new ExtractionRunResource($run) : null,
                'fields' => $run
                    ? ExtractedFieldResource::collection($run->fields)
                    : [],
                'sections' => $this->buildSectionsForFields($run?->fields ?? collect()),
            ],
        ]);
    }

    public function review(
        ReviewDocumentExtractionRequest $request,
        Document $document,
        ReviewDocumentExtraction $action
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($document->workspace_id === $workspace->id, 404);

        $this->authorize('update', $document);

        $run = $action->execute(
            $document,
            $request->validated('expected_updated_at'),
            $request->validated('fields'),
            $request->validated('review_notes'),
            $request->user()->id
        );

        if (!$run) {
            $latestRun = $document->latestExtractionRun()
                ->with([
                    'fields.reviewedBy',
                    'requestedBy',
                ])
                ->first();

            return response()->json([
                'message' => 'Resource conflict.',
                'code' => 'VERSION_CONFLICT',
                'data' => [
                    'run' => $latestRun
                        ? (new ExtractionRunResource($latestRun))->resolve()
                        : null,
                    'fields' => $latestRun
                        ? ExtractedFieldResource::collection($latestRun->fields)->resolve()
                        : [],
                ],
            ], 409);
        }

        $document = $this->loadDocument($document->fresh());
        $version = $run->beoVersion;

        return response()->json([
            'data' => [
                'document' => new DocumentResource($document),
                'run' => new ExtractionRunResource($run),
                'fields' => ExtractedFieldResource::collection($run->fields),
                'sections' => $this->buildSectionsForFields($run->fields),
                'version' => $version
                    ? new BeoVersionResource($version->loadMissing('document'))
                    : null,
                'comparison' => $version
                    ? $this->buildComparisonPayload($version)
                    : null,
            ],
        ]);
    }

    public function comparison(Document $document)
    {
        $workspace = app('currentWorkspace');

        abort_unless($document->workspace_id === $workspace->id, 404);

        $this->authorize('view', $document);

        $versions = $this->resolveVersions($document);
        $targetVersion = $versions->first();

        abort_unless($targetVersion, 404);

        return response()->json([
            'data' => [
                'document' => new DocumentResource($this->loadDocument($document)),
                ...$this->buildComparisonPayload($targetVersion),
            ],
        ]);
    }

    private function resolveVersions(Document $document)
    {
        $version = $document->latestBeoVersion()->first();

        if (!$version?->beo_id) {
            return collect();
        }

        return BeoVersion::query()
            ->with([
                'document.uploadedBy',
                'document.updatedBy',
                'createdBy',
                'approvedBy',
            ])
            ->where('beo_id', $version->beo_id)
            ->orderByDesc('version')
            ->get();
    }

    private function buildSectionsForFields($fields): array
    {
        return $fields
            ->groupBy(fn ($field) => str_contains($field->field_key, '.')
                ? explode('.', $field->field_key)[0]
                : 'general')
            ->map(fn ($items, $sectionKey) => [
                'id' => $sectionKey,
                'title' => ucfirst(str_replace('_', ' ', $sectionKey)),
                'description' => null,
                'field_keys' => $items->pluck('field_key')->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function buildSectionsForChanges($changes): array
    {
        return $changes
            ->groupBy(fn ($change) => str_contains($change->field_key, '.')
                ? explode('.', $change->field_key)[0]
                : 'general')
            ->map(fn ($items, $sectionKey) => [
                'id' => $sectionKey,
                'title' => ucfirst(str_replace('_', ' ', $sectionKey)),
                'description' => null,
                'change_ids' => $items->pluck('id')->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function buildComparisonPayload(BeoVersion $version): array
    {
        $changes = $version->changes()->get();
        $baseVersion = BeoVersion::query()
            ->where('beo_id', $version->beo_id)
            ->where('version', '<', $version->version)
            ->orderByDesc('version')
            ->first();

        return [
            'base_version' => $baseVersion
                ? (new BeoVersionResource($baseVersion))->resolve()
                : null,
            'target_version' => (new BeoVersionResource($version))->resolve(),
            'changes' => BeoVersionChangeResource::collection($changes)->resolve(),
            'sections' => $this->buildSectionsForChanges($changes),
            'impacts' => app(BeoVersionComparer::class)->buildImpactSummary($version),
            'warnings' => $changes
                ->filter(fn ($change) => $change->severity !== 'info')
                ->values()
                ->map(fn ($change) => [
                    'id' => $change->id,
                    'severity' => $change->severity === 'critical'
                        ? 'danger'
                        : 'warning',
                    'title' => 'Operational review required',
                    'description' => $change->field_key,
                ]),
        ];
    }

    private function loadDocument(Document $document): Document
    {
        $document = $document->fresh([
            'uploadedBy',
            'updatedBy',
            'links',
            'latestBeoVersion.beo.event.client.primaryContact',
            'latestBeoVersion.beo.event.contact.client',
            'latestBeoVersion.beo.event.venue',
            'latestBeoVersion.document',
            'latestExtractionRun.fields.reviewedBy',
            'latestExtractionRun.requestedBy',
        ]);

        $linkedEvent = $document->latestBeoVersion?->beo?->event;

        if (!$linkedEvent) {
            $linkedEventId = $document->links
                ? $document->links
                    ->where('entity_type', 'event')
                    ->sortByDesc('is_primary')
                    ->pluck('entity_id')
                    ->first()
                : null;

            if ($linkedEventId) {
                $linkedEvent = Event::query()
                    ->with([
                        'client.primaryContact',
                        'contact.client',
                        'group',
                        'venue',
                    ])
                    ->find($linkedEventId);
            }
        }

        $document->setRelation('linkedEvent', $linkedEvent);
        $document->download_url = app(DocumentStorage::class)
            ->temporaryDownloadUrl($document);

        return $document;
    }
}
