<?php

namespace App\Http\Requests\Clients;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Client::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'company_name' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'url', 'max:2048'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
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
