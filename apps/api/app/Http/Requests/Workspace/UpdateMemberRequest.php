<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->hasWorkspacePermission(
            $workspace->id,
            'members.manage'
        ) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspace = app('currentWorkspace');

        return [
            'role_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query
                        ->whereNull('workspace_id')
                        ->orWhere('workspace_id', $workspace->id)
                ),
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'active',
                    'suspended',
                    'removed',
                ]),
            ],
        ];
    }
}
