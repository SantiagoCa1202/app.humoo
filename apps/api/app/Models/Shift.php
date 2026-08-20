<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends WorkspaceModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'clocked_in_at' => 'datetime',
            'clocked_out_at' => 'datetime',
            'ends_at' => 'datetime',
            'starts_at' => 'datetime',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ShiftConflict::class);
    }
}
