<?php

namespace App\Data\BeoExtraction\V1;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class BeoExtractionContractValidator
{
    private const CANONICAL_ID_KEYS = [
        'workspace_id', 'user_id', 'client_id', 'contact_id', 'property_id', 'venue_id',
        'event_id', 'event_group_id', 'menu_id', 'menu_item_id', 'recipe_id',
        'inventory_item_id', 'prep_list_id', 'task_id', 'membership_id',
    ];

    public function validateJob(array $payload): ExtractionJobData
    {
        $errors = [];
        $this->validateWithLaravelRules($payload, [
            'schema_version' => ['required', 'string'],
            'extraction_run_id' => ['required', 'string'],
            'document_id' => ['required', 'string'],
            'import_batch_id' => ['present', 'nullable', 'string'],
            'correlation_id' => ['required', 'string'],
            'document' => ['required', 'array'],
            'options' => ['required', 'array'],
            'requested_at' => ['required', 'string'],
        ], $errors);
        $this->validateVersion($payload, $errors);
        $this->requireKeys($payload, [
            'schema_version', 'extraction_run_id', 'document_id', 'import_batch_id',
            'correlation_id', 'document', 'options', 'requested_at',
        ], '', $errors);

        $this->validateEnvelopeIds($payload, $errors);
        $this->validateDocument($payload['document'] ?? null, $errors);
        $this->validateOptions($payload['options'] ?? null, $errors);
        $this->validateNoCanonicalIds($payload, $errors);

        if (!is_string($payload['requested_at'] ?? null) || trim($payload['requested_at']) === '') {
            $errors['requested_at'][] = 'The requested_at field must be a non-empty date-time string.';
        }

        $this->throwIfInvalid($errors);

        return ExtractionJobData::fromValidated($payload);
    }

    public function validateResult(array $payload): ExtractionResultData
    {
        $errors = [];
        $this->validateWithLaravelRules($payload, [
            'schema_version' => ['required', 'string'],
            'extraction_run_id' => ['required', 'string'],
            'document_id' => ['required', 'string'],
            'import_batch_id' => ['present', 'nullable', 'string'],
            'correlation_id' => ['required', 'string'],
            'status' => ['required', 'string'],
            'extractor' => ['required', 'array'],
            'document_analysis' => ['required', 'array'],
            'pages' => ['required', 'array'],
            'event_orders' => ['required', 'array'],
            'issues' => ['required', 'array'],
            'warnings' => ['required', 'array'],
            'unresolved_items' => ['required', 'array'],
            'processing' => ['required', 'array'],
        ], $errors);
        $this->validateVersion($payload, $errors);
        $this->requireKeys($payload, [
            'schema_version', 'extraction_run_id', 'document_id', 'import_batch_id',
            'correlation_id', 'status', 'extractor', 'document_analysis', 'pages',
            'event_orders', 'issues', 'warnings', 'unresolved_items', 'processing',
        ], '', $errors);
        $this->validateEnvelopeIds($payload, $errors);

        if (!in_array($payload['status'] ?? null, ['completed', 'partial', 'failed'], true)) {
            $errors['status'][] = 'The status must be completed, partial, or failed.';
        }

        $this->validateExtractor($payload['extractor'] ?? null, 'extractor', $errors);
        $this->validateDocumentAnalysis($payload['document_analysis'] ?? null, $errors);
        $this->validatePages($payload['pages'] ?? null, $errors);
        $this->validateEventOrders($payload['event_orders'] ?? null, $errors);
        $this->validateIssues($payload['issues'] ?? null, 'issues', $errors);
        $this->validateIssues($payload['warnings'] ?? null, 'warnings', $errors);
        $this->validateUnresolvedItems($payload['unresolved_items'] ?? null, $errors);
        $this->validateProcessing($payload['processing'] ?? null, $errors);
        $this->validateConfidenceValues($payload, '', $errors);
        $this->validateNoCanonicalIds($payload, $errors);

        if (($payload['status'] ?? null) === 'partial'
            && count($payload['issues'] ?? []) === 0
            && count($payload['warnings'] ?? []) === 0
            && count($payload['unresolved_items'] ?? []) === 0) {
            $errors['status'][] = 'A partial result must declare an issue, warning, or unresolved item.';
        }

        if (($payload['status'] ?? null) === 'failed' && count($payload['event_orders'] ?? []) > 0) {
            $errors['event_orders'][] = 'A failed result cannot contain useful event order data.';
        }

        foreach (($payload['issues'] ?? []) as $index => $issue) {
            if (($payload['status'] ?? null) === 'completed' && ($issue['severity'] ?? null) === 'error') {
                $errors["issues.$index.severity"][] = 'A completed result cannot hide a critical error.';
            }
        }

        $this->throwIfInvalid($errors);

        return ExtractionResultData::fromValidated($payload);
    }

