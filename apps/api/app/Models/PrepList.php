<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrepList extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'blocked_items' => 'integer',
            'completed_at' => 'datetime',
            'completed_items' => 'integer',
            'current_version' => 'integer',
            'metadata' => 'array',
            'production_ends_at' => 'datetime',
            'production_starts_at' => 'datetime',
            'total_items' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PrepListVersion::class);
    }

    public function currentVersionRecord(): HasOne
    {
        return $this->hasOne(PrepListVersion::class)
            ->whereColumn('prep_list_versions.version', 'prep_lists.current_version');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
