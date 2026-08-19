<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'tax_id' => $this->tax_id,
            'address_line_1' => $this->address_line_1,
            'address_line_2' => $this->address_line_2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'status' => $this->status,
            'notes' => $this->notes,
            'contacts_count' => $this->whenCounted('contacts'),
            'primary_contact' => $this->whenLoaded(
                'primaryContact',
                fn () => $this->primaryContact
                    ? new ContactSummaryResource($this->primaryContact)
                    : null
            ),
            'contacts' => ContactSummaryResource::collection(
                $this->whenLoaded('contacts')
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
