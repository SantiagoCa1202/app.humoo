<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Http\Requests\Workspace\InviteMemberRequest;
use App\Services\AuditLogger;
use App\Services\InvitationService;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $request->user()->hasWorkspacePermission(
                $workspace->id,
                'members.view'
            ),
            403,
            'You do not have permission to view invitations.'
        );

        $invitations = $workspace->invitations()
            ->with(['role', 'invitedBy'])
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($invitation): array => [
                ...$invitation->toArray(),
                'is_expired' => $invitation->expires_at->isPast(),
            ])
            ->values();

        return response()->json([
            'data' => $invitations,
        ]);
    }

    public function store(
        InviteMemberRequest $request,
        InvitationService $invitationService,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        [$invitation, $plainToken] = $invitationService->create(
            $workspace,
            $request->user(),
            $request->validated('email'),
            $request->validated('role_id'),
        );

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'invitation.created',
            $invitation::class,
            $invitation->id,
            null,
            $invitation->toArray()
        );

        $payload = [
            'data' => $invitation,
        ];

        if (app()->isLocal() || app()->environment('testing')) {
            $payload['meta'] = [
                'invitation_token_preview' => $plainToken,
                'accept_url_preview' => $invitationService->buildAcceptUrl($plainToken),
            ];
        }

        return response()->json($payload, 201);
    }

    public function show(
        string $token,
        InvitationService $invitationService
    )
    {
        $invitation = $invitationService->preview($token);

        return response()->json([
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at,
                'workspace' => $invitation->workspace,
                'role' => $invitation->role,
                'invited_by' => $invitation->invitedBy,
            ],
        ]);
    }

    public function accept(
        AcceptInvitationRequest $request,
        InvitationService $invitationService
    )
    {
        $result = $invitationService->acceptForUser(
            $request->user(),
            $request->validated('token')
        );

        return response()->json([
            'data' => [
                'invitation' => $result['invitation'],
                'membership' => $result['membership'],
                'permissions' => $result['membership']->role?->permissions
                    ? $result['membership']->role->permissions
                        ->pluck('key')
                        ->values()
                        ->all()
                    : [],
            ],
        ]);
    }

    public function destroy(
        Request $request,
        \App\Models\Invitation $invitation,
        AuditLogger $auditLogger
    )
    {
        $workspace = app('currentWorkspace');

        abort_unless(
            $request->user()->hasWorkspacePermission($workspace->id, 'members.invite')
            || $request->user()->hasWorkspacePermission($workspace->id, 'members.manage'),
            403,
            'You do not have permission to manage invitations.'
        );

        abort_unless(
            $invitation->workspace_id === $workspace->id,
            404,
        );

        abort_if(
            $invitation->accepted_at !== null,
            422,
            'Accepted invitations cannot be cancelled.',
        );

        $before = $invitation->load([
            'role',
            'invitedBy',
            'workspace',
        ])->toArray();

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()->id,
            'invitation.cancelled',
            $invitation::class,
            $invitation->id,
            $before,
            null
        );

        $invitation->delete();

        return response()->noContent();
    }
}
