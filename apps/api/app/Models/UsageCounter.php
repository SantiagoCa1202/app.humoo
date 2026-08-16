<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageCounter extends BaseModel
{
    protected $fillable = [
        'workspace_id',
        'feature_id',
        'feature_key',
        'period_start',
        'period_end',
        'usage',
        'limit_value',
        'last_incremented_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'usage' => 'decimal:4',
            'limit_value' => 'decimal:4',
            'last_incremented_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }
}
