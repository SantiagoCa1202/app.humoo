<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PlanFeature extends Pivot
{
    protected $table = 'plan_features';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'decimal:4',
            'config' => 'array',
        ];
    }
}
