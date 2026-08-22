<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'locale' => ['sometimes', Rule::in(['en', 'es'])],
            'timezone' => ['sometimes', 'timezone:all'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('name')) {
            $normalized['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('locale')) {
            $normalized['locale'] = strtolower(trim((string) $this->input('locale')));
        }

        if ($this->has('timezone')) {
            $normalized['timezone'] = trim((string) $this->input('timezone'));
        }

        $this->merge($normalized);
    }
}
