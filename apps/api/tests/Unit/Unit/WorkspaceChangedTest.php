<?php

namespace Tests\Unit\Unit;

use App\Events\Realtime\WorkspaceChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class WorkspaceChangedTest extends TestCase
{
    public function test_realtime_event_contains_only_invalidation_metadata(): void
    {
        $event = new WorkspaceChanged(
            type: 'task.updated',
            workspaceId: 'workspace-1',
            entityType: 'task',
            entityId: 'task-1',
            occurredAt: '2026-08-22T12:00:00+00:00',
            version: 3,
        );

        $this->assertSame('workspace.changed', $event->broadcastAs());
        $this->assertEquals(
            [new PrivateChannel('workspace.workspace-1')],
            $event->broadcastOn(),
        );
        $this->assertSame([
            'type' => 'task.updated',
            'workspaceId' => 'workspace-1',
            'entityType' => 'task',
            'entityId' => 'task-1',
            'occurredAt' => '2026-08-22T12:00:00+00:00',
            'version' => 3,
        ], $event->broadcastWith());
    }
}
