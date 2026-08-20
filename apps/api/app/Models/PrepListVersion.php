<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrepListVersion extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'event_starts_at_snapshot' => 'datetime',
            'generation_metadata' => 'array',
            'guest_count_snapshot' => 'integer',
            'locked' => 'boolean',
            'locked_at' => 'datetime',
            'revision' => 'integer',
            'version' => 'integer',
        ];
    }

    public function prepList(): BelongsTo
    {
        return $this->belongsTo(PrepList::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(PrepSection::class)
            ->orderBy('position');
    }

    public function menuVersion(): BelongsTo
    {
        return $this->belongsTo(MenuVersion::class);
    }

    public function beoVersion(): BelongsTo
    {
        return $this->belongsTo(BeoVersion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
