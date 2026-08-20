<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = $this->whenLoaded('event');
        $team = $this->whenLoaded('team');
        $station = $this->whenLoaded('station');
        $completedBy = $this->whenLoaded('completedBy');
        $createdBy = $this->whenLoaded('createdBy');
        $updatedBy = $this->whenLoaded('updatedBy');

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'event_id' => $this->event_id,
            'station_id' => $this->station_id,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'starts_at' => $this->formatDateTime($this->starts_at),
            'due_at' => $this->formatDateTime($this->due_at),
            'completed_at' => $this->formatDateTime($this->completed_at),
            'blocked_reason' => $this->blocked_reason,
            'source' => $this->source,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'version' => $this->version,
            'metadata' => $this->metadata,
            'assignments' => TaskAssignmentResource::collection($this->whenLoaded('assignments')),
            'event' => $event ? [
                'id' => $event->id,
                'name' => $event->name,
                'starts_at' => $this->formatDateTime($event->starts_at),
                'timezone' => $event->timezone,
            ] : null,
            'team' => $team ? [
                'id' => $team->id,
                'key' => $team->key,
                'name' => $team->name,
                'status' => $team->status,
                'type' => $team->type,
            ] : null,
            'station' => $station ? [
                'id' => $station->id,
                'key' => $station->key,
                'name' => $station->name,
                'status' => $station->status,
                'type' => $station->type,
                'team_id' => $station->team_id,
                'team' => $station->relationLoaded('team') && $station->team ? [
                    'id' => $station->team->id,
                    'key' => $station->team->key,
                    'name' => $station->team->name,
                    'status' => $station->team->status,
                    'type' => $station->team->type,
                ] : null,
            ] : null,
            'completed_by' => $this->serializeUser($completedBy),
            'created_by' => $this->serializeUser($createdBy),
            'updated_by' => $this->serializeUser($updatedBy),
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }

    private function formatDateTime($value): ?string
    {
        return $value?->toIso8601String();
    }

    private function serializeUser($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
