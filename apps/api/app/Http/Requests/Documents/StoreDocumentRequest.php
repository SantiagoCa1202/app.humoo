<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const MAX_FILE_SIZE_KB = 10240;

    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Document::class) ?? false;
    }

    public function rules(): array
    {
        $workspace = app('currentWorkspace');

        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', self::ACCEPTED_MIME_TYPES),
                'max:'.self::MAX_FILE_SIZE_KB,
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in([
                    'beo',
                    'menu',
                    'recipe',
                    'contract',
                    'invoice',
                    'photo',
                    'export',
                    'attachment',
                    'other',
                ]),
            ],
            'source' => [
                'sometimes',
                'string',
                Rule::in([
                    'upload',
                    'manual',
                    'ai',
                    'import',
                ]),
            ],
            'event_id' => [
                'nullable',
                'ulid',
                Rule::exists('events', 'id')->where('workspace_id', $workspace->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => trim((string) $this->input('source', 'upload')),
            'type' => trim((string) $this->input('type', 'beo')),
        ]);
    }
}
