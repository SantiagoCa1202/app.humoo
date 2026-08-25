<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentPattern extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'ambiguity_rate' => 'decimal:6',
            'confidence' => 'decimal:6',
            'examples' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'occurrences' => 'integer',
            'pattern_json' => 'array',
            'slot_schema' => 'array',
            'successful_executions' => 'integer',
            'failed_executions' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
