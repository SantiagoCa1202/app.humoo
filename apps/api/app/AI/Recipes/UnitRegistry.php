<?php

namespace App\AI\Recipes;

use Illuminate\Support\Str;

/** Canonical unit vocabulary used by schemas, normalization and validation. */
final class UnitRegistry
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

    /** @return array<string, array<int, string>> */
    public function aliases(): array
    {
        return self::ALIASES;
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys(self::ALIASES);
    }

    public function normalize(?string $unit): ?string
    {
        $normalized = Str::lower(trim(Str::ascii((string) $unit)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        if ($normalized === '') {
            return null;
        }
        if (array_key_exists($normalized, self::ALIASES)) {
            return $normalized;
        }
        foreach (self::ALIASES as $key => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $key;
            }
        }

        return null;
    }
}
