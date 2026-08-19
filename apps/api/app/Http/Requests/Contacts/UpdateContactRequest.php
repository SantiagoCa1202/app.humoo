<?php

namespace App\Http\Requests\Contacts;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contact = $this->route('contact');

        if (!$contact instanceof Contact) {
            return false;
        }

        abort_unless(
            $contact->workspace_id === app('currentWorkspace')->id,
            404
        );

        return $contact instanceof Contact
            ? ($this->user()?->can('update', $contact) ?? false)
            : false;
    }

    public function rules(): array
    {
        $workspace = app('currentWorkspace');

        return [
            'client_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('clients', 'id')->where('workspace_id', $workspace->id),
            ],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'contact_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'is_primary' => ['sometimes', 'nullable', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'first_name',
            'last_name',
            'display_name',
            'phone',
            'job_title',
            'contact_type',
            'notes',
        ] as $field) {
            if ($this->has($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('email')) {
            $normalized['email'] = Str::lower(trim((string) $this->input('email')));
        }

        $this->merge($normalized);
    }
}
