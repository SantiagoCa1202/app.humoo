<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepItemAssignment extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_primary' => 'boolean',
        ];
    }

    public function prepItem(): BelongsTo
    {
        return $this->belongsTo(PrepItem::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMembership::class, 'membership_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
