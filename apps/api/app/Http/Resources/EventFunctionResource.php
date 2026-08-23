<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventFunctionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'beo_version_id' => $this->beo_version_id,
            'source_function_key' => $this->source_function_key,
            'source_function_name' => $this->source_function_name,
            'function_type' => $this->function_type,
            'operational_category' => $this->operational_category,
            'post_as' => $this->post_as,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'source_start_time' => $this->source_start_time,
            'source_end_time' => $this->source_end_time,
            'source_location_text' => $this->source_location_text,
            'expected_count' => $this->expected_count,
            'guaranteed_count' => $this->guaranteed_count,
            'set_count' => $this->set_count,
            'production_count' => $this->production_count,
            'menu_status' => $this->menu_status,
            'operational_signals' => $this->operational_signals,
            'source_metadata' => $this->source_metadata,
            'review_metadata' => $this->review_metadata,
            'venues' => VenueResource::collection($this->whenLoaded('venues')),
            'dietary_requirements' => EventFunctionDietaryRequirementResource::collection($this->whenLoaded('dietaryRequirements')),
            'instructions' => EventFunctionInstructionResource::collection($this->whenLoaded('instructions')),
            'hidden_by_preferences' => $this->when(isset($this->hidden_by_preferences), (bool) $this->hidden_by_preferences),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
