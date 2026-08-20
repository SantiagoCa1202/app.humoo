<?php

namespace App\Http\Requests\Events;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->hasWorkspacePermission(
            $workspace->id,
            'events.create'
        ) ?? false;
    }

    public function rules(): array
    {
        $workspace = app('currentWorkspace');

        return [
            'name' => ['required', 'string', 'max:255'],

            'event_group_id' => [
                'nullable',
                'ulid',
                Rule::exists('event_groups', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],

            'client_id' => [
                'nullable',
                'ulid',
                Rule::exists('clients', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],

            'contact_id' => [
                'nullable',
                'ulid',
                Rule::exists('contacts', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],

            'venue_id' => [
                'nullable',
                'ulid',
                Rule::exists('venues', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],

            'starts_at' => [
                'required',
                'date',
            ],

            'ends_at' => [
                'nullable',
                'date',
                'after:starts_at',
            ],

            'timezone' => [
                'required',
                'timezone',
            ],

            'guest_count_expected' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'guest_count_confirmed' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'service_type' => [
                'nullable',
                'string',
                'max:100',
            ],

            'event_type' => [
                'nullable',
                'string',
                'max:64',
            ],

            'status' => [
                'required',
                'in:draft,tentative,confirmed,in_production,completed,cancelled',
            ],

            'priority' => [
                'sometimes',
                'in:low,normal,high,urgent',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startsAt = $this->input('starts_at');
            $endsAt = $this->input('ends_at');

            if ($startsAt && $endsAt && strtotime((string) $endsAt) <= strtotime((string) $startsAt)) {
                $validator->errors()->add('ends_at', 'The ends at field must be after starts at.');
            }

            $workspace = app('currentWorkspace');
            $contactId = $this->input('contact_id');
            $clientId = $this->input('client_id');

            if (!$contactId || !$clientId) {
                return;
            }

            $contact = Contact::query()
                ->where('workspace_id', $workspace->id)
                ->find($contactId);

            if ($contact?->client_id && $contact->client_id !== $clientId) {
                $validator->errors()->add(
                    'contact_id',
                    'The selected contact does not belong to the selected client.'
                );
            }
        });
    }
}
