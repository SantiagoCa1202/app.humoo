<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractionRun extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
            'latency_ms' => 'integer',
            'last_heartbeat_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'metadata_json' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'usage_json' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(BeoImportBatch::class, 'beo_import_batch_id');
    }

    public function beoVersion(): BelongsTo
    {
        return $this->belongsTo(BeoVersion::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ExtractedField::class);
    }
}