    public function validateError(array $payload): ExtractionErrorData
    {
        $errors = [];
        $this->validateWithLaravelRules($payload, [
            'schema_version' => ['required', 'string'],
            'correlation_id' => ['required', 'string'],
            'error' => ['required', 'array'],
        ], $errors);
        $this->validateVersion($payload, $errors);
        $this->requireKeys($payload, ['schema_version', 'correlation_id', 'error'], '', $errors);
        if (!is_string($payload['correlation_id'] ?? null) || trim($payload['correlation_id']) === '') {
            $errors['correlation_id'][] = 'The identifier must be a non-empty string.';
        }
        $this->validateIssues($payload['error'] === null ? null : [$payload['error']], 'error', $errors);
        $this->throwIfInvalid($errors);

        return ExtractionErrorData::fromValidated($payload);
    }

    private function validateVersion(array $payload, array &$errors): void
    {
        $version = $payload['schema_version'] ?? null;
        if (!is_string($version) || !ExtractionContractVersion::supports($version)) {
            $errors['schema_version'][] = 'Only schema major version 1 with semver notation is supported.';
        }
    }

    private function validateEnvelopeIds(array $payload, array &$errors, array $optional = []): void
    {
        foreach (['extraction_run_id', 'document_id', 'correlation_id'] as $key) {
            if (in_array($key, $optional, true)) {
                continue;
            }
            if (!is_string($payload[$key] ?? null) || trim($payload[$key]) === '') {
                $errors[$key][] = 'The identifier must be a non-empty string.';
            }
        }

        if (array_key_exists('import_batch_id', $payload)
            && $payload['import_batch_id'] !== null
            && (!is_string($payload['import_batch_id']) || trim($payload['import_batch_id']) === '')) {
            $errors['import_batch_id'][] = 'The import batch identifier must be a string or null.';
        }
    }

    private function validateDocument($document, array &$errors): void
    {
        if (!is_array($document)) {
            $errors['document'][] = 'The document metadata must be an object.';
            return;
        }

        $this->requireKeys($document, ['filename', 'mime_type', 'sha256', 'file_size', 'source_reference', 'provider_hint', 'language_hints'], 'document', $errors);
        foreach (['filename', 'mime_type'] as $key) {
            if (!is_string($document[$key] ?? null) || trim($document[$key]) === '') {
                $errors["document.$key"][] = 'The value must be a non-empty string.';
            }
        }
        if (!is_string($document['sha256'] ?? null) || !preg_match('/^[a-fA-F0-9]{64}$/', $document['sha256'])) {
            $errors['document.sha256'][] = 'The checksum must be a SHA-256 hexadecimal string.';
        }
        if ($document['file_size'] !== null && (!is_int($document['file_size']) || $document['file_size'] < 0)) {
            $errors['document.file_size'][] = 'The file size must be a non-negative integer or null.';
        }
        if ($document['language_hints'] !== null && !$this->isListOfStrings($document['language_hints'])) {
            $errors['document.language_hints'][] = 'Language hints must be an array of strings or null.';
        }
    }

    private function validateOptions($options, array &$errors): void
    {
        if (!is_array($options)) {
            $errors['options'][] = 'The extraction options must be an object.';
            return;
        }

        $this->requireKeys($options, ['use_ocr', 'include_layout', 'include_source_trace', 'parser_profile'], 'options', $errors);
        foreach (['use_ocr', 'include_layout', 'include_source_trace'] as $key) {
            if (!is_bool($options[$key] ?? null)) {
                $errors["options.$key"][] = 'The option must be boolean.';
            }
        }
    }

