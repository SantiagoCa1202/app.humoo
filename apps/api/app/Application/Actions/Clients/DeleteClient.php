<?php

namespace App\Application\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class DeleteClient
{
    public function execute(Client $client): array
    {
        return DB::transaction(function () use ($client): array {
            $dependencies = [
                'contacts_count' => $client->contacts()->count(),
                'events_count' => $client->events()->count(),
            ];

            if (array_sum($dependencies) > 0) {
                return ['deleted' => false, 'dependencies' => $dependencies, 'before' => null];
            }

            $before = $client->toArray();
            $client->delete();

            return ['deleted' => true, 'dependencies' => $dependencies, 'before' => $before];
        });
    }
}
