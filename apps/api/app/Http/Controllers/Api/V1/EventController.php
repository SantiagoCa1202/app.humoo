<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Events\CreateEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Models\Event;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Event::class);

        $workspace = app('currentWorkspace');

        $events = Event::query()
            ->where(
                'workspace_id',
                $workspace->id
            )
            ->when(
                $request->input('filter.status'),
                fn($query, $status) =>
                $query->whereIn(
                    'status',
                    (array) $status
                )
            )
            ->when(
                $request->input('filter.date_from'),
                fn($query, $date) =>
                $query->where(
                    'starts_at',
                    '>=',
                    $date
                )
            )
            ->when(
                $request->input('filter.date_to'),
                fn($query, $date) =>
                $query->where(
                    'starts_at',
                    '<=',
                    $date
                )
            )
            ->orderBy('starts_at')
            ->cursorPaginate(25);

        return response()->json($events);
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

        return response()->json(
            ['data' => $event],
            201
        );
    }

    public function show(Event $event)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $event->workspace_id === $workspace->id,
            404
        );

        $this->authorize('view', $event);

        return response()->json([
            'data' => $event->load([
                'client',
                'contact',
                'venue',
                'staff',
                'menus',
                'prepLists',
            ]),
        ]);
    }
}
