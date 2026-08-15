<?php

namespace App\Application\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class CreateEvent
{
  public function execute(
    string $workspaceId,
    string $userId,
    array $data
  ): Event {
    return DB::transaction(function () use (
      $workspaceId,
      $userId,
      $data
    ) {
      return Event::create([
        ...$data,
        'workspace_id' => $workspaceId,
        'created_by' => $userId,
        'updated_by' => $userId,
        'version' => 1,
      ]);
    });
  }
}
