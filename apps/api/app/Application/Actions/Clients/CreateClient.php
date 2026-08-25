<?php

namespace App\Application\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class CreateClient
{
    public function execute(string $workspaceId, string $userId, array $attributes): Client
    {
        return DB::transaction(fn (): Client => Client::query()->create([
            ...$attributes,
            'workspace_id' => $workspaceId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'status' => $attributes['status'] ?? 'active',
        ]));
    }
}
