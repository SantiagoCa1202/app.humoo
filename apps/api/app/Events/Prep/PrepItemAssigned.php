<?php

namespace App\Events\Prep;

final class PrepItemAssigned
{
    public function __construct(
        public string $workspaceId,
        public string $prepItemId,
        public string $membershipId,
        public ?string $actorUserId = null,
    ) {
    }
}
