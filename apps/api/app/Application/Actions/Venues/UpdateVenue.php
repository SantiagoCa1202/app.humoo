<?php

namespace App\Application\Actions\Venues;

use App\Models\Venue;

class UpdateVenue
{
    public function execute(Venue $venue, string $userId, array $attributes): Venue
    {
        $venue->forceFill([
            ...$attributes,
            'updated_by' => $userId,
        ])->save();

        return $venue->fresh();
    }
}
