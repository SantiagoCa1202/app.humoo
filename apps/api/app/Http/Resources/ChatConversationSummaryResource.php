<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preview = is_string($this->latest_user_message ?? null)
            ? trim($this->latest_user_message)
            : null;

        return [
            'id' => $this->id,
            'title' => $preview ?: ($this->title ?: 'Humoo AI'),
            'preview' => $preview,
            'message_count' => (int) ($this->messages_count ?? 0),
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
