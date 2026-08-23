<?php

namespace App\Http\Requests\Beo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperationalVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasWorkspacePermission(
            app('currentWorkspace')->id,
            'events.view'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['workspace', 'membership'])],
            'settings' => ['required', 'array'],
            'settings.*' => ['boolean'],
        ];
    }
}
