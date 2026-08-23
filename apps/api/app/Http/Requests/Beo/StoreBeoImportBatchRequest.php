<?php

namespace App\Http\Requests\Beo;

use App\Models\BeoImportBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeoImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BeoImportBatch::class) ?? false;
    }

    public function rules(): array
    {
        $workspaceId = app('currentWorkspace')->id;
        $workspaceExists = fn (string $table) => Rule::exists($table, 'id')
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId));

        return [
            'property_id' => ['nullable', 'ulid', $workspaceExists('properties')],
            'document_id' => ['nullable', 'ulid', $workspaceExists('documents')],
            'original_filename' => ['required', 'string', 'max:255'],
            'source_system' => ['nullable', 'string', 'max:80'],
            'status' => ['sometimes', Rule::in(['received', 'review_required', 'completed', 'failed'])],
            'source_metadata' => ['nullable', 'array'],
            'event_orders' => ['required', 'array', 'min:1'],
            'event_orders.*.event_order_number' => ['required', 'string', 'max:100'],
            'event_orders.*.quote_number' => ['nullable', 'string', 'max:100'],
            'event_orders.*.folio_number' => ['nullable', 'string', 'max:100'],
            'event_orders.*.source_organization' => ['nullable', 'string', 'max:180'],
            'event_orders.*.source_system' => ['nullable', 'string', 'max:80'],
            'event_orders.*.event_id' => ['nullable', 'ulid', $workspaceExists('events')],
            'event_orders.*.property_id' => ['nullable', 'ulid', $workspaceExists('properties')],
            'event_orders.*.versions' => ['required', 'array', 'min:1'],
            'event_orders.*.versions.*.version' => ['nullable', 'integer', 'min:1'],
            'event_orders.*.versions.*.revision_number' => ['nullable', 'integer', 'min:0'],
            'event_orders.*.versions.*.revision_label' => ['nullable', 'string', 'max:120'],
            'event_orders.*.versions.*.revision_type' => ['nullable', 'string', 'max:40'],
            'event_orders.*.versions.*.document_id' => ['nullable', 'ulid', $workspaceExists('documents')],
            'event_orders.*.versions.*.date_printed' => ['nullable', 'date'],
            'event_orders.*.versions.*.source_pages' => ['nullable', 'array'],
            'event_orders.*.versions.*.source_pages.*' => ['integer', 'min:1'],
            'event_orders.*.versions.*.source_metadata' => ['nullable', 'array'],
            'event_orders.*.versions.*.status' => ['sometimes', 'string', 'max:32'],
            'event_orders.*.versions.*.functions' => ['sometimes', 'array'],
            'event_orders.*.versions.*.functions.*.source_function_key' => ['nullable', 'string', 'max:120'],
            'event_orders.*.versions.*.functions.*.source_function_name' => ['required', 'string', 'max:180'],
            'event_orders.*.versions.*.functions.*.function_type' => ['nullable', 'string', 'max:80'],
            'event_orders.*.versions.*.functions.*.operational_category' => ['nullable', 'string', 'max:80'],
            'event_orders.*.versions.*.functions.*.post_as' => ['nullable', 'string', 'max:120'],
            'event_orders.*.versions.*.functions.*.start_at' => ['nullable', 'date'],
            'event_orders.*.versions.*.functions.*.end_at' => ['nullable', 'date'],
            'event_orders.*.versions.*.functions.*.source_start_time' => ['nullable', 'string', 'max:80'],
            'event_orders.*.versions.*.functions.*.source_end_time' => ['nullable', 'string', 'max:80'],
            'event_orders.*.versions.*.functions.*.source_location_text' => ['nullable', 'string'],
            'event_orders.*.versions.*.functions.*.expected_count' => ['nullable', 'integer', 'min:0'],
            'event_orders.*.versions.*.functions.*.guaranteed_count' => ['nullable', 'integer', 'min:0'],
            'event_orders.*.versions.*.functions.*.set_count' => ['nullable', 'integer', 'min:0'],
            'event_orders.*.versions.*.functions.*.production_count' => ['nullable', 'integer', 'min:0'],
            'event_orders.*.versions.*.functions.*.menu_status' => ['sometimes', Rule::in(['available', 'partial', 'tbd', 'none'])],
            'event_orders.*.versions.*.functions.*.operational_signals' => ['nullable', 'array'],
            'event_orders.*.versions.*.functions.*.source_metadata' => ['nullable', 'array'],
            'event_orders.*.versions.*.functions.*.review_metadata' => ['nullable', 'array'],
            'event_orders.*.versions.*.functions.*.venue_ids' => ['sometimes', 'array'],
            'event_orders.*.versions.*.functions.*.venue_ids.*' => ['ulid', $workspaceExists('venues')],
            'event_orders.*.versions.*.functions.*.dietary_requirements' => ['sometimes', 'array'],
            'event_orders.*.versions.*.functions.*.dietary_requirements.*.guest_name' => ['nullable', 'string', 'max:180'],
            'event_orders.*.versions.*.functions.*.dietary_requirements.*.count' => ['nullable', 'integer', 'min:0'],
            'event_orders.*.versions.*.functions.*.dietary_requirements.*.raw_restriction' => ['required', 'string'],
            'event_orders.*.versions.*.functions.*.dietary_requirements.*.normalized_restriction' => ['nullable', 'string', 'max:180'],
            'event_orders.*.versions.*.functions.*.dietary_requirements.*.category' => ['sometimes', 'string', 'max:40'],
            'event_orders.*.versions.*.functions.*.dietary_requirements.*.source_text' => ['nullable', 'string'],
            'event_orders.*.versions.*.functions.*.dietary_requirements.*.source_reference' => ['nullable', 'array'],
            'event_orders.*.versions.*.functions.*.instructions' => ['sometimes', 'array'],
            'event_orders.*.versions.*.functions.*.instructions.*.category' => ['sometimes', 'string', 'max:40'],
            'event_orders.*.versions.*.functions.*.instructions.*.raw_text' => ['required', 'string'],
            'event_orders.*.versions.*.functions.*.instructions.*.normalized_text' => ['nullable', 'string'],
            'event_orders.*.versions.*.functions.*.instructions.*.source_reference' => ['nullable', 'array'],
            'event_orders.*.versions.*.references' => ['sometimes', 'array'],
            'event_orders.*.versions.*.references.*.target_event_order_number' => ['required', 'string', 'max:100'],
            'event_orders.*.versions.*.references.*.reference_type' => ['nullable', 'string', 'max:80'],
            'event_orders.*.versions.*.references.*.raw_text' => ['required', 'string'],
            'event_orders.*.versions.*.references.*.source_event_function_key' => ['nullable', 'string', 'max:120'],
            'event_orders.*.versions.*.references.*.source_reference' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'original_filename' => trim((string) $this->input('original_filename', '')),
            'source_system' => $this->filled('source_system') ? trim((string) $this->input('source_system')) : null,
        ]);
    }
}
