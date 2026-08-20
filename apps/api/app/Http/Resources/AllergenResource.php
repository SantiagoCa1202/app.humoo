<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllergenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presence = $this->pivot?->presence;

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'presence' => $presence,
            'source' => $this->pivot?->source,
            'severity' => match ($presence) {
                'contains' => 'danger',
                'may_contain', 'cross_contact' => 'warning',
                default => 'neutral',
            },
        ];
    }
}
