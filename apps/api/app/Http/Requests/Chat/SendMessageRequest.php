<?php

namespace App\Http\Requests\Chat;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app()->bound('currentWorkspace');
    }

    public function rules(): array
    {
        return [
            'client_message_id' => ['nullable', 'string', 'max:100'],
            'content' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'ulid'],
            'locale' => ['nullable', 'string', 'max:8'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_message_id' => $this->normalizeNullableString(
                $this->input('client_message_id')
            ),
            'content' => trim((string) $this->input('content', '')),
            'conversation_id' => $this->normalizeNullableString(
                $this->input('conversation_id')
            ),
            'locale' => $this->normalizeNullableString(
                $this->input('locale')
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
