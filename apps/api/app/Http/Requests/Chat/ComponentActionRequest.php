<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ComponentActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app()->bound('currentWorkspace');
    }

    public function rules(): array
    {
        return [
            'action_id' => ['required', 'string', 'max:120'],
            'component_instance_id' => ['required', 'ulid'],
            'entity' => ['sometimes', 'array'],
            'entity.id' => ['sometimes', 'ulid'],
            'entity.type' => ['sometimes', 'string', 'max:120'],
            'entity.version' => ['sometimes', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'input' => ['nullable', 'array'],
            'input.clarification_id' => ['nullable', 'ulid'],
            'input.selected_option_id' => ['nullable', 'string', 'max:80'],
            'input.custom_value' => ['nullable', 'numeric'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action_id' => trim((string) $this->input('action_id', '')),
            'component_instance_id' => trim((string) $this->input('component_instance_id', '')),
            'idempotency_key' => $this->normalizeNullableString(
                $this->input('idempotency_key')
            ),
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
