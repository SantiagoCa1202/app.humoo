<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeYieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipe_version_id' => $this->recipe_version_id,
            'quantity' => $this->quantity,
            'unit_id' => $this->unit_id,
            'unit' => $this->relationLoaded('unit') && $this->unit
                ? (new UnitResource($this->unit))->resolve()
                : null,
            'label' => $this->label,
            'factor_to_base' => $this->factor_to_base,
            'is_default' => $this->is_default,
        ];
    }
}
