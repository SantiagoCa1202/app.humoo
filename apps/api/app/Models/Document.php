<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends WorkspaceModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processing_error' => 'string',
            'scanned_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
    }

    public function beoVersions(): HasMany
    {
        return $this->hasMany(BeoVersion::class);
    }

    public function beoImportBatches(): HasMany
    {
        return $this->hasMany(BeoImportBatch::class);
    }

    public function latestBeoVersion(): HasOne
    {
        return $this->hasOne(BeoVersion::class)->latestOfMany('version');
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function latestExtractionRun(): HasOne
    {
        return $this->hasOne(ExtractionRun::class)->latestOfMany();
    }

    public function processingJobs(): HasMany
    {
        return $this->hasMany(DocumentProcessingJob::class);
    }
}
