<?php

namespace App\Http\Requests\Venues;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $venue = $this->route('venue');

        if (!$venue instanceof Venue) {
            return false;
        }

        abort_unless(
            $venue->workspace_id === app('currentWorkspace')->id,
            404
        );

        return $venue instanceof Venue
            ? ($this->user()?->can('update', $venue) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:180'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'access_instructions' => ['sometimes', 'nullable', 'string'],
            'parking_notes' => ['sometimes', 'nullable', 'string'],
            'loading_notes' => ['sometimes', 'nullable', 'string'],
            'kitchen_notes' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'nullable', 'string', 'max:32'],
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
