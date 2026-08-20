<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentProcessingJob extends WorkspaceModel
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'completed_at' => 'datetime',
            'result_json' => 'array',
            'started_at' => 'datetime',
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
}
