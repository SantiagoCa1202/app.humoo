<?php

namespace App\Services;

use App\Models\Workspace;
use App\Models\WorkspaceMembership;
use Closure;
use LogicException;

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

    /**
     * Bind the same tenant context supplied by ResolveWorkspace for one
     * non-HTTP operation. Queue workers are long-lived, so the previous
     * bindings must always be restored after the callback completes.
     *
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function within(Workspace $workspace, WorkspaceMembership $membership, Closure $callback): mixed
    {
        if ((string) $membership->workspace_id !== (string) $workspace->id) {
            throw new LogicException('The workspace membership does not belong to the execution workspace.');
        }

        $container = app();
        $previous = [];
        foreach (['currentWorkspace', 'currentMembership'] as $abstract) {
            $previous[$abstract] = $container->bound($abstract)
                ? ['bound' => true, 'value' => $container->make($abstract)]
                : ['bound' => false, 'value' => null];
        }

        $container->instance('currentWorkspace', $workspace);
        $container->instance('currentMembership', $membership);

        try {
            return $callback();
        } finally {
            foreach ($previous as $abstract => $binding) {
                if ($binding['bound']) {
                    $container->instance($abstract, $binding['value']);
                } else {
                    $container->forgetInstance($abstract);
                }
            }
        }
    }
}
