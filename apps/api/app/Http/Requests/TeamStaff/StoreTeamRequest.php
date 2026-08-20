<?php

namespace App\Http\Requests\TeamStaff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = app('currentWorkspace')->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'key' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
            'lead_membership_id' => [
                'nullable',
                'string',
                Rule::exists('workspace_memberships', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => [
                'string',
                Rule::exists('workspace_memberships', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
        ];
    }
}
