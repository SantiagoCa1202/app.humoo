<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOrderReference extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['source_reference' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sourceBeo(): BelongsTo
    {
        return $this->belongsTo(Beo::class, 'source_beo_id');
    }

    public function sourceVersion(): BelongsTo
    {
        return $this->belongsTo(BeoVersion::class, 'source_beo_version_id');
    }

    public function sourceEventFunction(): BelongsTo
    {
        return $this->belongsTo(EventFunction::class, 'source_event_function_id');
    }

    public function targetBeo(): BelongsTo
    {
        return $this->belongsTo(Beo::class, 'target_beo_id');
    }
}
