<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Venues\CreateVenue;
use App\Application\Actions\Venues\DeleteVenue;
use App\Application\Actions\Venues\UpdateVenue;
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
        CreateVenue $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        $venue = $action->execute($workspace->id, $request->user()->id, $request->validated());

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
        UpdateVenue $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($venue->workspace_id === $workspace->id, 404);

        $before = $venue->toArray();

        $venue = $action->execute($venue, $request->user()->id, $request->validated());

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
        DeleteVenue $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($venue->workspace_id === $workspace->id, 404);

        $this->authorize('delete', $venue);

        $result = $action->execute($venue);
        if (!$result['deleted']) {
            return response()->json([
                'message' => 'This venue cannot be deleted while related events still exist.',
                'data' => $result['dependencies'],
            ], 409);
        }

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'venue.deleted',
            Venue::class,
            $venue->id,
            $result['before'],
            null
        );

        return response()->noContent();
    }
}
