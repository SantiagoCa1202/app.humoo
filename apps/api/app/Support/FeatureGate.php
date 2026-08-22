<?php

namespace App\Support;

use App\Models\Workspace;
use App\Services\EntitlementService;

class FeatureGate
{
    public function __construct(
        private EntitlementService $entitlements
    ) {
    }

    public function allows(
        Workspace $workspace,
        string $featureKey,
        float $requested = 1
    ): bool {
        return $this->entitlements->allows($workspace, $featureKey, $requested);
    }
}
