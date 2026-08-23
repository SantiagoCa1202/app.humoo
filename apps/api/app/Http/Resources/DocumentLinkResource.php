<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'document_id' => $this->document_id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'relationship_type' => $this->relationship_type,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
            'source_page' => $this->source_page,
            'attachment_type' => $this->attachment_type,
            'source_reference' => $this->source_reference,
            'linked_by' => new UserReferenceResource($this->whenLoaded('linkedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
