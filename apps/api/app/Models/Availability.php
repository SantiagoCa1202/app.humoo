<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends WorkspaceModel
{
    protected $table = 'availability';

    protected function casts(): array
    {
        return [
            'available' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
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
