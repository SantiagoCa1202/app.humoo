<?php

namespace App\AI\Recipes;

use App\Models\Unit;

class UnitResolver
{
    public function idFor(?string $key): ?string
    {
        $key = (new UnitRegistry())->normalize($key);

        return $key === null ? null : Unit::query()->where('key', $key)->value('id');
    }
}
