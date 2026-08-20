<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeoVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'beo_id' => $this->beo_id,
            'document_id' => $this->document_id,
            'document' => new DocumentResource($this->whenLoaded('document')),
            'version' => $this->version,
            'status' => $this->status,
            'snapshot_json' => $this->snapshot_json,
            'change_summary' => $this->change_summary,
            'review_notes' => $this->review_notes,
            'source' => $this->source,
            'approved_by' => new UserReferenceResource($this->whenLoaded('approvedBy')),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_by' => new UserReferenceResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
