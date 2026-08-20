<?php

namespace App\Http\Requests\TeamStaff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspaceId = app('currentWorkspace')->id;

        return [
            'membership_id' => [
                'required',
                'string',
                Rule::exists('workspace_memberships', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'event_id' => [
                'nullable',
                'string',
                Rule::exists('events', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'team_id' => [
                'nullable',
                'string',
                Rule::exists('teams', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'station_id' => [
                'nullable',
                'string',
                Rule::exists('stations', 'id')->where(
                    fn ($query) => $query->where('workspace_id', $workspaceId)
                ),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['required', 'string', 'max:64'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'role' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
