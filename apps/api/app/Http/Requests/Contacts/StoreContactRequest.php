<?php

namespace App\Http\Requests\Contacts;

use App\Models\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }

    public function rules(): array
    {
        $workspace = app('currentWorkspace');

        return [
            'client_id' => [
                'nullable',
                'ulid',
                Rule::exists('clients', 'id')->where('workspace_id', $workspace->id),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'display_name' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'contact_type' => ['nullable', 'string', 'max:64'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
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
