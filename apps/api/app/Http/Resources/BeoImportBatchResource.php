<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeoImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'property_id' => $this->property_id,
            'property' => new PropertyResource($this->whenLoaded('property')),
            'document_id' => $this->document_id,
            'original_filename' => $this->original_filename,
            'source_system' => $this->source_system,
            'status' => $this->status,
            'source_metadata' => $this->source_metadata,
            'event_orders' => EventOrderResource::collection($this->whenLoaded('eventOrders')),
            'event_orders_count' => $this->when(isset($this->event_orders_count), $this->event_orders_count),
            'created_by' => new UserReferenceResource($this->whenLoaded('createdBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
