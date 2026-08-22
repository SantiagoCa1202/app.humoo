<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $type,
        public string $workspaceId,
        public string $entityType,
        public string $entityId,
        public ?string $occurredAt = null,
        public ?int $version = null,
    ) {
        $this->occurredAt ??= now()->toIso8601String();
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("workspace.{$this->workspaceId}")];
    }

    public function broadcastAs(): string
    {
        return 'workspace.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'workspaceId' => $this->workspaceId,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'occurredAt' => $this->occurredAt,
            'version' => $this->version,
        ];
    }
}
