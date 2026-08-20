<?php

namespace App\Http\Requests\Tasks;

class UpdateTaskRequest extends StoreTaskRequest
{
    public function authorize(): bool
    {
        $workspace = app('currentWorkspace');

        return $this->user()?->hasWorkspacePermission($workspace->id, 'tasks.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            ...$this->baseRules(true),
            'version' => ['required', 'integer', 'min:1'],
        ];
    }
}
