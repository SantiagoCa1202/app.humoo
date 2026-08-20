<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDocumentExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document
            ? ($this->user()?->can('update', $document) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'expected_updated_at' => [
                'required',
                'date',
            ],
            'review_notes' => [
                'nullable',
                'string',
            ],
            'fields' => [
                'required',
                'array',
                'min:1',
            ],
            'fields.*.id' => [
                'required',
                'ulid',
            ],
            'fields.*.review_status' => [
                'required',
                Rule::in([
                    'pending',
                    'accepted',
                    'corrected',
                    'rejected',
                ]),
            ],
            'fields.*.corrected_value_text' => [
                'nullable',
                'string',
            ],
            'fields.*.corrected_value_json' => [
                'nullable',
            ],
            'fields.*.review_notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
