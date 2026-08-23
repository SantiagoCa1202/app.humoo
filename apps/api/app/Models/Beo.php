<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Beo extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'current_version' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(BeoImportBatch::class, 'import_batch_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(BeoVersion::class)->latestOfMany('version');
    }

    public function references(): HasMany
    {
        return $this->hasMany(EventOrderReference::class, 'source_beo_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BeoVersion::class);
    }
}
