<?php

namespace App\Http\Requests\Venues;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Venue::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'timezone:all'],
            'contact_name' => ['nullable', 'string', 'max:180'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'access_instructions' => ['nullable', 'string'],
            'parking_notes' => ['nullable', 'string'],
            'loading_notes' => ['nullable', 'string'],
            'kitchen_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'name',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'timezone',
            'contact_name',
            'contact_phone',
            'access_instructions',
            'parking_notes',
            'loading_notes',
            'kitchen_notes',
            'notes',
            'status',
        ] as $field) {
            if ($this->has($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        if ($this->has('country_code')) {
            $normalized['country_code'] = Str::upper(trim((string) $this->input('country_code')));
        }

        if ($this->has('contact_email')) {
            $normalized['contact_email'] = Str::lower(trim((string) $this->input('contact_email')));
        }

        $this->merge($normalized);
    }
}
