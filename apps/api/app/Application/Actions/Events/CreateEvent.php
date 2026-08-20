<?php

namespace App\Application\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
    private PrepareEventAttributes $prepareEventAttributes;

    public function __construct(
        PrepareEventAttributes $prepareEventAttributes
    ) {
        $this->prepareEventAttributes = $prepareEventAttributes;
    }

    public function execute(
        string $workspaceId,
        string $userId,
        array $data
    ): Event {
        return DB::transaction(function () use (
            $data,
            $userId,
            $workspaceId
        ) {
            $payload = $this->prepareEventAttributes->execute(
                $workspaceId,
                $data,
                null,
                $userId
            );

            return Event::query()->create([
                ...$payload,
                'workspace_id' => $workspaceId,
                'created_by' => $userId,
                'updated_by' => $userId,
                'version' => 1,
            ]);
        });
    }
}
