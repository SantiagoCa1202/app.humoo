<?php

namespace Tests\Unit\Unit;

use App\AI\Menu\MenuDraftParser;
use Tests\TestCase;

class MenuDraftParserTest extends TestCase
{
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
}
