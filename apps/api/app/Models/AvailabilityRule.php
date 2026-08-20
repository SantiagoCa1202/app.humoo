<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'available' => 'boolean',
            'day_of_week' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(
            WorkspaceMembership::class,
            'membership_id'
        );
    }
}
