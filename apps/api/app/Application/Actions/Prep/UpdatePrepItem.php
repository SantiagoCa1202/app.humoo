<?php

namespace App\Application\Actions\Prep;

use App\Models\PrepItem;
use App\Models\PrepItemAssignment;
use Illuminate\Support\Facades\DB;

class UpdatePrepItem
{
    public function execute(
        PrepItem $item,
        int $expectedVersion,
        array $attributes,
        ?string $userId = null
    ): ?PrepItem {
        return DB::transaction(function () use (
            $item,
            $expectedVersion,
            $attributes,
            $userId
        ): ?PrepItem {
            $hasAssignmentUpdate = array_key_exists('assignment_membership_id', $attributes);
            $assignmentMembershipId = $attributes['assignment_membership_id'] ?? null;
            unset($attributes['assignment_membership_id']);

            if (($attributes['status'] ?? null) === 'done' && !array_key_exists('completed_at', $attributes)) {
                $attributes['completed_at'] = now();
                $attributes['completed_by'] = $userId;
            }

            if (($attributes['status'] ?? null) === 'in_progress' && !array_key_exists('started_at', $attributes)) {
                $attributes['started_at'] = $item->started_at ?? now();
            }

            $updated = PrepItem::query()
                ->whereKey($item->getKey())
                ->where('workspace_id', $item->workspace_id)
                ->where('version', $expectedVersion)
                ->update([
                    ...$attributes,
                    'updated_by' => $userId,
                    'version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                return null;
            }

            $freshItem = PrepItem::query()
                ->whereKey($item->getKey())
                ->where('workspace_id', $item->workspace_id)
                ->first();

            if (!$freshItem) {
                return null;
            }

            if ($hasAssignmentUpdate) {
                $this->syncPrimaryAssignment(
                    $freshItem,
                    $assignmentMembershipId,
                    $userId
                );
            }

            return PrepItem::query()
                ->whereKey($item->getKey())
                ->where('workspace_id', $item->workspace_id)
                ->with([
                    'assignments.membership.role',
                    'assignments.membership.teams',
                    'assignments.membership.user',
                    'assignments.assignedBy',
                    'actualUnit',
                    'completedBy',
                    'createdBy',
                    'recipe',
                    'recipeVersion',
                    'unit',
                    'updatedBy',
                    'yieldUnit',
                ])
                ->first();
        });
    }

    private function syncPrimaryAssignment(
        PrepItem $item,
        ?string $membershipId,
        ?string $userId
    ): void {
        if (!$membershipId) {
            PrepItemAssignment::query()
                ->where('workspace_id', $item->workspace_id)
                ->where('prep_item_id', $item->id)
                ->delete();

            return;
        }

        PrepItemAssignment::query()
            ->where('workspace_id', $item->workspace_id)
            ->where('prep_item_id', $item->id)
            ->where('membership_id', '!=', $membershipId)
            ->delete();

        PrepItemAssignment::query()->updateOrCreate(
            [
                'prep_item_id' => $item->id,
                'membership_id' => $membershipId,
            ],
            [
                'workspace_id' => $item->workspace_id,
                'assigned_at' => now(),
                'assigned_by' => $userId,
                'is_primary' => true,
                'status' => 'assigned',
            ]
        );
    }
}
