<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeoVersionChange extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'affects_production' => 'boolean',
            'after_value' => 'array',
            'before_value' => 'array',
            'reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function beo(): BelongsTo
    {
        return $this->belongsTo(Beo::class);
    }

    public function fromVersion(): BelongsTo
    {
        return $this->belongsTo(BeoVersion::class, 'from_version_id');
    }

    public function toVersion(): BelongsTo
    {
        return $this->belongsTo(BeoVersion::class, 'to_version_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
