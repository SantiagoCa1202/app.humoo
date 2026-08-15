<?php

namespace App\Application\Actions\Prep;

use App\Models\PrepItem;
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

            return PrepItem::query()
                ->whereKey($item->getKey())
                ->where('workspace_id', $item->workspace_id)
                ->first();
        });
    }
}
