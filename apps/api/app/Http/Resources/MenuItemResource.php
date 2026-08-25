<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_section_id' => $this->menu_section_id,
            'recipe_id' => $this->recipe_id,
            'recipe_version_id' => $this->recipe_version_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'course' => $this->course,
            'quantity_per_guest' => $this->quantity_per_guest,
            'serving_unit' => $this->serving_unit,
            'planned_quantity' => $this->planned_quantity,
            'event_planned_quantity' => $this->event_planned_quantity,
            'estimated_unit_cost' => $this->estimated_unit_cost,
            'cost_currency' => $this->cost_currency,
            'optional' => $this->optional,
            'active' => $this->active,
            'position' => $this->position,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'recipe' => $this->relationLoaded('recipe') && $this->recipe
                ? [
                    'id' => $this->recipe->id,
                    'name' => $this->recipe->name,
                    'current_version_id' => $this->recipe->currentVersionRecord?->id,
                  ]
                : null,
            'recipe_version' => $this->relationLoaded('recipeVersion') && $this->recipeVersion
                ? [
                    'id' => $this->recipeVersion->id,
                    'name' => $this->recipeVersion->name,
                    'version' => $this->recipeVersion->version,
                  ]
                : null,
        ];
    }
}
