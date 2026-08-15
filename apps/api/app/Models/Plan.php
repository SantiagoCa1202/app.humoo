<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends BaseModel
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'sort_order',
        'price_monthly',
        'price_yearly',
        'currency',
        'trial_days',
        'active',
        'public',
        'retired_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'trial_days' => 'integer',
            'active' => 'boolean',
            'public' => 'boolean',
            'retired_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
