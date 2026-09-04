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
    public function test_disabled_discovery_preserves_the_complete_p03_catalog(): void
    {
        config()->set('ai.tool_discovery.enabled', false);
        $metadata = $this->metadata();

        $profile = (new ToolProfileSelector)->select([], $metadata);

        $this->assertSame('all', $profile['profile']);
        $this->assertSame($metadata, $profile['metadata']);
        $this->assertFalse($profile['discovery_enabled']);
        $this->assertSame(0, $profile['deferred_count']);
    }

    public function test_enabled_discovery_keeps_only_authorized_core_and_deferred_tools(): void
    {
        config()->set('ai.tool_discovery.enabled', true);
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
            ['orchestration.respond', 'execution_plans.create'],
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
            ['key' => 'orchestration.respond', 'permission' => 'workspace.view'],
            ['key' => 'execution_plans.create', 'permission' => 'workspace.view'],
            ['key' => 'recipes.list', 'permission' => 'recipes.view'],
            ['key' => 'tasks.create', 'permission' => 'tasks.create'],
        ];
    }
}
