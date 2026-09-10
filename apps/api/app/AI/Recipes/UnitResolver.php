<?php

namespace App\AI\Recipes;

use App\Models\Unit;

class UnitResolver
{
    public function idFor(?string $key): ?string
    {
        $key = is_string($key) ? trim($key) : null;
        if ($key === '' || ! in_array($key, (new UnitRegistry())->keys(), true)) {
            return null;
        }

        return Unit::query()->where('key', $key)->value('id');
    }
}
