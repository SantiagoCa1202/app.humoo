<?php

namespace App\AI\Recipes;

use Illuminate\Support\Str;

class UnitNormalizer
{
    private const ALIASES = [
        'cup' => ['cup', 'cups', 'c', 'taza', 'tazas'],
        'tbsp' => ['tbsp', 'tablespoon', 'tablespoons', 'cucharada', 'cucharadas', 'cda', 'cdas'],
        'tsp' => ['tsp', 'teaspoon', 'teaspoons', 'cucharadita', 'cucharaditas', 'cdta', 'cdtas'],
        'gal' => ['gal', 'gallon', 'gallons', 'galon', 'galón', 'galones'],
        'lb' => ['lb', 'lbs', 'pound', 'pounds', 'libra', 'libras'],
        'oz' => ['oz', 'ounce', 'ounces', 'onza', 'onzas'],
        'g' => ['g', 'gram', 'grams', 'gramo', 'gramos'],
        'kg' => ['kg', 'kilogram', 'kilograms', 'kilogramo', 'kilogramos', 'kilo', 'kilos'],
        'ml' => ['ml', 'milliliter', 'milliliters', 'mililitro', 'mililitros'],
        'l' => ['l', 'liter', 'liters', 'litro', 'litros'],
        'fl_oz' => ['fl oz', 'fluid ounce', 'fluid ounces', 'onza liquida', 'onza líquida', 'onzas liquidas', 'onzas líquidas'],
        'each' => ['each', 'ea', 'unidad', 'unidades'],
        'piece' => ['piece', 'pieces', 'pc', 'pieza', 'piezas'],
        'portion' => ['portion', 'portions', 'porcion', 'porción', 'porciones', 'serving', 'servings'],
    ];

    public function normalize(?string $unit): ?string
    {
        $unit = Str::lower(trim(Str::ascii((string) $unit)));
        $unit = preg_replace('/\s+/', ' ', $unit) ?? $unit;
        if ($unit === '') {
            return null;
        }

        foreach (self::ALIASES as $key => $aliases) {
            if (in_array($unit, $aliases, true)) {
                return $key;
            }
        }

        return null;
    }

    public function aliases(): array
    {
        return self::ALIASES;
    }
}
