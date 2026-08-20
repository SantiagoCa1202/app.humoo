<?php

namespace App\Http\Requests\Prep;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePrepListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->hasWorkspacePermission(
            $workspace->id,
            'prep_lists.edit'
        ) ?? false;
    }

    public function rules(): array
    {
        $workspaceId = app('currentWorkspace')->id;

        return [
            'preview' => ['sometimes', 'boolean'],
            'menu_version_id' => [
                'nullable',
                'ulid',
                Rule::exists('menu_versions', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'beo_version_id' => [
                'nullable',
                'ulid',
                Rule::exists('beo_versions', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'guest_count' => ['nullable', 'integer', 'min:1'],
            'due_at' => ['nullable', 'date'],
            'assignment_membership_id' => [
                'nullable',
                'ulid',
                Rule::exists('workspace_memberships', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'include_assignments' => ['sometimes', 'boolean'],
            'preserve_completed_items' => ['sometimes', 'boolean'],
            'preserve_assignments' => ['sometimes', 'boolean'],
            'source' => ['nullable', Rule::in(['manual', 'regeneration', 'import'])],
            'notes' => ['nullable', 'string'],
            'change_summary' => ['nullable', 'string'],
        ];
    }
}
