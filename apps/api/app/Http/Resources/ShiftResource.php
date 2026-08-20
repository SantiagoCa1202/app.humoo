<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'membership_id' => $this->membership_id,
            'event_id' => $this->event_id,
            'team_id' => $this->team_id,
            'station_id' => $this->station_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'break_minutes' => $this->break_minutes,
            'role' => $this->role,
            'status' => $this->status,
            'clocked_in_at' => $this->clocked_in_at?->toIso8601String(),
            'clocked_out_at' => $this->clocked_out_at?->toIso8601String(),
            'notes' => $this->notes,
            'member' => $this->relationLoaded('membership') && $this->membership
                ? (new WorkspaceMemberReferenceResource($this->membership))->resolve()
                : null,
            'team' => $this->relationLoaded('team') && $this->team
                ? [
                    'id' => $this->team->id,
                    'key' => $this->team->key,
                    'name' => $this->team->name,
                    'status' => $this->team->status,
                ]
                : null,
            'station' => $this->relationLoaded('station') && $this->station
                ? (new StationResource($this->station))->resolve()
                : null,
            'event' => $this->relationLoaded('event') && $this->event
                ? [
                    'id' => $this->event->id,
                    'name' => $this->event->name,
                    'starts_at' => $this->event->starts_at?->toIso8601String(),
                    'timezone' => $this->event->timezone,
                ]
                : null,
            'conflicts' => $this->relationLoaded('conflicts')
                ? ShiftConflictResource::collection($this->conflicts)->resolve()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