    private function validateExtractor($extractor, string $path, array &$errors): void
    {
        if (!is_array($extractor)) {
            $errors[$path][] = 'Extractor metadata must be an object.';
            return;
        }
        $this->requireKeys($extractor, ['extractor_name', 'extractor_version', 'parser_profile', 'parser_version', 'ocr_engine', 'ocr_version', 'layout_engine', 'layout_version', 'ai_fallback_provider', 'ai_fallback_model', 'started_at', 'completed_at', 'duration_ms'], $path, $errors);
        foreach (['extractor_name', 'extractor_version', 'started_at'] as $key) {
            if (!is_string($extractor[$key] ?? null) || trim($extractor[$key]) === '') {
                $errors["$path.$key"][] = 'The value must be a non-empty string.';
            }
        }
        $this->validateNullableNonNegativeInt($extractor['duration_ms'] ?? null, "$path.duration_ms", $errors);
    }

    private function validateDocumentAnalysis($analysis, array &$errors): void
    {
        if (!is_array($analysis)) {
            $errors['document_analysis'][] = 'Document analysis must be an object.';
            return;
        }
        $this->requireKeys($analysis, ['detected_provider_type', 'page_count', 'text_mode', 'ocr_used', 'languages', 'overall_confidence', 'sha256', 'source_filename'], 'document_analysis', $errors);
        if (!is_int($analysis['page_count'] ?? null) || $analysis['page_count'] < 0) {
            $errors['document_analysis.page_count'][] = 'Page count must be a non-negative integer.';
        }
        if (!in_array($analysis['text_mode'] ?? null, ['text_native', 'scanned', 'mixed', 'unknown'], true)) {
            $errors['document_analysis.text_mode'][] = 'Unsupported text mode.';
        }
        if (!is_bool($analysis['ocr_used'] ?? null) || !$this->isListOfStrings($analysis['languages'] ?? null)) {
            $errors['document_analysis.languages'][] = 'Languages must be an array of strings and ocr_used must be boolean.';
        }
        if (!is_string($analysis['sha256'] ?? null) || !preg_match('/^[a-fA-F0-9]{64}$/', $analysis['sha256'])) {
            $errors['document_analysis.sha256'][] = 'The checksum must be a SHA-256 hexadecimal string.';
        }
    }

    private function validatePages($pages, array &$errors): void
    {
        if (!is_array($pages) || !$this->isList($pages)) {
            $errors['pages'][] = 'Pages must be a list.';
            return;
        }
        foreach ($pages as $index => $page) {
            $path = "pages.$index";
            if (!is_array($page)) {
                $errors[$path][] = 'Page classification must be an object.';
                continue;
            }
            $this->requireKeys($page, ['page_number', 'page_type', 'detected_event_order_number', 'confidence', 'text_available', 'ocr_used', 'source_trace', 'warnings'], $path, $errors);
            if (!in_array($page['page_type'] ?? null, ['EVENT_ORDER', 'CONTINUATION', 'DIAGRAM', 'ATTACHMENT', 'UNKNOWN'], true)) {
                $errors["$path.page_type"][] = 'Unsupported page type.';
            }
            if (!is_int($page['page_number'] ?? null) || $page['page_number'] < 1) {
                $errors["$path.page_number"][] = 'Page number must be a positive integer.';
            }
            if (!is_bool($page['text_available'] ?? null) || !is_bool($page['ocr_used'] ?? null) || !$this->isListOfStrings($page['warnings'] ?? null)) {
                $errors[$path][] = 'Page flags and warnings have invalid types.';
            }
            $this->validateSourceTrace($page['source_trace'] ?? null, "$path.source_trace", $errors);
        }
    }

