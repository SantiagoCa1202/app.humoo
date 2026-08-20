<?php

namespace App\Http\Requests\TeamStaff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'records' => ['nullable', 'array'],
            'records.*.id' => ['nullable', 'string'],
            'records.*.starts_at' => ['required', 'date'],
            'records.*.ends_at' => ['required', 'date', 'after:records.*.starts_at'],
            'records.*.timezone' => ['required', 'string', 'max:64'],
            'records.*.available' => ['nullable', 'boolean'],
            'records.*.type' => ['nullable', 'string', 'max:32'],
            'records.*.source' => ['nullable', 'string', 'max:32'],
            'records.*.notes' => ['nullable', 'string'],
            'rules' => ['nullable', 'array'],
            'rules.*.id' => ['nullable', 'string'],
            'rules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'rules.*.starts_at' => ['required', 'date_format:H:i'],
            'rules.*.ends_at' => ['required', 'date_format:H:i'],
            'rules.*.timezone' => ['required', 'string', 'max:64'],
            'rules.*.available' => ['nullable', 'boolean'],
            'rules.*.effective_from' => ['nullable', 'date'],
            'rules.*.effective_until' => ['nullable', 'date', 'after_or_equal:rules.*.effective_from'],
            'rules.*.active' => ['nullable', 'boolean'],
        ];
    }
}
