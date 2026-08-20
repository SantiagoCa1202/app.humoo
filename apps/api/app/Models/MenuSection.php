<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuSection extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'position' => 'integer',
            'service_at' => 'datetime',
        ];
    }

    public function menuVersion(): BelongsTo
    {
        return $this->belongsTo(MenuVersion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)
            ->orderBy('position');
    }
}
