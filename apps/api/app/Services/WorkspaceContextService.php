<?php

namespace App\Services;

use App\Models\WorkspaceMembership;

class WorkspaceContextService
{
    public function __construct(
        private EntitlementService $entitlements
    ) {
    }

    public function buildForMembership(WorkspaceMembership $membership): array
    {
        $membership->loadMissing([
            'workspace',
            'role.permissions',
        ]);

        $workspace = $membership->workspace;
        $permissions = $membership->role?->permissions
            ? $membership->role->permissions
                ->pluck('key')
                ->values()
                ->all()
            : [];

        $billing = $this->entitlements->snapshot($workspace);

        return [
            'workspace' => $workspace,
            'membership' => $membership,
            'permissions' => $permissions,
            'plan' => $billing['plan'],
            'subscription' => $billing['subscription'],
            'entitlements' => $billing['entitlements'],
        ];
    }
}
