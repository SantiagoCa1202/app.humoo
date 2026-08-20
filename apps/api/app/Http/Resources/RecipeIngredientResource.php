<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeIngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipe_version_id' => $this->recipe_version_id,
            'inventory_item_id' => $this->inventory_item_id,
            'component_recipe_id' => $this->component_recipe_id,
            'component_recipe_version_id' => $this->component_recipe_version_id,
            'ingredient_name' => $this->ingredient_name,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'unit' => $this->relationLoaded('unit') && $this->unit
                ? (new UnitResource($this->unit))->resolve()
                : null,
            'waste_percentage' => $this->waste_percentage,
            'yield_percentage' => $this->yield_percentage,
            'conversion_factor' => $this->conversion_factor,
            'unit_cost' => $this->unit_cost,
            'extended_cost' => $this->extended_cost,
            'cost_currency' => $this->cost_currency,
            'optional' => $this->optional,
            'scalable' => $this->scalable,
            'preparation' => $this->preparation,
            'position' => $this->position,
            'notes' => $this->notes,
        ];
    }
}
