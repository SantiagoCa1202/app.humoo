<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\TeamStaff\SyncAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeamStaff\SyncAvailabilityRequest;
use App\Http\Resources\AvailabilityResource;
use App\Http\Resources\AvailabilityRuleResource;
use App\Http\Resources\WorkspaceMemberReferenceResource;
use App\Models\Availability;
use App\Models\WorkspaceMembership;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Availability::class);

        $workspace = app('currentWorkspace');
        $membershipId = $request->input('membership_id');
        $from = $request->input('from');
        $to = $request->input('to');

        $memberships = WorkspaceMembership::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'availability' => function ($query) use ($from, $to): void {
                    $query
                        ->when($from, fn ($innerQuery) => $innerQuery->where('ends_at', '>=', $from))
                        ->when($to, fn ($innerQuery) => $innerQuery->where('starts_at', '<=', $to))
                        ->orderBy('starts_at');
                },
                'availabilityRules' => fn ($query) => $query->orderBy('day_of_week')->orderBy('starts_at'),
                'role',
                'teams',
                'user',
            ])
            ->when($membershipId, fn ($query) => $query->whereKey($membershipId))
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $memberships->map(function (WorkspaceMembership $membership): array {
                return [
                    'member' => (new WorkspaceMemberReferenceResource($membership))->resolve(),
                    'records' => AvailabilityResource::collection($membership->availability)->resolve(),
                    'rules' => AvailabilityRuleResource::collection($membership->availabilityRules)->resolve(),
                ];
            })->values()->all(),
        ]);
    }

    public function sync(
        SyncAvailabilityRequest $request,
        WorkspaceMembership $membership,
        SyncAvailability $action,
        AuditLogger $auditLogger
    ) {
        $workspace = app('currentWorkspace');

        abort_unless($membership->workspace_id === $workspace->id, 404);
        abort_unless(
            $request->user()->hasWorkspacePermission($workspace->id, 'members.manage'),
            403,
            'You do not have permission to manage staff availability.'
        );

        $before = [
            'records' => $membership->availability()->orderBy('starts_at')->get()->toArray(),
            'rules' => $membership->availabilityRules()->orderBy('day_of_week')->get()->toArray(),
        ];
        $result = $action->execute($membership, $request->validated());
        $membership = $membership->fresh([
            'availability',
            'availabilityRules',
            'role',
            'teams',
            'user',
        ]);

        $after = [
            'member' => (new WorkspaceMemberReferenceResource($membership))->resolve(),
            'records' => AvailabilityResource::collection($result['records'])->resolve(),
            'rules' => AvailabilityRuleResource::collection($result['rules'])->resolve(),
        ];

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'availability.synced',
            WorkspaceMembership::class,
            $membership->id,
            $before,
            $after
        );

        return response()->json([
            'data' => $after,
        ]);
    }
}
