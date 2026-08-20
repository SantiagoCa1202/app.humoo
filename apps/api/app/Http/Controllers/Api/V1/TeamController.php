<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\TeamStaff\CreateTeam;
use App\Application\Actions\TeamStaff\SyncTeamMembers;
use App\Application\Actions\TeamStaff\UpdateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamStaff\StoreTeamRequest;
use App\Http\Requests\TeamStaff\SyncTeamMembersRequest;
use App\Http\Requests\TeamStaff\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Team::class);

        $workspace = app('currentWorkspace');
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));

        $teams = Team::query()
            ->where('workspace_id', $workspace->id)
            ->with($this->relations())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => TeamResource::collection($teams),
        ]);
    }

    public function store(
        StoreTeamRequest $request,
        CreateTeam $action,
        AuditLogger $auditLogger
    )
    {
        $this->authorize('create', Team::class);

        $workspace = app('currentWorkspace');
        $team = $action->execute(
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );
        $team = $this->loadTeam($team);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'team.created',
            Team::class,
            $team->id,
            null,
            $team->toArray()
        );

        return response()->json([
            'data' => new TeamResource($team),
        ], 201);
    }

    public function show(Team $team)
    {
        $workspace = app('currentWorkspace');

        abort_unless($team->workspace_id === $workspace->id, 404);
        $this->authorize('view', $team);

        return response()->json([
            'data' => new TeamResource($this->loadTeam($team)),
        ]);
    }

    public function update(
        UpdateTeamRequest $request,
        Team $team,
        UpdateTeam $action,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($team->workspace_id === $workspace->id, 404);
        $this->authorize('update', $team);

        $before = $this->loadTeam($team)->toArray();
        $updated = $action->execute(
            $team,
            $workspace->id,
            $request->user()->id,
            $request->validated()
        );
        $updated = $this->loadTeam($updated);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'team.updated',
            Team::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new TeamResource($updated),
        ]);
    }

    public function destroy(
        Request $request,
        Team $team,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless($team->workspace_id === $workspace->id, 404);
        $this->authorize('delete', $team);

        $before = $this->loadTeam($team)->toArray();
        $team->delete();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'team.deleted',
            Team::class,
            $team->id,
            $before,
            null
        );

        return response()->noContent();
    }

    public function syncMembers(
        SyncTeamMembersRequest $request,
        Team $team,
        SyncTeamMembers $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($team->workspace_id === $workspace->id, 404);
        $this->authorize('update', $team);

        $before = $this->loadTeam($team)->toArray();
        $updated = $action->execute(
            $team,
            $workspace->id,
            $request->validated('member_ids', []),
            $request->validated('lead_membership_id')
        );
        $updated = $this->loadTeam($updated);

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'team.members.synced',
            Team::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new TeamResource($updated),
        ]);
    }

    private function relations(): array
    {
        return [
            'leadMembership.role',
            'leadMembership.user',
            'members.role',
            'members.teams',
            'members.user',
        ];
    }

    private function loadTeam(Team $team): Team
    {
        return $team->fresh($this->relations());
    }
}
