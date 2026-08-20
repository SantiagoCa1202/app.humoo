<?php

namespace App\Http\Requests\Prep;

use Illuminate\Validation\Rule;

class UpdatePrepListRequest extends StorePrepListRequest
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
            'name' => ['sometimes', 'string', 'max:180'],
            'event_id' => [
                'sometimes',
                'ulid',
                Rule::exists('events', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'production_starts_at' => ['sometimes', 'nullable', 'date'],
            'production_ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:production_starts_at'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'status' => ['sometimes', Rule::in(['draft', 'active', 'in_progress', 'completed', 'cancelled'])],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
