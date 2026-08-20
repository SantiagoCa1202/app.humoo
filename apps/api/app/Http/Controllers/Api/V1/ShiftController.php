<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\TeamStaff\CreateShift;
use App\Application\Actions\TeamStaff\UpdateShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamStaff\StoreShiftRequest;
use App\Http\Requests\TeamStaff\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Shift::class);

        $workspace = app('currentWorkspace');
        $from = $request->input('from');
        $to = $request->input('to');

        $shifts = Shift::query()
            ->where('workspace_id', $workspace->id)
            ->with($this->relations())
            ->when($request->filled('membership_id'), fn ($query) => $query->where('membership_id', $request->input('membership_id')))
            ->when($request->filled('team_id'), fn ($query) => $query->where('team_id', $request->input('team_id')))
            ->when($request->filled('station_id'), fn ($query) => $query->where('station_id', $request->input('station_id')))
            ->when($request->filled('event_id'), fn ($query) => $query->where('event_id', $request->input('event_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($from, fn ($query) => $query->where('ends_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('starts_at', '<=', $to))
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'data' => ShiftResource::collection($shifts),
        ]);
    }

    public function store(
        StoreShiftRequest $request,
        CreateShift $action,
        AuditLogger $auditLogger
    ) {
        $this->authorize('create', Shift::class);

        $workspace = app('currentWorkspace');
        $shift = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'shift.created',
            Shift::class,
            $shift->id,
            null,
            $shift->toArray()
        );

        return response()->json([
            'data' => new ShiftResource($shift),
        ], 201);
    }

    public function show(Shift $shift)
    {
        $workspace = app('currentWorkspace');

        abort_unless($shift->workspace_id === $workspace->id, 404);
        $this->authorize('view', $shift);

        return response()->json([
            'data' => new ShiftResource($this->loadShift($shift)),
        ]);
    }

    public function update(
        UpdateShiftRequest $request,
        Shift $shift,
        UpdateShift $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($shift->workspace_id === $workspace->id, 404);
        $this->authorize('update', $shift);

        $before = $this->loadShift($shift)->toArray();
        $updated = $action->execute(
            $shift,
            $request->user()->id,
            $request->validated()
        );

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'shift.updated',
            Shift::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new ShiftResource($updated),
        ]);
    }

    public function destroy(
        Request $request,
        Shift $shift,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($shift->workspace_id === $workspace->id, 404);
        $this->authorize('delete', $shift);

        $before = $this->loadShift($shift)->toArray();
        $shift->delete();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'shift.deleted',
            Shift::class,
            $shift->id,
            $before,
            null
        );

        return response()->noContent();
    }

    private function relations(): array
    {
        return [
            'conflicts.membership.role',
            'conflicts.membership.teams',
            'conflicts.membership.user',
            'event',
            'membership.role',
            'membership.teams',
            'membership.user',
            'station.team',
            'team',
        ];
    }

    private function loadShift(Shift $shift): Shift
    {
        return $shift->fresh($this->relations());
    }
}
