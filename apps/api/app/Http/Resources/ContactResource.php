<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'full_name' => trim(implode(' ', array_filter([
                $this->first_name,
                $this->last_name,
            ]))),
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'contact_type' => $this->contact_type,
            'is_primary' => (bool) $this->is_primary,
            'notes' => $this->notes,
            'client' => $this->whenLoaded(
                'client',
                fn () => $this->client
                    ? new ClientSummaryResource($this->client)
                    : null
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
