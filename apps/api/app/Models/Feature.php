<?php

namespace App\Models;

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
}
