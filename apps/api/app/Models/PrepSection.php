<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrepSection extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'position' => 'integer',
            'production_date' => 'date',
            'starts_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(
            PrepListVersion::class,
            'prep_list_version_id'
        );
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrepItem::class)
            ->orderBy('position');
    }
}
