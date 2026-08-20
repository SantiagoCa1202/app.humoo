<?php

namespace App\Http\Requests\Menus;

class UpdateMenuRequest extends StoreMenuRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'current_version_id' => ['required', 'string', 'exists:menu_versions,id'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);
    }
}
