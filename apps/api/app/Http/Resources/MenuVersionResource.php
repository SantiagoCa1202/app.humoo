<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'menu_id' => $this->menu_id,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'locked' => $this->locked,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'change_summary' => $this->change_summary,
            'source' => $this->source,
            'revision' => $this->revision,
            'metadata' => $this->metadata,
            'sections' => $this->whenLoaded(
                'sections',
                fn () => MenuSectionResource::collection($this->sections)->resolve()
            ),
            'created_by' => $this->relationLoaded('createdBy') && $this->createdBy
                ? (new UserReferenceResource($this->createdBy))->resolve()
                : null,
            'approved_by' => $this->relationLoaded('approvedBy') && $this->approvedBy
                ? (new UserReferenceResource($this->approvedBy))->resolve()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
