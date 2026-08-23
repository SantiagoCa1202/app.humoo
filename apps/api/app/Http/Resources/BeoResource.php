<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'event_id' => $this->event_id,
            'import_batch_id' => $this->import_batch_id,
            'property_id' => $this->property_id,
            'event_order_number' => $this->event_order_number,
            'quote_number' => $this->quote_number,
            'folio_number' => $this->folio_number,
            'source_organization' => $this->source_organization,
            'source_system' => $this->source_system,
            'import_batch' => new BeoImportBatchResource($this->whenLoaded('importBatch')),
            'property' => new PropertyResource($this->whenLoaded('property')),
            'event' => $this->when(
                $this->relationLoaded('event'),
                fn () => $this->event
                    ? (new EventResource($this->event))->resolve()
                    : null
            ),
            'current_version' => $this->current_version,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => new UserReferenceResource($this->whenLoaded('approvedBy')),
            'created_by' => new UserReferenceResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserReferenceResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'latest_version' => new BeoVersionResource($this->whenLoaded('latestVersion')),
            'versions' => BeoVersionResource::collection($this->whenLoaded('versions')),
            'references' => EventOrderReferenceResource::collection($this->whenLoaded('references')),
        ];
    }
}
