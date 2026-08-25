<?php

namespace App\Application\Actions\Team;

use App\Models\Workspace;
use Illuminate\Support\Facades\Validator;

class UpdateWorkspace
{
    public function execute(Workspace $workspace, array $attributes): Workspace
    {
        $attributes = Validator::make($attributes, [
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'default_locale' => ['sometimes', 'in:en,es'],
            'timezone' => ['sometimes', 'timezone:all'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ])->validate();
        $workspace->forceFill($attributes)->save();
        return $workspace->fresh();
    }
}
