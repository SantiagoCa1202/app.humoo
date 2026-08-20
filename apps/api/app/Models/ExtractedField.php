<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedField extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'corrected_value_json' => 'array',
            'page_number' => 'integer',
            'reviewed' => 'boolean',
            'reviewed_at' => 'datetime',
            'source_location' => 'array',
            'value_json' => 'array',
        ];
    }

    public function extractionRun(): BelongsTo
    {
        return $this->belongsTo(ExtractionRun::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
