<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrepList extends WorkspaceModel
{
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PrepListVersion::class);
    }
}
