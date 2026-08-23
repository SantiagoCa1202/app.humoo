<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BeoVersion extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'snapshot_json' => 'array',
            'version' => 'integer',
            'revision_number' => 'integer',
            'date_printed' => 'datetime',
            'source_pages' => 'array',
            'source_metadata' => 'array',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(BeoVersionChange::class, 'to_version_id');
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function functions(): HasMany
    {
        return $this->hasMany(EventFunction::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(EventOrderReference::class, 'source_beo_version_id');
    }
}
