<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:120',
            ],
            'last_name' => [
                'required',
                'string',
                'max:120',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'password_confirmation' => [
                'required',
                'string',
                'min:8',
            ],
            'device_name' => [
                'required',
                'string',
                'max:120',
            ],
            'locale' => [
                'sometimes',
                'string',
                'max:10',
            ],
            'timezone' => [
                'sometimes',
                'string',
                'max:120',
            ],
            'invitation_token' => [
                'sometimes',
                'nullable',
                'string',
                'min:20',
                'max:255',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'device_name' => trim((string) $this->input('device_name')),
            'locale' => trim((string) $this->input('locale', config('app.locale'))),
            'timezone' => trim((string) $this->input('timezone', 'UTC')),
            'invitation_token' => trim((string) $this->input('invitation_token')),
        ]);
    }
}
