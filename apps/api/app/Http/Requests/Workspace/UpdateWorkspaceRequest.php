<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->can('update', $workspace) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'min:2',
                'max:120',
            ],
            'default_locale' => [
                'sometimes',
                Rule::in(['en', 'es']),
            ],
            'timezone' => [
                'sometimes',
                'timezone:all',
            ],
            'currency' => [
                'sometimes',
                'string',
                'size:3',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('currency')) {
            $normalized['currency'] = Str::upper(trim((string) $this->input('currency')));
        }

        if ($this->has('default_locale')) {
            $normalized['default_locale'] = Str::lower(trim((string) $this->input('default_locale')));
        }

        if ($this->has('name')) {
            $normalized['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('timezone')) {
            $normalized['timezone'] = trim((string) $this->input('timezone'));
        }

        $this->merge($normalized);
    }
}
