<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        if (!$client instanceof Client) {
            return false;
        }

        abort_unless(
            $client->workspace_id === app('currentWorkspace')->id,
            404
        );

        return $client instanceof Client
            ? ($this->user()?->can('update', $client) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:180'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'website' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:80'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'name',
            'company_name',
            'phone',
            'website',
            'tax_id',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'status',
            'notes',
        ] as $field) {
            if ($this->has($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('email')) {
            $normalized['email'] = Str::lower(trim((string) $this->input('email')));
        }

        if ($this->has('country_code')) {
            $normalized['country_code'] = Str::upper(trim((string) $this->input('country_code')));
        }

        $this->merge($normalized);
    }
}
