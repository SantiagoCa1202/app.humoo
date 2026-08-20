<?php

namespace App\Http\Resources;

use App\Models\PrepItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrepListVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sections = $this->relationLoaded('sections') ? $this->sections : collect();
        $items = $sections->flatMap(fn ($section) => $section->items ?? collect());

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'prep_list_id' => $this->prep_list_id,
            'menu_version_id' => $this->menu_version_id,
            'beo_version_id' => $this->beo_version_id,
            'version' => $this->version,
            'status' => $this->status,
            'source' => $this->source,
            'generation_metadata' => $this->generation_metadata,
            'guest_count_snapshot' => $this->guest_count_snapshot,
            'event_starts_at_snapshot' => $this->event_starts_at_snapshot?->toIso8601String(),
            'locked' => $this->locked,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'change_summary' => $this->change_summary,
            'revision' => $this->revision,
            'menu_version' => $this->relationLoaded('menuVersion') && $this->menuVersion
                ? [
                    'id' => $this->menuVersion->id,
                    'menu_id' => $this->menuVersion->menu_id,
                    'name' => $this->menuVersion->name,
                    'version' => $this->menuVersion->version,
                ]
                : null,
            'created_by' => $this->relationLoaded('createdBy') && $this->createdBy
                ? (new UserReferenceResource($this->createdBy))->resolve()
                : null,
            'approved_by' => $this->relationLoaded('approvedBy') && $this->approvedBy
                ? (new UserReferenceResource($this->approvedBy))->resolve()
                : null,
            'locked_by' => $this->relationLoaded('lockedBy') && $this->lockedBy
                ? (new UserReferenceResource($this->lockedBy))->resolve()
                : null,
            'sections' => $this->whenLoaded(
                'sections',
                fn () => PrepSectionResource::collection($this->sections)->resolve()
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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
