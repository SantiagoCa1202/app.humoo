<?php

namespace App\Http\Requests\Events;

use App\Models\Contact;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->hasWorkspacePermission(
            $workspace->id,
            'events.edit'
        ) ?? false;
    }

    public function rules(): array
    {
        $workspace = app('currentWorkspace');

        return [
            'version' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'string', 'max:255'],
            'event_group_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('event_groups', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],
            'client_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('clients', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],
            'contact_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('contacts', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],
            'venue_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('venues', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'timezone'],
            'guest_count_expected' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'guest_count_confirmed' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'service_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'event_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => [
                'sometimes',
                Rule::in([
                    'draft',
                    'tentative',
                    'confirmed',
                    'in_production',
                    'completed',
                    'cancelled',
                ]),
            ],
            'priority' => [
                'sometimes',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $workspace = app('currentWorkspace');
            /** @var Event|null $event */
            $event = $this->route('event');
            $startsAt = $this->input('starts_at', $event?->starts_at?->toIso8601String());
            $endsAt = $this->input('ends_at');

            if ($startsAt && $endsAt && strtotime((string) $endsAt) <= strtotime((string) $startsAt)) {
                $validator->errors()->add('ends_at', 'The ends at field must be after starts at.');
            }

            $contactId = $this->input('contact_id');
            $effectiveClientId = $this->exists('client_id')
                ? $this->input('client_id')
                : $event?->client_id;

            if (!$contactId || !$effectiveClientId) {
                return;
            }

            $contact = Contact::query()
                ->where('workspace_id', $workspace->id)
                ->find($contactId);

            if ($contact?->client_id && $contact->client_id !== $effectiveClientId) {
                $validator->errors()->add(
                    'contact_id',
                    'The selected contact does not belong to the selected client.'
                );
            }
        });
    }
}
