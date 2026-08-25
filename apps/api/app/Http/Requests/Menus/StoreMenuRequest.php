<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = app('currentWorkspace')->id;

        return [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'default_guest_count' => ['nullable', 'integer', 'min:1'],
            'event_id' => [
                'nullable',
                'string',
                Rule::exists('events', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['nullable', 'string'],
            'sections.*.name' => ['required', 'string', 'max:150'],
            'sections.*.description' => ['nullable', 'string'],
            'sections.*.type' => ['nullable', 'string', 'max:64'],
            'sections.*.position' => ['nullable', 'integer', 'min:1'],
            'sections.*.items' => ['nullable', 'array'],
            'sections.*.items.*.id' => ['nullable', 'string'],
            'sections.*.items.*.name' => ['required', 'string', 'max:180'],
            'sections.*.items.*.description' => ['nullable', 'string'],
            'sections.*.items.*.notes' => ['nullable', 'string'],
            'sections.*.items.*.position' => ['nullable', 'integer', 'min:1'],
            'sections.*.items.*.quantity_per_guest' => ['nullable', 'numeric', 'min:0'],
            'sections.*.items.*.serving_unit' => ['nullable', 'string', 'max:64'],
            'sections.*.items.*.planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'sections.*.items.*.event_planned_quantity' => ['nullable', 'numeric', 'min:0'],
            'sections.*.items.*.metadata' => ['nullable', 'array'],
            'sections.*.items.*.recipe_id' => [
                'nullable',
                'string',
                Rule::exists('recipes', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'sections.*.items.*.recipe_version_id' => [
                'nullable',
                'string',
                Rule::exists('recipe_versions', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
        ];
    }
}
