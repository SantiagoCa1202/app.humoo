<?php

namespace App\Http\Requests\TeamStaff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTeamMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = app('currentWorkspace')->id;

        return [
            'member_ids' => ['required', 'array'],
            'member_ids.*' => [
                'string',
                Rule::exists('workspace_memberships', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'lead_membership_id' => [
                'nullable',
                'string',
                Rule::exists('workspace_memberships', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
        ];
    }
}
