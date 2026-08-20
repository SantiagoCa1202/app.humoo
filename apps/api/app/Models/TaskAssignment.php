<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignment extends BaseModel
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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
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
