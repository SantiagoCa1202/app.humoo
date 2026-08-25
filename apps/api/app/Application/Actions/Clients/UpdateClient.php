<?php

namespace App\Application\Actions\Clients;

use App\Models\Client;

class UpdateClient
{
    public function execute(Client $client, string $userId, array $attributes): Client
    {
        $client->forceFill([
            ...$attributes,
            'updated_by' => $userId,
        ])->save();

        return $client->fresh();
    }
}
