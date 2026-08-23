<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventFunctionDietaryRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_function_id' => $this->event_function_id,
            'guest_name' => $this->guest_name,
            'count' => $this->count,
            'raw_restriction' => $this->raw_restriction,
            'normalized_restriction' => $this->normalized_restriction,
            'category' => $this->category,
            'source_text' => $this->source_text,
            'source_reference' => $this->source_reference,
        ];
    }
}
