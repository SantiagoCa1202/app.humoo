<?php

namespace App\Application\Actions\Venues;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;

class CreateVenue
{
    public function execute(string $workspaceId, string $userId, array $attributes): Venue
    {
        return DB::transaction(fn (): Venue => Venue::query()->create([
            ...$attributes,
            'workspace_id' => $workspaceId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'status' => $attributes['status'] ?? 'active',
        ]));
    }
}
