<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuVersionChange extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'after_value' => 'array',
            'affects_production' => 'boolean',
            'before_value' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function fromVersion(): BelongsTo
    {
        return $this->belongsTo(MenuVersion::class, 'from_version_id');
    }

    public function toVersion(): BelongsTo
    {
        return $this->belongsTo(MenuVersion::class, 'to_version_id');
    }
}
