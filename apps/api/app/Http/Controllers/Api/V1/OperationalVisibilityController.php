<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Beo\UpdateOperationalVisibilityRequest;
use App\Services\OperationalVisibilityService;

class OperationalVisibilityController extends Controller
{
    public function show(OperationalVisibilityService $visibility)
    {
        $membership = app('currentMembership')->loadMissing('workspace');

        return response()->json([
            'data' => [
                'defaults' => $membership->workspace->operational_visibility_defaults ?? OperationalVisibilityService::DEFAULTS,
                'overrides' => $membership->operational_visibility_overrides ?? [],
                'resolved' => $visibility->settings($membership),
            ],
        ]);
    }

    public function update(
        UpdateOperationalVisibilityRequest $request,
        OperationalVisibilityService $visibility
    ) {
        $membership = app('currentMembership')->loadMissing('workspace');
        $settings = $request->validated('settings');

        if ($request->validated('scope') === 'workspace') {
            $this->authorize('update', $membership->workspace);
            $membership->workspace->forceFill([
                'operational_visibility_defaults' => array_replace(
                    OperationalVisibilityService::DEFAULTS,
                    $settings
                ),
            ])->save();
        } else {
            $membership->forceFill([
                'operational_visibility_overrides' => $settings,
            ])->save();
        }

        $membership->refresh()->load('workspace');

        return response()->json([
            'data' => [
                'defaults' => $membership->workspace->operational_visibility_defaults ?? OperationalVisibilityService::DEFAULTS,
                'overrides' => $membership->operational_visibility_overrides ?? [],
                'resolved' => $visibility->settings($membership),
            ],
        ]);
    }
}
