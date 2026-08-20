<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMenu extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'assigned_at' => 'datetime',
            'guest_count' => 'integer',
            'snapshot_json' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function menuVersion(): BelongsTo
    {
        return $this->belongsTo(MenuVersion::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
