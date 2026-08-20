<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeVersionChange extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'before_value' => 'array',
            'after_value' => 'array',
            'affects_production' => 'boolean',
            'reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function fromVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'from_version_id');
    }

    public function toVersion(): BelongsTo
    {
        return $this->belongsTo(RecipeVersion::class, 'to_version_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
