<?php

namespace App\Application\Actions\Venues;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;

class DeleteVenue
{
    public function execute(Venue $venue): array
    {
        return DB::transaction(function () use ($venue): array {
            $dependencies = ['events_count' => $venue->events()->count()];

            if (array_sum($dependencies) > 0) {
                return ['deleted' => false, 'dependencies' => $dependencies, 'before' => null];
            }

            $before = $venue->toArray();
            $venue->delete();

            return ['deleted' => true, 'dependencies' => $dependencies, 'before' => $before];
        });
    }
}
