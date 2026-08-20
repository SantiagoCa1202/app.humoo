<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuEventReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'timezone' => $this->timezone,
            'venue' => $this->relationLoaded('venue') && $this->venue
                ? [
                    'id' => $this->venue->id,
                    'name' => $this->venue->name,
                  ]
                : null,
        ];
    }
}
