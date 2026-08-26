<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Log;

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
            // The client sends entity: null for component actions without a target entity,
            // such as a clarification option. Treat that as an omitted entity.
            'entity' => ['nullable', 'array'],
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

    protected function failedValidation(Validator $validator): void
    {
        if (str_starts_with((string) $this->input('action_id'), 'clarification.')) {
            Log::warning('ai.clarification.resolve_failed', [
                'action_id' => $this->input('action_id'),
                'clarification_id' => $this->input('input.clarification_id'),
                'failure_stage' => 'request_validation',
                'invalid_fields' => array_keys($validator->errors()->toArray()),
                'selected_option_id' => $this->input('input.selected_option_id'),
            ]);
        }

        parent::failedValidation($validator);
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
