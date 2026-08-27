<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_type' => $this->sender_type,
            'status' => $this->status,
            'locale' => $this->locale,
            'content_text' => $this->content_text,
            'client_message_id' => $this->client_message_id,
            'parent_message_id' => $this->parent_message_id,
            'error_code' => $this->error_code,
            // Technical details remain server-side; the error component carries
            // only the mapped public error code and localized copy.
            'error_message' => null,
            'suggestions' => array_values(
                array_filter(
                    $metadata['suggestions'] ?? [],
                    fn ($item) => is_string($item) && trim($item) !== ''
                )
            ),
            'blocks' => MessageBlockResource::collection($this->whenLoaded('blocks'))->resolve(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
