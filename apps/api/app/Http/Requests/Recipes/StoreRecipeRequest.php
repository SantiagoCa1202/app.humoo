<?php

namespace App\Http\Requests\Recipes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
            'recipe_code' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'exists:recipe_tags,id'],
            'version' => ['required', 'array'],
            'version.name' => ['required', 'string', 'max:180'],
            'version.description' => ['nullable', 'string'],
            'version.status' => ['nullable', Rule::in(['draft', 'review', 'approved', 'superseded', 'archived'])],
            'version.prep_time_minutes' => ['nullable', 'integer', 'min:0'],
            'version.cook_time_minutes' => ['nullable', 'integer', 'min:0'],
            'version.rest_time_minutes' => ['nullable', 'integer', 'min:0'],
            'version.total_time_minutes' => ['nullable', 'integer', 'min:0'],
            'version.change_summary' => ['nullable', 'string'],
            'version.ingredients' => ['nullable', 'array'],
            'version.ingredients.*.ingredient_name' => ['required', 'string', 'max:180'],
            'version.ingredients.*.inventory_item_id' => ['nullable', 'string'],
            'version.ingredients.*.component_recipe_id' => ['nullable', 'string', 'exists:recipes,id'],
            'version.ingredients.*.component_recipe_version_id' => ['nullable', 'string', 'exists:recipe_versions,id'],
            'version.ingredients.*.quantity' => ['required', 'numeric', 'gt:0'],
            'version.ingredients.*.unit_id' => ['required', 'string', 'exists:units,id'],
            'version.ingredients.*.waste_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'version.ingredients.*.yield_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'version.ingredients.*.conversion_factor' => ['nullable', 'numeric', 'gt:0'],
            'version.ingredients.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'version.ingredients.*.cost_currency' => ['nullable', 'string', 'size:3'],
            'version.ingredients.*.optional' => ['nullable', 'boolean'],
            'version.ingredients.*.scalable' => ['nullable', 'boolean'],
            'version.ingredients.*.preparation' => ['nullable', 'string', 'max:255'],
            'version.ingredients.*.notes' => ['nullable', 'string'],
            'version.steps' => ['nullable', 'array'],
            'version.steps.*.title' => ['nullable', 'string', 'max:180'],
            'version.steps.*.instruction' => ['required', 'string'],
            'version.steps.*.duration_minutes' => ['nullable', 'integer', 'min:0'],
            'version.steps.*.type' => ['nullable', 'string', 'max:64'],
            'version.steps.*.critical' => ['nullable', 'boolean'],
            'version.steps.*.notes' => ['nullable', 'string'],
            'version.yields' => ['required', 'array', 'min:1'],
            'version.yields.*.quantity' => ['required', 'numeric', 'gt:0'],
            'version.yields.*.unit_id' => ['required', 'string', 'exists:units,id'],
            'version.yields.*.label' => ['nullable', 'string', 'max:150'],
            'version.yields.*.factor_to_base' => ['nullable', 'numeric', 'gt:0'],
            'version.yields.*.is_default' => ['nullable', 'boolean'],
            'version.allergens' => ['nullable', 'array'],
            'version.allergens.*.id' => ['required_with:version.allergens', 'string', 'exists:allergens,id'],
            'version.allergens.*.presence' => ['nullable', Rule::in(['contains', 'may_contain', 'cross_contact'])],
            'version.allergens.*.source' => ['nullable', Rule::in(['manual', 'ingredient', 'ai'])],
        ];
    }
}
