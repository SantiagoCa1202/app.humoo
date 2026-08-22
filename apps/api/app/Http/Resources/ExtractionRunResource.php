<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtractionRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'document_id' => $this->document_id,
            'beo_version_id' => $this->beo_version_id,
            'status' => $this->status,
            'provider' => $this->provider,
            'model_key' => $this->model_key,
            'prompt_version' => $this->prompt_version,
            'schema_version' => $this->schema_version,
            'attempt' => $this->attempt,
            'latency_ms' => $this->latency_ms,
            'usage_json' => $this->usage_json,
            'metadata_json' => $this->metadata_json,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'correlation_id' => $this->correlation_id,
            'requested_by' => new UserReferenceResource($this->whenLoaded('requestedBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
