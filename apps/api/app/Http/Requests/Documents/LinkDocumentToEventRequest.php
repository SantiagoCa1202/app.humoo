<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkDocumentToEventRequest extends FormRequest
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
        $workspace = app('currentWorkspace');

        return [
            'event_id' => [
                'required',
                'ulid',
                Rule::exists('events', 'id')->where('workspace_id', $workspace->id),
            ],
        ];
    }
}
