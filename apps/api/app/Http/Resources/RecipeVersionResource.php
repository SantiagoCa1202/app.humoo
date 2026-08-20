<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'recipe_id' => $this->recipe_id,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'base_yield' => $this->base_yield,
            'yield_unit_id' => $this->yield_unit_id,
            'yield_unit' => $this->relationLoaded('yieldUnit') && $this->yieldUnit
                ? (new UnitResource($this->yieldUnit))->resolve()
                : null,
            'prep_time_minutes' => $this->prep_time_minutes,
            'cook_time_minutes' => $this->cook_time_minutes,
            'rest_time_minutes' => $this->rest_time_minutes,
            'total_time_minutes' => $this->total_time_minutes,
            'shelf_life_hours' => $this->shelf_life_hours,
            'storage_instructions' => $this->storage_instructions,
            'storage_temperature_min' => $this->storage_temperature_min,
            'storage_temperature_max' => $this->storage_temperature_max,
            'temperature_unit_id' => $this->temperature_unit_id,
            'temperature_unit' => $this->relationLoaded('temperatureUnit') && $this->temperatureUnit
                ? (new UnitResource($this->temperatureUnit))->resolve()
                : null,
            'equipment_required' => $this->equipment_required,
            'status' => $this->status,
            'locked' => $this->locked,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'change_summary' => $this->change_summary,
            'source' => $this->source,
            'revision' => $this->revision,
            'estimated_total_cost' => $this->estimated_total_cost,
            'estimated_cost_per_yield' => $this->estimated_cost_per_yield,
            'cost_currency' => $this->cost_currency,
            'metadata' => $this->metadata,
            'ingredients' => $this->whenLoaded(
                'ingredients',
                fn () => RecipeIngredientResource::collection($this->ingredients)->resolve()
            ),
            'steps' => $this->whenLoaded(
                'steps',
                fn () => RecipeStepResource::collection($this->steps)->resolve()
            ),
            'yields' => $this->whenLoaded(
                'yields',
                fn () => RecipeYieldResource::collection($this->yields)->resolve()
            ),
            'allergens' => $this->whenLoaded(
                'allergens',
                fn () => AllergenResource::collection($this->allergens)->resolve()
            ),
            'allergen_count' => $this->relationLoaded('allergens')
                ? $this->allergens->count()
                : null,
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
