<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractedFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'extraction_run_id' => $this->extraction_run_id,
            'field_key' => $this->field_key,
            'value_type' => $this->value_type,
            'value_text' => $this->value_text,
            'value_json' => $this->value_json,
            'raw_value' => $this->raw_value,
            'confidence' => $this->confidence,
            'page_number' => $this->page_number,
            'source_location' => $this->source_location,
            'reviewed' => $this->reviewed,
            'review_status' => $this->review_status,
            'corrected_value_text' => $this->corrected_value_text,
            'corrected_value_json' => $this->corrected_value_json,
            'reviewed_by' => new UserReferenceResource($this->whenLoaded('reviewedBy')),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'review_notes' => $this->review_notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
