<?php

namespace Tests\Feature\Feature;

use App\AI\EntityResolution\MenuEntityResolver;
use App\Application\Actions\Menus\CreateMenu;
use App\Application\Actions\Menus\UpdateMenuFromChat;
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
}
