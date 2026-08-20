<?php

namespace App\Http\Resources;

use App\Models\PrepItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrepListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentVersion = $this->relationLoaded('currentVersionRecord')
            ? $this->currentVersionRecord
            : null;
        $sections = $currentVersion && $currentVersion->relationLoaded('sections')
            ? $currentVersion->sections
            : collect();
        $items = $sections->flatMap(fn ($section) => $section->items ?? collect());

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'production_starts_at' => $this->production_starts_at?->toIso8601String(),
            'production_ends_at' => $this->production_ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'current_version' => $this->current_version,
            'current_version_id' => $currentVersion?->id,
            'status' => $this->status,
            'total_items' => $this->total_items,
            'completed_items' => $this->completed_items,
            'blocked_items' => $this->blocked_items,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'event' => $this->relationLoaded('event') && $this->event
                ? [
                    'id' => $this->event->id,
                    'name' => $this->event->name,
                    'starts_at' => $this->event->starts_at?->toIso8601String(),
                    'ends_at' => $this->event->ends_at?->toIso8601String(),
                    'timezone' => $this->event->timezone,
                ]
                : null,
            'current_version_record' => $currentVersion
                ? (new PrepListVersionResource($currentVersion))->resolve()
                : null,
            'versions' => $this->whenLoaded(
                'versions',
                fn () => PrepListVersionResource::collection($this->versions)->resolve()
            ),
            'progress' => [
                'assigned_staff_count' => $items
                    ->flatMap(fn (PrepItem $item) => $item->assignments ?? collect())
                    ->pluck('membership_id')
                    ->filter()
                    ->unique()
                    ->count(),
                'blocked' => $items->where('status', 'blocked')->count(),
                'completed' => $items->where('status', 'done')->count(),
                'in_progress' => $items->where('status', 'in_progress')->count(),
                'remaining' => $items->whereNotIn('status', ['done', 'skipped'])->count(),
                'skipped' => $items->where('status', 'skipped')->count(),
                'total' => $items->count(),
                'unassigned' => $items->filter(
                    fn (PrepItem $item) => ($item->assignments?->count() ?? 0) === 0
                )->count(),
            ],
            'created_by' => $this->relationLoaded('createdBy') && $this->createdBy
                ? (new UserReferenceResource($this->createdBy))->resolve()
                : null,
            'updated_by' => $this->relationLoaded('updatedBy') && $this->updatedBy
                ? (new UserReferenceResource($this->updatedBy))->resolve()
                : null,
            'completed_by' => $this->relationLoaded('completedBy') && $this->completedBy
                ? (new UserReferenceResource($this->completedBy))->resolve()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