    private function validateEventOrders($orders, array &$errors): void
    {
        if (!is_array($orders) || !$this->isList($orders)) {
            $errors['event_orders'][] = 'Event orders must be a list.';
            return;
        }
        foreach ($orders as $index => $order) {
            $path = "event_orders.$index";
            if (!is_array($order)) {
                $errors[$path][] = 'Event order must be an object.';
                continue;
            }
            $this->requireKeys($order, ['source_key', 'event_order_number', 'quote_number', 'folio_number', 'organization', 'program_name', 'event_date', 'property_name', 'location_text', 'revision', 'source_pages', 'functions', 'references', 'attachments', 'source_trace', 'confidence'], $path, $errors);
            $this->validateSourceKey($order['source_key'] ?? null, "$path.source_key", $errors);
            if (!is_string($order['event_order_number'] ?? null) || trim($order['event_order_number']) === '') {
                $errors["$path.event_order_number"][] = 'Event order number must be a non-empty string.';
            }
            if (!$this->isListOfPositiveInts($order['source_pages'] ?? null)) {
                $errors["$path.source_pages"][] = 'Source pages must be a list of positive integers.';
            }
            $this->validateRevision($order['revision'] ?? null, "$path.revision", $errors);
            $this->validateFunctions($order['functions'] ?? null, "$path.functions", $errors);
            $this->validateReferences($order['references'] ?? null, "$path.references", $errors);
            $this->validateAttachments($order['attachments'] ?? null, "$path.attachments", $errors);
            $this->validateSourceTrace($order['source_trace'] ?? null, "$path.source_trace", $errors);
        }
    }

    private function validateFunctions($functions, string $path, array &$errors): void
    {
        if (!is_array($functions) || !$this->isList($functions)) {
            $errors[$path][] = 'Functions must be a list.';
            return;
        }
        foreach ($functions as $index => $function) {
            $itemPath = "$path.$index";
            if (!is_array($function)) {
                $errors[$itemPath][] = 'Function must be an object.';
                continue;
            }
            $this->requireKeys($function, ['source_key', 'source_function_name', 'normalized_type', 'post_as', 'start_time', 'end_time', 'start_datetime', 'end_datetime', 'source_location_text', 'venue_candidates', 'attendance', 'menu', 'dietary_requirements', 'operational_instructions', 'staffing', 'setup', 'av', 'attachments', 'relevance_signals', 'source_trace', 'confidence'], $itemPath, $errors);
            $this->validateSourceKey($function['source_key'] ?? null, "$itemPath.source_key", $errors);
            if (!is_string($function['source_function_name'] ?? null) || trim($function['source_function_name']) === '') {
                $errors["$itemPath.source_function_name"][] = 'Function name must be a non-empty string.';
            }
            $this->validateVenueCandidates($function['venue_candidates'] ?? null, "$itemPath.venue_candidates", $errors);
            $this->validateAttendance($function['attendance'] ?? null, "$itemPath.attendance", $errors);
            $this->validateMenu($function['menu'] ?? null, "$itemPath.menu", $errors);
            $this->validateDietary($function['dietary_requirements'] ?? null, "$itemPath.dietary_requirements", $errors);
            $this->validateInstructions($function['operational_instructions'] ?? null, "$itemPath.operational_instructions", $errors);
            foreach (['staffing', 'setup', 'av'] as $key) {
                $this->validateSourceItems($function[$key] ?? null, "$itemPath.$key", $errors);
            }
            $this->validateAttachments($function['attachments'] ?? null, "$itemPath.attachments", $errors);
            if (!is_array($function['relevance_signals'] ?? null)) {
                $errors["$itemPath.relevance_signals"][] = 'Relevance signals must be an object.';
            }
            $this->validateSourceTrace($function['source_trace'] ?? null, "$itemPath.source_trace", $errors);
        }
    }

