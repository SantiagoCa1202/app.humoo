<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venues\StoreVenueRequest;
use App\Http\Requests\Venues\UpdateVenueRequest;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Venue::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) ($request->input('search') ?? $request->input('filter.search', '')));
        $status = $request->input('status') ?? $request->input('filter.status');

        $venues = Venue::query()
            ->where('workspace_id', $workspace->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->orderBy('name')
            ->get();

        return VenueResource::collection($venues);
    }

    public function store(
        StoreVenueRequest $request,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        $venue = Venue::query()->create([
            ...$request->validated(),
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'status' => $request->validated('status', 'active'),
        ]);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'venue.created',
            Venue::class,
            $venue->id,
            null,
            $venue->toArray()
        );

        return (new VenueResource($venue))->response()->setStatusCode(201);
    }

    public function show(Venue $venue)
    {
        $workspace = app('currentWorkspace');

        abort_unless($venue->workspace_id === $workspace->id, 404);

        $this->authorize('view', $venue);

        return new VenueResource($venue);
    }

    public function update(
        UpdateVenueRequest $request,
        Venue $venue,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($venue->workspace_id === $workspace->id, 404);

        $before = $venue->toArray();

        $venue->forceFill([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ])->save();

        $venue = $venue->fresh();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'venue.updated',
            Venue::class,
            $venue->id,
            $before,
            $venue->toArray()
        );

        return new VenueResource($venue);
    }

    public function destroy(
        Request $request,
        Venue $venue,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($venue->workspace_id === $workspace->id, 404);

        $this->authorize('delete', $venue);

        $activeEventsCount = $venue->events()->count();

        if ($activeEventsCount > 0) {
            return response()->json([
                'message' => 'This venue cannot be deleted while related events still exist.',
                'data' => [
                    'events_count' => $activeEventsCount,
                ],
            ], 409);
        }

        $before = $venue->toArray();
        $venue->delete();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'venue.deleted',
            Venue::class,
            $venue->id,
            $before,
            null
        );

        return response()->noContent();
    }
}
