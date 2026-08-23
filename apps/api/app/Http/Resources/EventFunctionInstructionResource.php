<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventFunctionInstructionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_function_id' => $this->event_function_id,
            'category' => $this->category,
            'raw_text' => $this->raw_text,
            'normalized_text' => $this->normalized_text,
            'source_reference' => $this->source_reference,
        ];
    }
}
