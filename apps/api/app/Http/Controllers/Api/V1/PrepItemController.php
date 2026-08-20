<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Prep\UpdatePrepItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prep\UpdatePrepItemRequest;
use App\Http\Resources\PrepItemResource;
use App\Models\PrepItem;
use App\Services\AuditLogger;

class PrepItemController extends Controller
{
    public function update(
        UpdatePrepItemRequest $request,
        UpdatePrepItem $action,
        AuditLogger $auditLogger,
        PrepItem $item
    ) {
        $workspace = app('currentWorkspace');

        abort_unless(
            $item->workspace_id === $workspace->id,
            404
        );

        $this->authorize('update', $item);

        $before = $item->toArray();

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
                'data' => (new PrepItemResource(
                    PrepItem::query()
                    ->whereKey($item->getKey())
                    ->where('workspace_id', $workspace->id)
                    ->with($this->itemRelations())
                    ->first()
                ))->resolve(),
            ], 409);
        }

        $auditLogger->logWorkspaceAction(
            $request,
            $workspace->id,
            $request->user()?->id,
            'prep_item.updated',
            PrepItem::class,
            $updated->id,
            $before,
            $updated->toArray()
        );

        return response()->json([
            'data' => new PrepItemResource($updated),
        ]);
    }

    private function itemRelations(): array
    {
        return [
            'assignments.assignedBy',
            'assignments.membership.role',
            'assignments.membership.teams',
            'assignments.membership.user',
            'actualUnit',
            'completedBy',
            'createdBy',
            'recipe',
            'recipeVersion',
            'unit',
            'updatedBy',
            'yieldUnit',
        ];
    }
}
