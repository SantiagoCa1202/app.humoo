<?php

namespace Tests\Unit\Unit;

use App\AI\Recipes\FractionNormalizer;
use App\AI\Recipes\RecipeInputIngestionPipeline;
use App\AI\Recipes\UnitNormalizer;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeInputIngestionPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranch_input_is_structured_and_keeps_the_salt_range_for_clarification(): void
    {
        $this->seed(UnitSeeder::class);
        $result = app(RecipeInputIngestionPipeline::class)->ingest([], <<<'RECIPE'
crea esta receta: Ranch casero – aprox. 1 galón

Mayonesa 8 cups
Buttermilk 4 cups
Sour cream 4 cups
Garlic powder 2 tbsp
Onion powder 2 tbsp
Dill seco 2 tbsp
Parsley seco 3 tbsp
Chives secos 2 tbsp
Black pepper 2 tsp
Sal 1½–2 tbsp
Jugo de limón ½ cup
Worcestershire sauce 2 tbsp

Preparación:
mezcla primero la mayonesa con el sour cream.
Agrega poco a poco el buttermilk hasta conseguir la consistencia deseada.
Incorpora todos los condimentos, limón y Worcestershire.
Refrigera mínimo 1–2 horas antes de servir.
RECIPE, 'es');

        $this->assertSame('clarification', $result['status']);
        $this->assertSame('Ranch casero', $result['draft']['name']);
        $this->assertSame(1.0, $result['draft']['yield']['quantity']);
        $this->assertSame('gal', $result['draft']['yield']['unit_key']);
        $this->assertCount(12, $result['draft']['ingredients']);
        $this->assertSame('cup', $result['draft']['ingredients'][0]['unit_key']);
        $this->assertSame(0.5, $result['draft']['ingredients'][9]['quantity_min']);
        $this->assertSame(2.0, $result['draft']['ingredients'][9]['quantity_max']);
        $this->assertCount(4, $result['draft']['steps']);
        $this->assertSame('user_provided', $result['draft']['source']);
        $this->assertSame('quantity_range', $result['issues'][0]['code']);
    }

    public function test_fraction_and_unit_aliases_are_deterministic(): void
    {
        $fractions = new FractionNormalizer();
        $units = new UnitNormalizer();

        $this->assertSame(0.5, $fractions->parse('½'));
        $this->assertSame(2.25, $fractions->parse('2¼'));
        $this->assertSame(1.5, $fractions->parse('1 1/2'));
        $this->assertSame(1.5, $fractions->parse('1-1/2'));
        $this->assertSame(['min' => 1.5, 'max' => 2.0], $fractions->parseRange('1½–2'));
        $this->assertSame(['min' => 1.0, 'max' => 2.0], $fractions->parseRange('1 to 2'));
        $this->assertSame('cup', $units->normalize('tazas'));
        $this->assertSame('tbsp', $units->normalize('cucharada'));
        $this->assertSame('tsp', $units->normalize('teaspoons'));
        $this->assertSame('gal', $units->normalize('galón'));
    }

    public function test_incomplete_recipe_never_invents_quantities_or_yield(): void
    {
        $this->seed(UnitSeeder::class);
        $result = app(RecipeInputIngestionPipeline::class)->ingest([], "crea esta receta:\nRanch\nMayonesa\nButtermilk", 'es');

        $this->assertSame('clarification', $result['status']);
        $this->assertSame('Ranch', $result['draft']['name']);
        $this->assertContains('missing_yield', array_column($result['issues'], 'code'));
        $this->assertContains('missing_ingredients', array_column($result['issues'], 'code'));
    }

    public function test_preparation_on_the_heading_line_is_extracted_as_steps(): void
    {
        $this->seed(UnitSeeder::class);
        $result = app(RecipeInputIngestionPipeline::class)->ingest([], "crea esta receta: Salsa\nRinde 1 galón\nMayonesa 8 cups\nPreparación: mezcla la mayonesa. Refrigera antes de servir.", 'es');

        $this->assertSame('ready', $result['status']);
        $this->assertCount(2, $result['draft']['steps']);
    }
}
