<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipe_version_id' => $this->recipe_version_id,
            'position' => $this->position,
            'title' => $this->title,
            'instruction' => $this->instruction,
            'duration_minutes' => $this->duration_minutes,
            'station_id' => $this->station_id,
            'temperature' => $this->temperature,
            'temperature_unit_id' => $this->temperature_unit_id,
            'temperature_unit' => $this->relationLoaded('temperatureUnit') && $this->temperatureUnit
                ? (new UnitResource($this->temperatureUnit))->resolve()
                : null,
            'type' => $this->type,
            'critical' => $this->critical,
            'notes' => $this->notes,
        ];
    }
}
