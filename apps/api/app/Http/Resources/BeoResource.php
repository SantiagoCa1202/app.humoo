<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'event_id' => $this->event_id,
            'event' => $this->when(
                $this->relationLoaded('event'),
                fn () => $this->event
                    ? (new EventResource($this->event))->resolve()
                    : null
            ),
            'current_version' => $this->current_version,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => new UserReferenceResource($this->whenLoaded('approvedBy')),
            'created_by' => new UserReferenceResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserReferenceResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
