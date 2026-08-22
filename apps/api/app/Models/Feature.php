<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends BaseModel
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'type',
        'module',
        'unit',
        'reset_period',
        'active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(
            Plan::class,
            'plan_features'
        )->withPivot([
            'enabled',
            'limit_value',
            'config',
        ])->withTimestamps();
    }
}
