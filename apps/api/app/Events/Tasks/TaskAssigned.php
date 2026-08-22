<?php

namespace App\Events\Tasks;

final class TaskAssigned
{
    public function __construct(
        public string $workspaceId,
        public string $taskId,
        public string $membershipId,
        public ?string $actorUserId = null,
    ) {
    }
}
