<?php

namespace App\Http\Requests\Recipes;

class UpdateRecipeRequest extends StoreRecipeRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'current_version_id' => ['required', 'string', 'exists:recipe_versions,id'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);
    }
}
