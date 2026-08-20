<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\TeamStaff\CreateStation;
use App\Application\Actions\TeamStaff\UpdateStation;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamStaff\StoreStationRequest;
use App\Http\Requests\TeamStaff\UpdateStationRequest;
use App\Http\Resources\StationResource;
use App\Models\Station;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class StationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Station::class);

        $workspace = app('currentWorkspace');
        $status = trim((string) $request->input('status', ''));
        $teamId = $request->input('team_id');

        $stations = Station::query()
            ->where('workspace_id', $workspace->id)
            ->with(['team'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => StationResource::collection($stations),
        ]);
    }

    public function store(
        StoreStationRequest $request,
        CreateStation $action,
        AuditLogger $auditLogger
    ) {
        $this->authorize('create', Station::class);

        $workspace = app('currentWorkspace');
        $station = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->validated()
        )->fresh(['team']);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'station.created',
            Station::class,
            $station->id,
            null,
            $station->toArray()
        );

        return response()->json([
            'data' => new StationResource($station),
        ], 201);
    }

    public function show(Station $station)
    {
        $workspace = app('currentWorkspace');

        abort_unless($station->workspace_id === $workspace->id, 404);
        $this->authorize('view', $station);

        return response()->json([
            'data' => new StationResource($station->fresh(['team'])),
        ]);
    }

    public function update(
        UpdateStationRequest $request,
        Station $station,
        UpdateStation $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($station->workspace_id === $workspace->id, 404);
        $this->authorize('update', $station);

        $before = $station->fresh(['team'])->toArray();
        $updated = $action->execute(
            $station,
            $request->user()->id,
            $request->validated()
        );

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'station.updated',
            Station::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new StationResource($updated),
        ]);
    }

    public function destroy(
        Request $request,
        Station $station,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($station->workspace_id === $workspace->id, 404);
        $this->authorize('delete', $station);

        $before = $station->fresh(['team'])->toArray();
        $station->delete();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'station.deleted',
            Station::class,
            $station->id,
            $before,
            null
        );

        return response()->noContent();
    }
}
