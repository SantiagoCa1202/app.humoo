<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $client = $this->whenLoaded('client');
        $contact = $this->whenLoaded('contact');
        $venue = $this->whenLoaded('venue');
        $group = $this->whenLoaded('group');

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'event_group_id' => $this->event_group_id,
            'client_id' => $this->client_id,
            'contact_id' => $this->contact_id,
            'venue_id' => $this->venue_id,
            'lead_membership_id' => $this->lead_membership_id,
            'name' => $this->name,
            'event_number' => $this->event_number,
            'description' => $this->description,
            'starts_at' => $this->formatDateTime($this->starts_at),
            'ends_at' => $this->formatDateTime($this->ends_at),
            'timezone' => $this->timezone,
            'guest_count_expected' => $this->guest_count_expected,
            'guest_count_confirmed' => $this->guest_count_confirmed,
            'service_type' => $this->service_type,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'cancelled_at' => $this->formatDateTime($this->cancelled_at),
            'completed_at' => $this->formatDateTime($this->completed_at),
            'version' => $this->version,
            'event_group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'status' => $group->status,
            ] : null,
            'client' => $this->resolveClient($client),
            'contact' => $this->resolveContact($contact, $client),
            'venue' => $this->resolveVenue($venue),
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }

    private function formatDateTime($value): ?string
    {
        return $value?->toIso8601String();
    }

    private function resolveClient($client): ?array
    {
        if ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'company_name' => $client->company_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status,
                'primary_contact' => $client->relationLoaded('primaryContact') && $client->primaryContact
                    ? (new ContactSummaryResource($client->primaryContact))->resolve()
                    : null,
            ];
        }

        if (!$this->client_name_snapshot) {
            return null;
        }

        return [
            'id' => null,
            'name' => $this->client_name_snapshot,
            'company_name' => null,
            'email' => null,
            'phone' => null,
            'status' => null,
            'primary_contact' => null,
        ];
    }

    private function resolveContact($contact, $client): ?array
    {
        if ($contact) {
            return [
                'id' => $contact->id,
                'client_id' => $contact->client_id,
                'display_name' => $contact->display_name,
                'full_name' => trim(implode(' ', array_filter([
                    $contact->first_name,
                    $contact->last_name,
                ]))),
                'email' => $contact->email,
                'phone' => $contact->phone,
                'job_title' => $contact->job_title,
                'contact_type' => $contact->contact_type,
                'organization' => $contact->relationLoaded('client') && $contact->client
                    ? ($contact->client->company_name ?: $contact->client->name)
                    : ($client?->company_name ?: $client?->name),
            ];
        }

        if (!$this->contact_name_snapshot && !$this->contact_email_snapshot && !$this->contact_phone_snapshot) {
            return null;
        }

        return [
            'id' => null,
            'client_id' => $this->client_id,
            'display_name' => $this->contact_name_snapshot,
            'full_name' => $this->contact_name_snapshot,
            'email' => $this->contact_email_snapshot,
            'phone' => $this->contact_phone_snapshot,
            'job_title' => null,
            'contact_type' => null,
            'organization' => $this->client_name_snapshot,
        ];
    }

    private function resolveVenue($venue): ?array
    {
        if ($venue) {
            return [
                'id' => $venue->id,
                'name' => $venue->name,
                'address_line_1' => $venue->address_line_1,
                'address_line_2' => $venue->address_line_2,
                'city' => $venue->city,
                'state' => $venue->state,
                'postal_code' => $venue->postal_code,
                'country_code' => $venue->country_code,
                'timezone' => $venue->timezone,
                'contact_name' => $venue->contact_name,
                'contact_email' => $venue->contact_email,
                'contact_phone' => $venue->contact_phone,
                'notes' => $venue->notes,
            ];
        }

        if (!$this->venue_name_snapshot) {
            return null;
        }

        return [
            'id' => null,
            'name' => $this->venue_name_snapshot,
            'address_line_1' => $this->address_line_1_snapshot,
            'address_line_2' => $this->address_line_2_snapshot,
            'city' => $this->city_snapshot,
            'state' => $this->state_snapshot,
            'postal_code' => $this->postal_code_snapshot,
            'country_code' => $this->country_code_snapshot,
            'timezone' => null,
            'contact_name' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'notes' => null,
        ];
    }
}
