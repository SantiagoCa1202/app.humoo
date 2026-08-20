<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'conversation_id' => $this->conversation_id,
            'message_id' => $this->id,
            'blocks' => MessageBlockResource::collection($this->whenLoaded('blocks'))->resolve(),
            'suggestions' => array_values(
                array_filter(
                    (array) ($this->metadata['suggestions'] ?? []),
                    fn ($item) => is_string($item) && trim($item) !== ''
                )
            ),
        ];
    }
}
