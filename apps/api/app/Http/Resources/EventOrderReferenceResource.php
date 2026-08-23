<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventOrderReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_beo_id' => $this->source_beo_id,
            'source_beo_version_id' => $this->source_beo_version_id,
            'source_event_function_id' => $this->source_event_function_id,
            'target_event_order_number' => $this->target_event_order_number,
            'target_beo_id' => $this->target_beo_id,
            'reference_type' => $this->reference_type,
            'raw_text' => $this->raw_text,
            'source_reference' => $this->source_reference,
        ];
    }
}
