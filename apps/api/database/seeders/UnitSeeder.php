<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['key' => 'g', 'name' => 'Gram', 'symbol' => 'g', 'dimension' => 'weight', 'base_factor' => 1, 'decimal_places' => 2],
            ['key' => 'kg', 'name' => 'Kilogram', 'symbol' => 'kg', 'dimension' => 'weight', 'base_factor' => 1000, 'decimal_places' => 3],
            ['key' => 'oz', 'name' => 'Ounce', 'symbol' => 'oz', 'dimension' => 'weight', 'base_factor' => 28.349523125, 'decimal_places' => 2],
            ['key' => 'lb', 'name' => 'Pound', 'symbol' => 'lb', 'dimension' => 'weight', 'base_factor' => 453.59237, 'decimal_places' => 2],
            ['key' => 'ml', 'name' => 'Milliliter', 'symbol' => 'ml', 'dimension' => 'volume', 'base_factor' => 1, 'decimal_places' => 2],
            ['key' => 'l', 'name' => 'Liter', 'symbol' => 'l', 'dimension' => 'volume', 'base_factor' => 1000, 'decimal_places' => 2],
            ['key' => 'fl_oz', 'name' => 'Fluid ounce', 'symbol' => 'fl oz', 'dimension' => 'volume', 'base_factor' => 29.5735295625, 'decimal_places' => 2],
            ['key' => 'tsp', 'name' => 'Teaspoon', 'symbol' => 'tsp', 'dimension' => 'volume', 'base_factor' => 4.92892159375, 'decimal_places' => 2],
            ['key' => 'tbsp', 'name' => 'Tablespoon', 'symbol' => 'tbsp', 'dimension' => 'volume', 'base_factor' => 14.78676478125, 'decimal_places' => 2],
            ['key' => 'cup', 'name' => 'Cup', 'symbol' => 'cup', 'dimension' => 'volume', 'base_factor' => 236.5882365, 'decimal_places' => 2],
            ['key' => 'gal', 'name' => 'Gallon', 'symbol' => 'gal', 'dimension' => 'volume', 'base_factor' => 3785.411784, 'decimal_places' => 2],
            ['key' => 'each', 'name' => 'Each', 'symbol' => 'ea', 'dimension' => 'count', 'base_factor' => null, 'decimal_places' => 0],
            ['key' => 'piece', 'name' => 'Piece', 'symbol' => 'pc', 'dimension' => 'count', 'base_factor' => null, 'decimal_places' => 0],
            ['key' => 'portion', 'name' => 'Portion', 'symbol' => 'portion', 'dimension' => 'portion', 'base_factor' => null, 'decimal_places' => 0],
        ] as $unit) {
            Unit::query()->updateOrCreate(
                ['key' => $unit['key']],
                [
                    ...$unit,
                    'active' => true,
                    'system' => true,
                ]
            );
        }
    }
}
