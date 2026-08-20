<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_version_id' => $this->menu_version_id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'position' => $this->position,
            'service_at' => $this->service_at?->toIso8601String(),
            'metadata' => $this->metadata,
            'items' => $this->whenLoaded(
                'items',
                fn () => MenuItemResource::collection($this->items)->resolve()
            ),
            'item_count' => $this->relationLoaded('items')
                ? $this->items->count()
                : null,
        ];
    }
}
