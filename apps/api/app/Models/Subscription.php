<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends BaseModel
{
    protected $fillable = [
        'workspace_id',
        'plan_id',
        'provider',
        'provider_subscription_id',
        'status',
        'billing_interval',
        'currency',
        'starts_at',
        'current_period_start',
        'current_period_end',
        'trial_starts_at',
        'trial_ends_at',
        'cancel_at_period_end',
        'cancelled_at',
        'cancel_at',
        'ends_at',
        'grace_ends_at',
        'provider_synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'cancel_at_period_end' => 'boolean',
            'starts_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancel_at' => 'datetime',
            'ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'provider_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
