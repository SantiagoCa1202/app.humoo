<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'key' => $this->key,
            'description' => $this->description,
            'team_id' => $this->team_id,
            'type' => $this->type,
            'capacity' => $this->capacity,
            'position' => $this->position,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'team' => $this->relationLoaded('team') && $this->team
                ? [
                    'id' => $this->team->id,
                    'key' => $this->team->key,
                    'name' => $this->team->name,
                    'status' => $this->team->status,
                ]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