    private function validateMenu($menu, string $path, array &$errors): void
    {
        if (!is_array($menu)) {
            $errors[$path][] = 'Menu must be an object.';
            return;
        }
        $this->requireKeys($menu, ['status', 'source_title', 'sections', 'raw_text', 'confidence', 'source_trace'], $path, $errors);
        if (!in_array($menu['status'] ?? null, ['available', 'partial', 'tbd', 'none', 'unknown'], true)) {
            $errors["$path.status"][] = 'Unsupported menu status.';
        }
        if (!is_array($menu['sections'] ?? null) || !$this->isList($menu['sections'])) {
            $errors["$path.sections"][] = 'Menu sections must be a list.';
        } else {
            foreach ($menu['sections'] as $sectionIndex => $section) {
                $sectionPath = "$path.sections.$sectionIndex";
                if (!is_array($section)) {
                    $errors[$sectionPath][] = 'Menu section must be an object.';
                    continue;
                }
                $this->requireKeys($section, ['source_title', 'normalized_type', 'service_role', 'course_type', 'items', 'confidence', 'source_trace'], $sectionPath, $errors);
                if (!is_array($section['items'] ?? null) || !$this->isList($section['items'])) {
                    $errors["$sectionPath.items"][] = 'Menu items must be a list.';
                } else {
                    foreach ($section['items'] as $itemIndex => $item) {
                        $itemPath = "$sectionPath.items.$itemIndex";
                        if (!is_array($item)) {
                            $errors[$itemPath][] = 'Menu item must be an object.';
                            continue;
                        }
                        $this->requireKeys($item, ['source_text', 'source_name', 'normalized_name', 'notes', 'quantity', 'confidence', 'source_trace'], $itemPath, $errors);
                        $this->validateQuantity($item['quantity'] ?? null, "$itemPath.quantity", $errors);
                        $this->validateSourceTrace($item['source_trace'] ?? null, "$itemPath.source_trace", $errors);
                    }
                }
                $this->validateSourceTrace($section['source_trace'] ?? null, "$sectionPath.source_trace", $errors);
            }
        }
        if (($menu['status'] ?? null) === 'tbd' && count($menu['sections'] ?? []) > 0) {
            $errors["$path.sections"][] = 'A TBD menu cannot contain extracted menu sections.';
        }
        $this->validateSourceTrace($menu['source_trace'] ?? null, "$path.source_trace", $errors);
    }

    private function validateQuantity($quantity, string $path, array &$errors): void
    {
        if (!is_array($quantity)) {
            $errors[$path][] = 'Quantity semantics must be an object.';
            return;
        }
        $this->requireKeys($quantity, ['ordered_quantity', 'ordered_unit', 'pricing_quantity', 'pricing_unit', 'price', 'currency', 'production_quantity', 'production_unit', 'raw_quantity_text', 'source_text'], $path, $errors);
        if (!is_string($quantity['raw_quantity_text'] ?? null) || trim($quantity['raw_quantity_text']) === '') {
            $errors["$path.raw_quantity_text"][] = 'Raw quantity text is required to preserve source semantics.';
        }
        if (!is_string($quantity['source_text'] ?? null) || trim($quantity['source_text']) === '') {
            $errors["$path.source_text"][] = 'Quantity source text is required.';
        }
        if (($quantity['production_quantity'] ?? null) !== null && ($quantity['production_unit'] ?? null) === null) {
            $errors["$path.production_unit"][] = 'Production quantity requires an explicit production unit.';
        }
        $this->validateSourceTrace($quantity['source_trace'] ?? null, "$path.source_trace", $errors);
    }

    private function validateAttendance($attendance, string $path, array &$errors): void
    {
        if (!is_array($attendance)) {
            $errors[$path][] = 'Attendance must be an object.';
            return;
        }
        $this->requireKeys($attendance, ['expected_count', 'guaranteed_count', 'set_count'], $path, $errors);
        foreach (['expected_count', 'guaranteed_count', 'set_count'] as $key) {
            if (($attendance[$key] ?? null) !== null && (!is_int($attendance[$key]) || $attendance[$key] < 0)) {
                $errors["$path.$key"][] = 'Attendance counts must be non-negative integers or null.';
            }
        }
    }

    private function validateVenueCandidates($candidates, string $path, array &$errors): void
    {
        if (!is_array($candidates) || !$this->isList($candidates)) {
            $errors[$path][] = 'Venue candidates must be a list.';
            return;
        }
        foreach ($candidates as $index => $candidate) {
            $candidatePath = "$path.$index";
            if (!is_array($candidate)) {
                $errors[$candidatePath][] = 'Venue candidate must be an object.';
                continue;
            }
            $this->requireKeys($candidate, ['source_name', 'normalized_name', 'confidence', 'source_trace'], $candidatePath, $errors);
            if (!is_string($candidate['source_name'] ?? null) || trim($candidate['source_name']) === '') {
                $errors["$candidatePath.source_name"][] = 'Venue source name must be non-empty.';
            }
            $this->validateSourceTrace($candidate['source_trace'] ?? null, "$candidatePath.source_trace", $errors);
        }
    }

