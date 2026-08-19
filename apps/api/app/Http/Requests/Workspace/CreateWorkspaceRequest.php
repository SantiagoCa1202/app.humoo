<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Workspace::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:120',
            ],
            'default_locale' => [
                'required',
                Rule::in(['en', 'es']),
            ],
            'timezone' => [
                'required',
                'timezone:all',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();

        $this->merge([
            'currency' => Str::upper(trim((string) $this->input('currency', 'USD'))),
            'default_locale' => Str::lower(trim((string) $this->input('default_locale', $user?->locale ?? config('app.locale')))),
            'name' => trim((string) $this->input('name')),
            'timezone' => trim((string) $this->input('timezone', $user?->timezone ?? 'UTC')),
        ]);
    }
}
