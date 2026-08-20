<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'type' => $this->type,
            'image_document_id' => $this->image_document_id,
            'current_version' => $this->current_version,
            'current_version_id' => $this->relationLoaded('currentVersionRecord') && $this->currentVersionRecord
                ? $this->currentVersionRecord->id
                : null,
            'status' => $this->status,
            'recipe_code' => $this->recipe_code,
            'metadata' => $this->metadata,
            'tags' => $this->whenLoaded(
                'tags',
                fn () => RecipeTagResource::collection($this->tags)->resolve()
            ),
            'current_version_record' => $this->relationLoaded('currentVersionRecord') && $this->currentVersionRecord
                ? (new RecipeVersionResource($this->currentVersionRecord))->resolve()
                : null,
            'created_by' => $this->relationLoaded('createdBy') && $this->createdBy
                ? (new UserReferenceResource($this->createdBy))->resolve()
                : null,
            'updated_by' => $this->relationLoaded('updatedBy') && $this->updatedBy
                ? (new UserReferenceResource($this->updatedBy))->resolve()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