    private function validateDietary($items, string $path, array &$errors): void
    {
        if (!is_array($items) || !$this->isList($items)) {
            $errors[$path][] = 'Dietary requirements must be a list.';
            return;
        }
        foreach ($items as $index => $item) {
            $itemPath = "$path.$index";
            if (!is_array($item)) {
                $errors[$itemPath][] = 'Dietary requirement must be an object.';
                continue;
            }
            $this->requireKeys($item, ['guest_name', 'count', 'source_restriction', 'normalized_restriction', 'category', 'confidence', 'source_trace'], $itemPath, $errors);
            if (!is_string($item['source_restriction'] ?? null) || trim($item['source_restriction']) === '') {
                $errors["$itemPath.source_restriction"][] = 'Source restriction is required.';
            }
            if (!in_array($item['category'] ?? null, ['allergy', 'intolerance', 'vegan', 'vegetarian', 'religious', 'preference', 'other', 'unknown'], true)) {
                $errors["$itemPath.category"][] = 'Unsupported dietary category.';
            }
            $this->validateSourceTrace($item['source_trace'] ?? null, "$itemPath.source_trace", $errors);
        }
    }

    private function validateInstructions($items, string $path, array &$errors): void
    {
        if (!is_array($items) || !$this->isList($items)) {
            $errors[$path][] = 'Operational instructions must be a list.';
            return;
        }
        $allowed = ['food', 'beverage', 'service', 'setup', 'staffing', 'security', 'timing', 'av', 'general', 'unknown'];
        foreach ($items as $index => $item) {
            $itemPath = "$path.$index";
            if (!is_array($item)) {
                $errors[$itemPath][] = 'Instruction must be an object.';
                continue;
            }
            $this->requireKeys($item, ['category', 'source_text', 'normalized_text', 'confidence', 'source_trace'], $itemPath, $errors);
            if (!in_array($item['category'] ?? null, $allowed, true)) {
                $errors["$itemPath.category"][] = 'Unsupported instruction category.';
            }
            $this->validateSourceTrace($item['source_trace'] ?? null, "$itemPath.source_trace", $errors);
        }
    }

    private function validateSourceItems($items, string $path, array &$errors): void
    {
        if (!is_array($items) || !$this->isList($items)) {
            $errors[$path][] = 'Source items must be a list.';
            return;
        }
        foreach ($items as $index => $item) {
            $itemPath = "$path.$index";
            if (!is_array($item)) {
                $errors[$itemPath][] = 'Source item must be an object.';
                continue;
            }
            $this->requireKeys($item, ['source_text', 'confidence', 'source_trace'], $itemPath, $errors);
            $this->validateSourceTrace($item['source_trace'] ?? null, "$itemPath.source_trace", $errors);
        }
    }

    private function validateAttachments($items, string $path, array &$errors): void
    {
        if (!is_array($items) || !$this->isList($items)) {
            $errors[$path][] = 'Attachments must be a list.';
            return;
        }
        foreach ($items as $index => $item) {
            $itemPath = "$path.$index";
            if (!is_array($item)) {
                $errors[$itemPath][] = 'Attachment must be an object.';
                continue;
            }
            $this->requireKeys($item, ['type', 'page_number', 'source_document', 'labels', 'extracted_text', 'related_function_source_key', 'source_location_text', 'confidence', 'source_trace'], $itemPath, $errors);
            if (!in_array($item['type'] ?? null, ['diagram', 'document', 'image', 'other'], true)) {
                $errors["$itemPath.type"][] = 'Unsupported attachment type.';
            }
            if (!is_string($item['source_document'] ?? null) || trim($item['source_document']) === '') {
                $errors["$itemPath.source_document"][] = 'Source document is required.';
            }
            if (!$this->isListOfStrings($item['labels'] ?? null)) {
                $errors["$itemPath.labels"][] = 'Attachment labels must be a list of strings.';
            }
            $this->validateSourceTrace($item['source_trace'] ?? null, "$itemPath.source_trace", $errors);
        }
    }

