<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrepItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'prep_section_id' => $this->prep_section_id,
            'recipe_id' => $this->recipe_id,
            'recipe_version_id' => $this->recipe_version_id,
            'menu_item_id' => $this->menu_item_id,
            'station_id' => $this->station_id,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'unit_label' => $this->unit_label,
            'unit' => $this->relationLoaded('unit') && $this->unit
                ? (new UnitResource($this->unit))->resolve()
                : null,
            'portions' => $this->portions,
            'yield_quantity' => $this->yield_quantity,
            'yield_unit_id' => $this->yield_unit_id,
            'yield_unit' => $this->relationLoaded('yieldUnit') && $this->yieldUnit
                ? (new UnitResource($this->yieldUnit))->resolve()
                : null,
            'scale_factor' => $this->scale_factor,
            'actual_quantity' => $this->actual_quantity,
            'actual_unit_id' => $this->actual_unit_id,
            'actual_unit' => $this->relationLoaded('actualUnit') && $this->actualUnit
                ? (new UnitResource($this->actualUnit))->resolve()
                : null,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'priority' => $this->priority,
            'status' => $this->status,
            'blocked_reason' => $this->blocked_reason,
            'completed_by' => $this->relationLoaded('completedBy') && $this->completedBy
                ? (new UserReferenceResource($this->completedBy))->resolve()
                : null,
            'requires_confirmation' => $this->requires_confirmation,
            'generated' => $this->generated,
            'source' => $this->source,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'notes' => $this->notes,
            'version' => $this->version,
            'position' => $this->position,
            'metadata' => $this->metadata,
            'recipe' => $this->relationLoaded('recipe') && $this->recipe
                ? [
                    'id' => $this->recipe->id,
                    'name' => $this->recipe->name,
                ]
                : null,
            'recipe_version' => $this->relationLoaded('recipeVersion') && $this->recipeVersion
                ? [
                    'id' => $this->recipeVersion->id,
                    'name' => $this->recipeVersion->name,
                    'version' => $this->recipeVersion->version,
                ]
                : null,
            'assignments' => $this->whenLoaded(
                'assignments',
                fn () => PrepItemAssignmentResource::collection($this->assignments)->resolve()
            ),
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
