<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = app('currentWorkspace')->id;

        return [
            'proposed_name' => ['nullable', 'string', 'max:180'],
            'include_sections' => ['nullable', 'boolean'],
            'include_items' => ['nullable', 'boolean'],
            'include_recipe_links' => ['nullable', 'boolean'],
            'target_event_id' => [
                'nullable',
                'string',
                Rule::exists('events', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
        ];
    }
}
