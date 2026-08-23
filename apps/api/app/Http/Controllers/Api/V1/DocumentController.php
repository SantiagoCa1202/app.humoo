<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Documents\LinkDocumentToEvent;
use App\Application\Actions\Documents\UploadDocument;
use App\Application\Actions\Documents\RetryDocumentExtraction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\LinkDocumentToEventRequest;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Resources\BeoResource;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Event;
use App\Services\AuditLogger;
use App\Support\DocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        $workspace = app('currentWorkspace');
        $type = trim((string) $request->input('type', ''));
        $search = trim((string) $request->input('search', ''));
        $eventId = $request->input('event_id');
        $processingStatus = trim((string) $request->input('processing_status', ''));
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));

        $documents = Document::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'uploadedBy',
                'updatedBy',
                'links',
                'latestBeoVersion',
                'latestExtractionRun',
            ])
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($processingStatus !== '', fn ($query) => $query->where('processing_status', $processingStatus))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%");
                });
            })
            ->when($eventId, function ($query, $value): void {
                $query->where(function ($linkedQuery) use ($value): void {
                    $linkedQuery->whereHas('links', function ($linkQuery) use ($value): void {
                        $linkQuery
                            ->where('entity_type', 'event')
                            ->where('entity_id', $value);
                    })->orWhereHas('beoVersions.beo', fn ($beoQuery) => $beoQuery->where('event_id', $value));
                });
            })
            ->latest('created_at')
            ->cursorPaginate($perPage);

        $items = collect($documents->items())
            ->map(fn (Document $document) => $this->decorateDocument($document));

        return response()->json([
            'data' => DocumentResource::collection($items),
            'path' => $documents->path(),
            'per_page' => $documents->perPage(),
            'next_cursor' => $documents->nextCursor()?->encode(),
            'next_page_url' => $documents->nextPageUrl(),
            'prev_cursor' => $documents->previousCursor()?->encode(),
            'prev_page_url' => $documents->previousPageUrl(),
        ]);
    }

    public function store(
        StoreDocumentRequest $request,
        UploadDocument $action,
        AuditLogger $auditLogger
    ) {
        $this->authorize('create', Document::class);

        $workspace = app('currentWorkspace');
        $event = $request->validated('event_id')
            ? Event::query()
                ->where('workspace_id', $workspace->id)
                ->findOrFail($request->validated('event_id'))
            : null;

        $document = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->file('file'),
            $request->validated(),
            $event
        );

        $document = $this->loadDocument($document);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'document.uploaded',
            Document::class,
            $document->id,
            null,
            $document->toArray()
        );

        return response()->json([
            'data' => new DocumentResource($document),
            'meta' => [
                'beo' => $document->latestBeoVersion?->beo
                    ? (new BeoResource($document->latestBeoVersion->beo))->resolve()
                    : null,
            ],
        ], 201);
    }

    public function show(Document $document)
    {
        $workspace = app('currentWorkspace');

        abort_unless($document->workspace_id === $workspace->id, 404);

        $this->authorize('view', $document);

        $document = $this->loadDocument($document);

        return response()->json([
            'data' => new DocumentResource($document),
            'meta' => [
                'beo' => $document->latestBeoVersion?->beo
                    ? (new BeoResource($document->latestBeoVersion->beo))->resolve()
                    : null,
            ],
        ]);
    }

    public function retryExtraction(
        Request $request,
        Document $document,
        RetryDocumentExtraction $action
    ) {
        $workspace = app('currentWorkspace');
        abort_unless($document->workspace_id === $workspace->id, 404);
        $this->authorize('update', $document);

        $run = $action->execute($document, $request->user()->id);

        return response()->json([
            'data' => new DocumentResource($this->loadDocument($document)),
            'run' => new \App\Http\Resources\ExtractionRunResource($run),
        ], 202);
    }

    public function linkEvent(
        LinkDocumentToEventRequest $request,
        Document $document,
        LinkDocumentToEvent $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($document->workspace_id === $workspace->id, 404);

        $this->authorize('update', $document);

        $event = Event::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($request->validated('event_id'));

        $before = $document->toArray();
        $document = $action->execute(
            $document,
            $event,
            $request->user()->id
        );

        $document = $this->loadDocument($document);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'document.event_linked',
            Document::class,
            $document->id,
            $before,
            $document->toArray()
        );

        return response()->json([
            'data' => new DocumentResource($document),
            'meta' => [
                'beo' => $document->latestBeoVersion?->beo
                    ? (new BeoResource($document->latestBeoVersion->beo))->resolve()
                    : null,
            ],
        ]);
    }

    public function downloadSigned(Request $request, Document $document)
    {
        abort_unless($request->hasValidSignature(), 403);

        if ($document->deleted_at !== null) {
            abort(404);
        }

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_filename ?: $document->name
        );
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
            'latestExtractionRun.requestedBy',
        ]);

        return $this->decorateDocument($document);
    }

    private function decorateDocument(Document $document): Document
    {
        $linkedEventId = $document->links
            ? $document->links
                ->where('entity_type', 'event')
                ->sortByDesc('is_primary')
                ->pluck('entity_id')
                ->first()
            : null;

        $linkedEvent = $document->latestBeoVersion?->beo?->event;

        if (!$linkedEvent && $linkedEventId) {
            $linkedEvent = Event::query()
                ->with([
                    'client.primaryContact',
                    'contact.client',
                    'group',
                    'venue',
                ])
                ->find($linkedEventId);
        }

        $document->setRelation('linkedEvent', $linkedEvent);
        $document->download_url = app(DocumentStorage::class)
            ->temporaryDownloadUrl($document);

        return $document;
    }
}
