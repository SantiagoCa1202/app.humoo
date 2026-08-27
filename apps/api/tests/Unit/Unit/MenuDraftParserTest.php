<?php

namespace Tests\Unit\Unit;

use App\AI\Menu\MenuDraftParser;
use Tests\TestCase;

class MenuDraftParserTest extends TestCase
{
    public function test_inline_menu_creation_preserves_the_name_and_recipe_reference_text(): void
    {
        $draft = (new MenuDraftParser)->parse('crea un menu Dinner Buffet y añade Ranch casero');

        $this->assertSame('Dinner Buffet', $draft['name']);
        $this->assertSame('Ranch casero', $draft['sections'][0]['items'][0]['name']);
    }

    public function test_it_builds_a_generic_menu_draft_with_exclusions_and_guest_count(): void
    {
        $draft = (new MenuDraftParser)->parse(<<<'MENU'
Crea un menú con lo siguiente:
The Uptown:
Assorted Danish, Croissants, Biscuits
Strawberry + Apricot Jam
Scrambled Eggs
Applewood Smoked Thick Cut Bacon
Rosemary Roasted Potatoes
Fresh Sliced Fruit
Starbucks Regular & Decaf Coffee
Tea, Orange Juice
MENU);

        $items = collect($draft['sections'])->flatMap(fn (array $section) => $section['items']);

        $this->assertSame('The Uptown', $draft['name']);
        $this->assertCount(11, $items);
        $this->assertSame([], $draft['excluded_items']);
        $this->assertSame('Assorted Danish', $items->first()['name']);
    }

    public function test_it_accepts_menu_name_after_create_this_menu_prefix(): void
    {
        $draft = (new MenuDraftParser)->parse(<<<'MENU'
crea este menu: The Uptown:
Assorted Danish, Croissants, Biscuits
Strawberry + Apricot Jam
Scrambled Eggs
Applewood Smoked Thick Cut Bacon
Rosemary Roasted Potatoes
Fresh Sliced Fruit
MENU);

        $this->assertSame('The Uptown', $draft['name']);
        $this->assertCount(8, collect($draft['sections'])->flatMap(fn (array $section) => $section['items']));
    }

    public function test_it_groups_hot_and_cold_items_without_inventing_preparation_data(): void
    {
        $draft = (new MenuDraftParser)->parse(<<<'MENU'
Crea un menú llamado Breakfast para 50 personas, separa la comida fría y caliente y omite Tea, Orange Juice y Starbucks Coffee.
The Uptown:
Assorted Danish, Scrambled Eggs, Fresh Sliced Fruit, Starbucks Regular & Decaf Coffee, Tea, Orange Juice
MENU);

        $items = collect($draft['sections'])->flatMap(fn (array $section) => $section['items']);

        $this->assertSame('The Uptown', $draft['name']);
        $this->assertSame(50, $draft['requested_guest_count']);
        $this->assertNotEmpty($draft['excluded_items']);
        $this->assertFalse($items->contains(fn (array $item): bool => str_contains($item['name'], 'Tea')));
        $this->assertFalse($items->contains(fn (array $item): bool => str_contains($item['name'], 'Starbucks')));
        $this->assertNotEmpty(array_filter($draft['sections'], fn (array $section): bool => $section['type'] === 'hot'));
        $this->assertArrayNotHasKey('quantity', $items->first());
    }

    public function test_it_extracts_explicit_per_guest_quantity_and_unit(): void
    {
        $draft = (new MenuDraftParser)->parse("The Uptown:\nEggs .5lb/person, Bacon 1 lb per guest");
        $items = collect($draft['sections'])->flatMap(fn (array $section) => $section['items']);

        $this->assertSame('Eggs', $items[0]['name']);
        $this->assertSame(0.5, $items[0]['quantity_per_guest']);
        $this->assertSame('lb', $items[0]['serving_unit']);
        $this->assertSame(1.0, $items[1]['quantity_per_guest']);
    }
}
