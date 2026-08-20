<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrepSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prep_list_version_id' => $this->prep_list_version_id,
            'station_id' => $this->station_id,
            'team_id' => $this->team_id,
            'name' => $this->name,
            'type' => $this->type,
            'production_date' => $this->production_date?->toDateString(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'position' => $this->position,
            'notes' => $this->notes,
            'station' => $this->relationLoaded('station') && $this->station
                ? [
                    'id' => $this->station->id,
                    'name' => $this->station->name,
                ]
                : null,
            'team' => $this->relationLoaded('team') && $this->team
                ? [
                    'id' => $this->team->id,
                    'name' => $this->team->name,
                ]
                : null,
            'items' => $this->whenLoaded(
                'items',
                fn () => PrepItemResource::collection($this->items)->resolve()
            ),
        ];
    }
}