    private function validateReferences($items, string $path, array &$errors): void
    {
        if (!is_array($items) || !$this->isList($items)) {
            $errors[$path][] = 'References must be a list.';
            return;
        }
        foreach ($items as $index => $item) {
            $itemPath = "$path.$index";
            if (!is_array($item)) {
                $errors[$itemPath][] = 'Reference must be an object.';
                continue;
            }
            $this->requireKeys($item, ['source_event_order_number', 'source_function_key', 'target_event_order_number', 'reference_type', 'source_text', 'confidence', 'source_trace', 'resolved'], $itemPath, $errors);
            if (!in_array($item['reference_type'] ?? null, ['related_event_order', 'continuation', 'attachment', 'unknown'], true)) {
                $errors["$itemPath.reference_type"][] = 'Unsupported reference type.';
            }
            if (!is_bool($item['resolved'] ?? null)) {
                $errors["$itemPath.resolved"][] = 'Resolved must be boolean.';
            }
            $this->validateSourceTrace($item['source_trace'] ?? null, "$itemPath.source_trace", $errors);
        }
    }

    private function validateRevision($revision, string $path, array &$errors): void
    {
        if (!is_array($revision)) {
            $errors[$path][] = 'Revision must be an object.';
            return;
        }
        $this->requireKeys($revision, ['kind', 'number', 'raw_label', 'is_revised', 'confidence', 'source_trace'], $path, $errors);
        if (!in_array($revision['kind'] ?? null, ['original', 'revision', 'popup', 'unknown'], true)) {
            $errors["$path.kind"][] = 'Unsupported revision kind.';
        }
        if (!is_bool($revision['is_revised'] ?? null)) {
            $errors["$path.is_revised"][] = 'is_revised must be boolean.';
        }
        $this->validateSourceTrace($revision['source_trace'] ?? null, "$path.source_trace", $errors);
    }

    private function validateIssues($items, string $path, array &$errors): void
    {
        if (!is_array($items) || !$this->isList($items)) {
            $errors[$path][] = 'Issues must be a list.';
            return;
        }
        $codes = ['INVALID_DOCUMENT', 'UNSUPPORTED_FILE_TYPE', 'EMPTY_DOCUMENT', 'TEXT_EXTRACTION_FAILED', 'OCR_FAILED', 'PAGE_CLASSIFICATION_FAILED', 'PARSE_FAILED', 'CHECKSUM_MISMATCH', 'CONTRACT_VALIDATION_FAILED', 'INTERNAL_ERROR'];
        foreach ($items as $index => $item) {
            $itemPath = "$path.$index";
            if (!is_array($item)) {
                $errors[$itemPath][] = 'Issue must be an object.';
                continue;
            }
            $this->requireKeys($item, ['code', 'message', 'severity', 'path', 'entity', 'retryable', 'stage', 'page_number', 'source_key', 'details', 'correlation_id'], $itemPath, $errors);
            if (!in_array($item['code'] ?? null, $codes, true) || !in_array($item['severity'] ?? null, ['info', 'warning', 'error'], true)) {
                $errors[$itemPath][] = 'Issue code or severity is unsupported.';
            }
            if (!is_string($item['message'] ?? null) || trim($item['message']) === '' || !is_bool($item['retryable'] ?? null) || !is_array($item['details'] ?? null)) {
                $errors[$itemPath][] = 'Issue fields have invalid types.';
            }
            if (!is_string($item['correlation_id'] ?? null) || trim($item['correlation_id']) === '') {
                $errors["$itemPath.correlation_id"][] = 'Issue correlation id is required.';
            }
            if (array_key_exists('source_trace', $item)) {
                foreach (($item['source_trace'] ?? []) as $traceIndex => $trace) {
                    $this->validateSourceTrace($trace, "$itemPath.source_trace.$traceIndex", $errors);
                }
            }
        }
    }

