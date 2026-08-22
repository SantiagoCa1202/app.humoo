<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'event_key' => $this->event_key,
            'type' => $this->type,
            'priority' => $this->priority,
            'title' => $this->title,
            'body' => $this->body,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'action_key' => $this->action_key,
            'action_payload' => $this->action_payload,
            'payload' => $this->payload,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
