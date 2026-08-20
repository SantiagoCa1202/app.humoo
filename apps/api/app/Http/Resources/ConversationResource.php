<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'messages' => MessageResource::collection($this->whenLoaded('messages'))->resolve(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
