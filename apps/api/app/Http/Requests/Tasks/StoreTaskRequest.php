<?php

namespace App\Http\Requests\Tasks;

use App\Models\Station;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->hasWorkspacePermission($workspace->id, 'tasks.create') ?? false;
    }

    public function rules(): array
    {
        return $this->baseRules(false);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $workspace = app('currentWorkspace');
            $startsAt = $this->input('starts_at');
            $dueAt = $this->input('due_at');
            $teamId = $this->input('team_id');
            $stationId = $this->input('station_id');

            if ($startsAt && $dueAt && strtotime((string) $dueAt) <= strtotime((string) $startsAt)) {
                $validator->errors()->add('due_at', 'The due at field must be after starts at.');
            }

            if (!$stationId) {
                return;
            }

            $station = Station::query()
                ->where('workspace_id', $workspace->id)
                ->find($stationId);

            if (!$station) {
                return;
            }

            if ($teamId && $station->team_id && $station->team_id !== $teamId) {
                $validator->errors()->add(
                    'station_id',
                    'The selected station does not belong to the selected team.'
                );
            }
        });
    }

    protected function baseRules(bool $partial): array
    {
        $workspace = app('currentWorkspace');
        $required = $partial ? ['sometimes'] : ['required'];

        return [
            'title' => [...$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'string', 'max:64'],
            'status' => [...$required, Rule::in(['todo', 'in_progress', 'blocked', 'done', 'cancelled'])],
            'priority' => [...$required, Rule::in(['low', 'normal', 'high', 'urgent'])],
            'event_id' => [
                'nullable',
                'ulid',
                Rule::exists('events', 'id')->where('workspace_id', $workspace->id),
            ],
            'team_id' => [
                'nullable',
                'ulid',
                Rule::exists('teams', 'id')->where('workspace_id', $workspace->id),
            ],
            'station_id' => [
                'nullable',
                'ulid',
                Rule::exists('stations', 'id')->where('workspace_id', $workspace->id),
            ],
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'blocked_reason' => ['nullable', 'string'],
            'source' => ['sometimes', 'string', 'max:32'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'source_id' => ['nullable', 'ulid'],
            'metadata' => ['nullable', 'array'],
            'assignments' => ['sometimes', 'array'],
            'assignments.*.membership_id' => [
                'required_with:assignments',
                'ulid',
                'distinct',
                Rule::exists('workspace_memberships', 'id')->where(function ($query) use ($workspace): void {
                    $query
                        ->where('workspace_id', $workspace->id)
                        ->where('status', 'active');
                }),
            ],
            'assignments.*.is_primary' => ['sometimes', 'boolean'],
            'assignments.*.status' => [
                'sometimes',
                Rule::in(['assigned', 'accepted', 'declined', 'completed', 'cancelled']),
            ],
        ];
    }
}
