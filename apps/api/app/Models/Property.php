<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(BeoImportBatch::class);
    }

    public function beos(): HasMany
    {
        return $this->hasMany(Beo::class);
    }
}
