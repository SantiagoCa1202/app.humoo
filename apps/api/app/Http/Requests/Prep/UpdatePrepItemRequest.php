<?php

namespace App\Http\Requests\Prep;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrepItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->hasWorkspacePermission(
            $workspace->id,
            'prep_lists.edit'
        ) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $workspace = app('currentWorkspace');

        return [
            'version' => [
                'required',
                'integer',
                'min:1',
            ],
            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'quantity' => [
                'sometimes',
                'nullable',
                'numeric',
            ],
            'unit_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('units', 'id'),
            ],
            'portions' => [
                'sometimes',
                'nullable',
                'numeric',
            ],
            'yield_quantity' => [
                'sometimes',
                'nullable',
                'numeric',
            ],
            'yield_unit_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('units', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace->id)
                ),
            ],
            'actual_quantity' => [
                'sometimes',
                'nullable',
                'numeric',
            ],
            'actual_unit_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('units', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace->id)
                ),
            ],
            'due_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'starts_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'priority' => [
                'sometimes',
                Rule::in([
                    'low',
                    'normal',
                    'high',
                    'urgent',
                ]),
            ],
            'status' => [
                'sometimes',
                Rule::in([
                    'todo',
                    'in_progress',
                    'blocked',
                    'done',
                    'skipped',
                ]),
            ],
            'blocked_reason' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'prep_section_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('prep_sections', 'id')->where(
                    'workspace_id',
                    $workspace->id
                ),
            ],
            'assignment_membership_id' => [
                'sometimes',
                'nullable',
                'ulid',
                Rule::exists('workspace_memberships', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspace->id)
                ),
            ],
        ];
    }
}
