<?php

namespace Tests\Unit\Unit;

use App\AI\Tools\ToolProfileSelector;
use App\Models\Permission;
use App\Models\Role;
use App\Models\WorkspaceMembership;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ToolProfileSelectorTest extends TestCase
{
    public function test_discovery_defers_authorized_domain_tools(): void
    {
        $membership = new WorkspaceMembership;
        $role = new Role;
        $role->setRelation('permissions', new Collection([
            new Permission(['key' => 'workspace.view']),
            new Permission(['key' => 'recipes.view']),
        ]));
        $membership->setRelation('role', $role);

        $profile = (new ToolProfileSelector)->select(
            ['membership' => $membership],
            $this->metadata(),
        );

        $this->assertSame('discovery', $profile['profile']);
        $this->assertTrue($profile['discovery_enabled']);
        $this->assertSame(
            ['objectives.cancel', 'execution_plans.create'],
            collect($profile['prompt_metadata'])->pluck('key')->all(),
        );
        $this->assertTrue((bool) collect($profile['metadata'])->firstWhere('key', 'recipes.list')['defer_loading']);
        $this->assertNull(collect($profile['metadata'])->firstWhere('key', 'tasks.create'));
        $this->assertSame(2, $profile['core_count']);
        $this->assertSame(3, $profile['initial_tool_count']);
        $this->assertSame(1, $profile['deferred_count']);
    }

    /** @return array<int, array<string, mixed>> */
    private function metadata(): array
    {
        return [
            ['key' => 'objectives.cancel', 'permission' => 'workspace.view'],
            ['key' => 'execution_plans.create', 'permission' => 'workspace.view'],
            ['key' => 'recipes.list', 'permission' => 'recipes.view'],
            ['key' => 'tasks.create', 'permission' => 'tasks.create'],
        ];
    }
}
