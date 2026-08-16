<?php

namespace Tests\Unit\Unit;

use App\Application\Actions\Events\CreateEvent;
use App\Models\Event;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_creates_event_with_workspace_creator_and_version(): void
    {
        $this->seed(DatabaseSeeder::class);

        $workspace = Workspace::query()
            ->where('slug', 'humoo-demo-kitchen')
            ->firstOrFail();
        $user = User::query()->where('email', 'owner@humoo.local')->firstOrFail();

        $event = app(CreateEvent::class)->execute(
            $workspace->id,
            $user->id,
            [
                'name' => 'Create Event Action Test',
                'starts_at' => now()->addWeek(),
                'timezone' => 'America/New_York',
                'status' => 'confirmed',
                'priority' => 'high',
            ],
        );

        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame($workspace->id, $event->workspace_id);
        $this->assertSame($user->id, $event->created_by);
        $this->assertSame($user->id, $event->updated_by);
        $this->assertSame(1, $event->version);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Create Event Action Test',
        ]);
    }
}
