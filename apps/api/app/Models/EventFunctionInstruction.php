<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFunctionInstruction extends WorkspaceModel
{
    protected function casts(): array
    {
        return ['source_reference' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function eventFunction(): BelongsTo
    {
        return $this->belongsTo(EventFunction::class);
    }
}
