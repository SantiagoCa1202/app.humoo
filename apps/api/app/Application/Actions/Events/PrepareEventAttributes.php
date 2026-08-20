<?php

namespace App\Application\Actions\Events;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Venue;

class PrepareEventAttributes
{
    public function execute(
        string $workspaceId,
        array $attributes,
        ?Event $event = null,
        ?string $userId = null
    ): array {
        $payload = $attributes;

        if (array_key_exists('client_id', $attributes)) {
            $payload = [
                ...$payload,
                ...$this->buildClientSnapshot($workspaceId, $attributes['client_id']),
            ];
        }

        if (array_key_exists('contact_id', $attributes)) {
            $payload = [
                ...$payload,
                ...$this->buildContactSnapshot($workspaceId, $attributes['contact_id']),
            ];
        }

        if (array_key_exists('venue_id', $attributes)) {
            $payload = [
                ...$payload,
                ...$this->buildVenueSnapshot($workspaceId, $attributes['venue_id']),
            ];
        }

        if (array_key_exists('status', $attributes)) {
            $payload = [
                ...$payload,
                ...$this->buildStatusAttributes($attributes['status'], $event, $userId),
            ];
        }

        return $payload;
    }

    private function buildClientSnapshot(
        string $workspaceId,
        ?string $clientId
    ): array {
        if (!$clientId) {
            return [
                'client_name_snapshot' => null,
            ];
        }

        $client = Client::query()
            ->where('workspace_id', $workspaceId)
            ->find($clientId);

        return [
            'client_name_snapshot' => $client?->name,
        ];
    }

    private function buildContactSnapshot(
        string $workspaceId,
        ?string $contactId
    ): array {
        if (!$contactId) {
            return [
                'contact_email_snapshot' => null,
                'contact_name_snapshot' => null,
                'contact_phone_snapshot' => null,
            ];
        }

        $contact = Contact::query()
            ->where('workspace_id', $workspaceId)
            ->find($contactId);

        return [
            'contact_email_snapshot' => $contact?->email,
            'contact_name_snapshot' => $contact?->display_name
                ?: trim(implode(' ', array_filter([
                    $contact?->first_name,
                    $contact?->last_name,
                ]))),
            'contact_phone_snapshot' => $contact?->phone,
        ];
    }

    private function buildVenueSnapshot(
        string $workspaceId,
        ?string $venueId
    ): array {
        if (!$venueId) {
            return [
                'address_line_1_snapshot' => null,
                'address_line_2_snapshot' => null,
                'city_snapshot' => null,
                'country_code_snapshot' => null,
                'postal_code_snapshot' => null,
                'state_snapshot' => null,
                'venue_name_snapshot' => null,
            ];
        }

        $venue = Venue::query()
            ->where('workspace_id', $workspaceId)
            ->find($venueId);

        return [
            'address_line_1_snapshot' => $venue?->address_line_1,
            'address_line_2_snapshot' => $venue?->address_line_2,
            'city_snapshot' => $venue?->city,
            'country_code_snapshot' => $venue?->country_code,
            'postal_code_snapshot' => $venue?->postal_code,
            'state_snapshot' => $venue?->state,
            'venue_name_snapshot' => $venue?->name,
        ];
    }

    private function buildStatusAttributes(
        string $status,
        ?Event $event,
        ?string $userId
    ): array {
        $attributes = [];

        if ($status === 'cancelled') {
            $attributes['cancelled_at'] = $event?->cancelled_at ?? now();
            $attributes['cancelled_by'] = $userId;
        } elseif ($event?->status === 'cancelled') {
            $attributes['cancelled_at'] = null;
            $attributes['cancelled_by'] = null;
        }

        if ($status === 'completed') {
            $attributes['completed_at'] = $event?->completed_at ?? now();
        } elseif ($event?->status === 'completed') {
            $attributes['completed_at'] = null;
        }

        return $attributes;
    }
}
