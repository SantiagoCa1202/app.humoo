<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Prep\UpdatePrepItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prep\UpdatePrepItemRequest;
use App\Models\PrepItem;

class PrepItemController extends Controller
{
    public function update(
        UpdatePrepItemRequest $request,
        UpdatePrepItem $action,
        PrepItem $item
    ) {
        $workspace = app('currentWorkspace');

        abort_unless(
            $item->workspace_id === $workspace->id,
            404
        );

        $this->authorize('update', $item);

        $updated = $action->execute(
            $item,
            $request->integer('version'),
            $request->safe()->except('version'),
            $request->user()?->id,
        );

        if (!$updated) {
            return response()->json([
                'message' => 'Resource conflict.',
                'code' => 'VERSION_CONFLICT',
                'data' => PrepItem::query()
                    ->whereKey($item->getKey())
                    ->where('workspace_id', $workspace->id)
                    ->first(),
            ], 409);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }
}
