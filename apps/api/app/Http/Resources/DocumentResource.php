<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'original_filename' => $this->original_filename,
            'type' => $this->type,
            'disk' => $this->disk,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'checksum_algorithm' => $this->checksum_algorithm,
            'scan_status' => $this->scan_status,
            'scanned_at' => $this->scanned_at?->toIso8601String(),
            'processing_status' => $this->processing_status,
            'processing_error' => $this->processing_error,
            'visibility' => $this->visibility,
            'metadata' => $this->metadata,
            'uploaded_by' => new UserReferenceResource($this->whenLoaded('uploadedBy')),
            'updated_by' => new UserReferenceResource($this->whenLoaded('updatedBy')),
            'links' => DocumentLinkResource::collection($this->whenLoaded('links')),
            'linked_event' => $this->when(
                $this->relationLoaded('linkedEvent'),
                fn () => $this->linkedEvent
                    ? (new EventResource($this->linkedEvent))->resolve()
                    : null
            ),
            'latest_beo_version' => $this->when(
                $this->relationLoaded('latestBeoVersion'),
                fn () => $this->latestBeoVersion
                    ? (new BeoVersionResource($this->latestBeoVersion))->resolve()
                    : null
            ),
            'latest_extraction_run' => $this->when(
                $this->relationLoaded('latestExtractionRun'),
                fn () => $this->latestExtractionRun
                    ? (new ExtractionRunResource($this->latestExtractionRun))->resolve()
                    : null
            ),
            'download_url' => $this->when(
                isset($this->download_url),
                fn () => $this->download_url
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