    private function validateUnresolvedItems($items, array &$errors): void
    {
        if (!is_array($items) || !$this->isList($items)) {
            $errors['unresolved_items'][] = 'Unresolved items must be a list.';
            return;
        }
        foreach ($items as $index => $item) {
            $path = "unresolved_items.$index";
            if (!is_array($item)) {
                $errors[$path][] = 'Unresolved item must be an object.';
                continue;
            }
            $this->requireKeys($item, ['type', 'source_text', 'page_number', 'related_source_key', 'reason', 'confidence', 'review_recommended', 'source_trace'], $path, $errors);
            if (!is_string($item['source_text'] ?? null) || !is_string($item['reason'] ?? null) || !is_bool($item['review_recommended'] ?? null)) {
                $errors[$path][] = 'Unresolved item fields have invalid types.';
            }
            $this->validateSourceTrace($item['source_trace'] ?? null, "$path.source_trace", $errors);
        }
    }

    private function validateProcessing($processing, array &$errors): void
    {
        if (!is_array($processing)) {
            $errors['processing'][] = 'Processing metadata must be an object.';
            return;
        }
        $this->requireKeys($processing, ['started_at', 'completed_at', 'duration_ms'], 'processing', $errors);
        if (!is_string($processing['started_at'] ?? null) || trim($processing['started_at']) === '') {
            $errors['processing.started_at'][] = 'Processing start time is required.';
        }
        $this->validateNullableNonNegativeInt($processing['duration_ms'] ?? null, 'processing.duration_ms', $errors);
    }

    private function validateSourceTrace($trace, string $path, array &$errors): void
    {
        if (!is_array($trace)) {
            $errors[$path][] = 'Source trace must be an object.';
            return;
        }
        $this->requireKeys($trace, ['document_id', 'page_numbers', 'source_text', 'confidence'], $path, $errors);
        if (!is_string($trace['document_id'] ?? null) || trim($trace['document_id']) === '') {
            $errors["$path.document_id"][] = 'Source trace document id is required.';
        }
        if (!$this->isListOfPositiveInts($trace['page_numbers'] ?? null)) {
            $errors["$path.page_numbers"][] = 'Source trace page numbers must be positive integers.';
        }
        if (!is_string($trace['source_text'] ?? null) || trim($trace['source_text']) === '') {
            $errors["$path.source_text"][] = 'Source trace text is required.';
        }
    }

    private function validateConfidenceValues($value, string $path, array &$errors): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            $childPath = $path === '' ? (string) $key : "$path.$key";
            if ($key === 'confidence' && (!is_int($child) && !is_float($child) || $child < 0 || $child > 1)) {
                $errors[$childPath][] = 'Confidence must be a number between 0 and 1.';
            }
            $this->validateConfidenceValues($child, $childPath, $errors);
        }
    }

    private function validateNoCanonicalIds($value, array &$errors, string $path = ''): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $child) {
            $childPath = $path === '' ? (string) $key : "$path.$key";
            if (in_array($key, self::CANONICAL_ID_KEYS, true)) {
                $errors[$childPath][] = 'Extraction contracts cannot provide canonical Humoo identifiers.';
            }
            if (is_array($child)) {
                $this->validateNoCanonicalIds($child, $errors, $childPath);
            }
        }
    }

    private function requireKeys(array $payload, array $keys, string $path, array &$errors): void
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                $errors[$path === '' ? $key : "$path.$key"][] = 'This field is required.';
            }
        }
    }

    private function validateSourceKey($value, string $path, array &$errors): void
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > 200) {
            $errors[$path][] = 'Source keys must be stable non-empty strings.';
        }
    }

    private function validateNullableNonNegativeInt($value, string $path, array &$errors): void
    {
        if ($value !== null && (!is_int($value) || $value < 0)) {
            $errors[$path][] = 'The value must be a non-negative integer or null.';
        }
    }

    private function isListOfStrings($value): bool
    {
        if (!is_array($value) || !$this->isList($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }
        return true;
    }

    private function isListOfPositiveInts($value): bool
    {
        if (!is_array($value) || !$this->isList($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_int($item) || $item < 1) {
                return false;
            }
        }
        return true;
    }

    private function isList($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function throwIfInvalid(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateWithLaravelRules(array $payload, array $rules, array &$errors): void
    {
        $validator = Validator::make($payload, $rules);

        if (!$validator->fails()) {
            return;
        }

        foreach ($validator->errors()->toArray() as $path => $messages) {
            $errors[$path] = array_merge($errors[$path] ?? [], $messages);
        }
    }
}
