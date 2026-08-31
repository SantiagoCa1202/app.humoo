<?php

namespace Tests\Feature\Feature;

use App\AI\EntityResolution\MenuEntityResolver;
use App\Application\Actions\Menus\CreateMenu;
use App\Application\Actions\Menus\DeleteMenu;
use App\Application\Actions\Menus\DuplicateMenu;
use App\Application\Actions\Menus\UpdateMenuFromChat;
use App\Models\Event;
use App\Models\EventMenu;
use App\Models\Menu;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuChatCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_item_can_move_to_another_section_using_the_existing_version_action(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $menu = app(CreateMenu::class)->execute($workspace->id, $user->id, [
            'name' => 'Down South Boulevard',
            'status' => 'active',
            'sections' => [
                [
                    'name' => 'Cold Food',
                    'items' => [['name' => 'Tortilla Chips']],
                ],
                [
                    'name' => 'Hot Food',
                    'items' => [['name' => 'Scrambled Eggs']],
                ],
            ],
        ]);
        $menu = Menu::query()
            ->whereKey($menu->id)
            ->where('workspace_id', $workspace->id)
            ->with([
                'currentVersionRecord.sections.items',
            ])
            ->firstOrFail();
        $item = $menu->currentVersionRecord->sections->firstWhere('name', 'Cold Food')->items->first();
        $target = $menu->currentVersionRecord->sections->firstWhere('name', 'Hot Food');

        $updated = app(UpdateMenuFromChat::class)->moveItem(
            $menu,
            $workspace->id,
            $user->id,
            $item->id,
            $target->id
        );

        $updated = $updated->fresh('currentVersionRecord.sections.items');
        $this->assertSame(2, $updated->current_version);
        $this->assertSame(
            'Hot Food',
            $updated->currentVersionRecord->sections
                ->firstWhere('name', 'Hot Food')
                ->items
                ->firstWhere('name', 'Tortilla Chips')
                ->menuSection
                ->name
        );
    }

    public function test_menu_item_can_be_reordered_before_another_item_without_rewriting_menu_content(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $menu = app(CreateMenu::class)->execute($workspace->id, $user->id, [
            'name' => 'Italian Dinner',
            'status' => 'draft',
            'sections' => [[
                'name' => 'Entrées',
                'items' => [['name' => 'Chicken Alfredo'], ['name' => 'Lasagna']],
            ]],
        ])->fresh('currentVersionRecord.sections.items');
        $items = $menu->currentVersionRecord->sections->first()->items->keyBy('name');

        $updated = app(UpdateMenuFromChat::class)->reorderItem(
            $menu,
            $workspace->id,
            $user->id,
            $items['Lasagna']->id,
            $items['Chicken Alfredo']->id,
        )->fresh('currentVersionRecord.sections.items');

        $this->assertSame(
            ['Lasagna', 'Chicken Alfredo'],
            $updated->currentVersionRecord->sections->first()->items->pluck('name')->all()
        );
        $this->assertSame(2, $updated->current_version);
    }

    public function test_multiple_menu_item_changes_are_saved_as_one_menu_version(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $menu = app(CreateMenu::class)->execute($workspace->id, $user->id, [
            'name' => 'Southern Brunch',
            'status' => 'draft',
            'sections' => [[
                'name' => 'Breakfast',
                'items' => [['name' => 'Bacon'], ['name' => 'Fruit Salad']],
            ]],
        ])->fresh('currentVersionRecord.sections.items');
        $items = $menu->currentVersionRecord->sections->first()->items->keyBy('name');

        $updated = app(UpdateMenuFromChat::class)->updateItems($menu, $workspace->id, $user->id, [
            ['item_id' => $items['Bacon']->id, 'notes' => 'Serve hot.'],
            ['item_id' => $items['Fruit Salad']->id, 'optional' => true],
        ])->fresh('currentVersionRecord.sections.items');

        $this->assertSame(2, $updated->current_version);
        $result = $updated->currentVersionRecord->sections->first()->items->keyBy('name');
        $this->assertSame('Serve hot.', $result['Bacon']->notes);
        $this->assertTrue($result['Fruit Salad']->optional);
    }

    public function test_menu_resolution_is_workspace_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $menu = Menu::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Context Menu',
            'current_version' => 0,
            'status' => 'draft',
        ]);

        $resolver = app(MenuEntityResolver::class);
        $resolved = $resolver->resolveMenu($workspace->id, [], (string) $menu->id);
        $wrongWorkspace = $resolver->resolveMenu('01wrongworkspace000000000000', [], (string) $menu->id);

        $this->assertSame('resolved', $resolved['status']);
        $this->assertSame('missing', $wrongWorkspace['status']);
    }

    public function test_menu_creation_keeps_named_empty_sections_without_inventing_items(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();

        $menu = app(CreateMenu::class)->execute($workspace->id, $user->id, [
            'name' => 'Southern Brunch',
            'status' => 'draft',
            'sections' => [
                ['name' => 'Breakfast', 'items' => []],
                ['name' => 'Hot Food', 'items' => []],
                ['name' => 'Desserts', 'items' => []],
            ],
        ])->fresh('currentVersionRecord.sections.items');

        $this->assertSame(['Breakfast', 'Hot Food', 'Desserts'], $menu->currentVersionRecord->sections->pluck('name')->all());
        $this->assertSame(0, $menu->currentVersionRecord->sections->flatMap->items->count());
    }

    public function test_duplicate_preserves_a_structured_final_menu_state_and_delete_retires_active_assignments(): void
    {
        $this->seed(DatabaseSeeder::class);
        $workspace = Workspace::query()->where('slug', 'humoo-demo-kitchen')->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $menu = app(CreateMenu::class)->execute($workspace->id, $user->id, [
            'name' => 'Down South Boulevard',
            'status' => 'active',
            'sections' => [['name' => 'Hot Food', 'items' => [['name' => 'Prime Rib']]]],
        ])->fresh('currentVersionRecord.sections.items');

        $copy = app(DuplicateMenu::class)->execute($menu, $workspace->id, $user->id, [
            'proposed_name' => 'Down South Boulevard Premium',
            'sections' => [[
                'name' => 'Hot Food',
                'items' => [['name' => 'Prime Rib'], ['name' => 'Rosemary Roasted Potatoes']],
            ]],
        ])->fresh('currentVersionRecord.sections.items');
        $this->assertSame('Down South Boulevard Premium', $copy->name);
        $this->assertSame(['Prime Rib', 'Rosemary Roasted Potatoes'], $copy->currentVersionRecord->sections->first()->items->pluck('name')->all());

        $event = Event::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Johnson Wedding',
            'starts_at' => '2026-08-30T18:00:00Z',
            'timezone' => 'America/New_York',
            'status' => 'draft',
            'priority' => 'normal',
            'version' => 1,
        ]);
        $assignment = EventMenu::query()->create([
            'workspace_id' => $workspace->id,
            'event_id' => $event->id,
            'menu_id' => $menu->id,
            'menu_version_id' => $menu->currentVersionRecord->id,
            'type' => 'primary',
            'status' => 'approved',
        ]);

        $this->assertSame(1, app(DeleteMenu::class)->execute($menu, $workspace->id));
        $this->assertSoftDeleted('menus', ['id' => $menu->id]);
        $this->assertSame('superseded', $assignment->fresh()->status);
    }
}
