<?php

namespace Tests\Unit\Unit;

use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceContextService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratePrepListTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_context_service_returns_plan_and_entitlements_for_active_membership(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $owner = User::query()->where('email', 'owner@humoo.local')->firstOrFail();
        $membership = $owner->membershipForWorkspace($workspace->id);

        $context = app(WorkspaceContextService::class)
            ->buildForMembership($membership);

        $this->assertSame('pro', $context['plan']?->key);
        $this->assertNotEmpty($context['entitlements']);
        $this->assertContains(
            'events.create',
            $context['permissions'],
        );
    }
}
