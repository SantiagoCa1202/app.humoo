<?php

namespace App\Http\Requests\TeamStaff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStationRequest extends FormRequest
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
            'team_id' => [
                'nullable',
                'string',
                Rule::exists('teams', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'type' => ['nullable', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'position' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:32'],
        ];
    }
}
