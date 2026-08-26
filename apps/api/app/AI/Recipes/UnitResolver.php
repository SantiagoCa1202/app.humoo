<?php

namespace App\AI\Recipes;

use App\Models\Unit;

class UnitResolver
{
    public function idFor(?string $key): ?string
    {
        return $key === null ? null : Unit::query()->where('key', $key)->value('id');
    }
}
