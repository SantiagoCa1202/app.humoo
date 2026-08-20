<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Events\CreateEvent;
use App\Application\Actions\Events\UpdateEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $workspace = app('currentWorkspace');
        $statusFilter = $request->input('status', $request->input('filter.status', []));
        $search = trim((string) ($request->input('search') ?? $request->input('filter.search', '')));
        $clientId = $request->input('client_id') ?? $request->input('filter.client_id');
        $venueId = $request->input('venue_id') ?? $request->input('filter.venue_id');
        $serviceType = $request->input('service_type') ?? $request->input('filter.service_type');
        $dateFrom = $request->input('date_from') ?? $request->input('filter.date_from');
        $dateTo = $request->input('date_to') ?? $request->input('filter.date_to');
        $perPage = max(1, min((int) $request->input('per_page', 25), 100));
        $statuses = collect(is_array($statusFilter) ? $statusFilter : explode(',', (string) $statusFilter))
            ->filter(fn ($value) => filled($value))
            ->values()
            ->all();

        $events = Event::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'client.primaryContact',
                'contact.client',
                'group',
                'venue',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('event_number', 'like', "%{$search}%")
                        ->orWhere('service_type', 'like', "%{$search}%")
                        ->orWhere('event_type', 'like', "%{$search}%")
                        ->orWhere('client_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('contact_name_snapshot', 'like', "%{$search}%")
                        ->orWhere('venue_name_snapshot', 'like', "%{$search}%");
                });
            })
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($clientId, fn ($query, $value) => $query->where('client_id', $value))
            ->when($venueId, fn ($query, $value) => $query->where('venue_id', $value))
            ->when($serviceType, fn ($query, $value) => $query->where('service_type', $value))
            ->when($dateFrom, function ($query, $value): void {
                $query->whereRaw('COALESCE(ends_at, starts_at) >= ?', [$value]);
            })
            ->when($dateTo, function ($query, $value): void {
                $query->where('starts_at', '<=', $value);
            })
            ->orderBy('starts_at')
            ->cursorPaginate($perPage);

        return response()->json([
            'data' => EventResource::collection(collect($events->items())),
            'path' => $events->path(),
            'per_page' => $events->perPage(),
            'next_cursor' => $events->nextCursor()?->encode(),
            'next_page_url' => $events->nextPageUrl(),
            'prev_cursor' => $events->previousCursor()?->encode(),
            'prev_page_url' => $events->previousPageUrl(),
        ]);
    }

    public function store(
        StoreEventRequest $request,
        CreateEvent $action,
        AuditLogger $auditLogger
    ) {
        $this->authorize('create', Event::class);

        $workspace = app('currentWorkspace');

        $event = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'event.created',
            Event::class,
            $event->id,
            null,
            $event->toArray()
        );

        return (new EventResource($this->loadEventRelations($event)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Event $event)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $event->workspace_id === $workspace->id,
            404
        );

        $this->authorize('view', $event);

        return new EventResource($this->loadEventRelations($event));
    }

    public function update(
        UpdateEventRequest $request,
        Event $event,
        UpdateEvent $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($event->workspace_id === $workspace->id, 404);

        $this->authorize('update', $event);

        $before = $event->toArray();
        $updated = $action->execute(
            $event,
            $request->integer('version'),
            $request->safe()->except('version'),
            $request->user()?->id
        );

        if (!$updated) {
            return response()->json([
                'message' => 'Resource conflict.',
                'code' => 'VERSION_CONFLICT',
                'data' => (new EventResource(
                    $this->loadEventRelations(
                        Event::query()
                            ->whereKey($event->getKey())
                            ->where('workspace_id', $workspace->id)
                            ->firstOrFail()
                    )
                ))->resolve(),
            ], 409);
        }

        $updated = $this->loadEventRelations($updated);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'event.updated',
            Event::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return new EventResource($updated);
    }

    public function destroy(
        Request $request,
        Event $event,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($event->workspace_id === $workspace->id, 404);

        $this->authorize('delete', $event);

        $dependencyCounts = [
            'beo_count' => $event->beo()->count(),
            'menus_count' => $event->menus()->count(),
            'notes_count' => $event->notes()->count(),
            'prep_lists_count' => $event->prepLists()->count(),
            'staff_count' => $event->staff()->count(),
        ];

        if (array_sum($dependencyCounts) > 0) {
            return response()->json([
                'message' => 'This event cannot be deleted while related records still exist.',
                'data' => $dependencyCounts,
            ], 409);
        }

        $before = $event->toArray();

        DB::transaction(function () use ($event): void {
            $event->statusHistory()->delete();
            $event->delete();
        });

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'event.deleted',
            Event::class,
            $event->id,
            $before,
            null
        );

        return response()->noContent();
    }

    private function loadEventRelations(Event $event): Event
    {
        return $event->load([
            'client.primaryContact',
            'contact.client',
            'group',
            'venue',
        ]);
    }
}
