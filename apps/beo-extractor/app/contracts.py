from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any


class ContractValidationError(ValueError):
    """Raised when a job or result cannot satisfy Extraction Contract V1."""


CONTRACT_ROOT = Path(__file__).resolve().parents[3] / "contracts" / "beo-extraction" / "v1"
SUPPORTED_MAJOR = 1
CANONICAL_ID_KEYS = {
    "workspace_id", "user_id", "client_id", "contact_id", "property_id", "venue_id",
    "event_id", "event_group_id", "menu_id", "menu_item_id", "recipe_id",
    "inventory_item_id", "prep_list_id", "task_id", "membership_id",
}


def load_schema(name: str) -> dict[str, Any]:
    path = CONTRACT_ROOT / name
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def validate_job(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    _validate_envelope(payload, errors, require_result_fields=False)
    document = payload.get("document")
    if not isinstance(document, dict):
        errors.append("document must be an object")
    else:
        _require(document, ("filename", "mime_type", "sha256", "file_size", "source_reference", "provider_hint", "language_hints"), "document", errors)
        if not _is_sha256(document.get("sha256")):
            errors.append("document.sha256 must be a 64-character hexadecimal checksum")
        if document.get("file_size") is not None and (not isinstance(document.get("file_size"), int) or document["file_size"] < 0):
            errors.append("document.file_size must be a non-negative integer or null")
        if document.get("language_hints") is not None and not _string_list(document.get("language_hints")):
            errors.append("document.language_hints must be a list of strings or null")

    options = payload.get("options")
    if not isinstance(options, dict):
        errors.append("options must be an object")
    else:
        _require(options, ("use_ocr", "include_layout", "include_source_trace", "parser_profile"), "options", errors)
        for key in ("use_ocr", "include_layout", "include_source_trace"):
            if not isinstance(options.get(key), bool):
                errors.append(f"options.{key} must be boolean")

    _reject_canonical_ids(payload, errors)
    _validate_json_schema(payload, "extraction-job.schema.json", errors)
    _raise(errors)
    return payload


def validate_result(payload: dict[str, Any]) -> dict[str, Any]:
    errors: list[str] = []
    _validate_envelope(payload, errors, require_result_fields=True)
    if payload.get("status") not in {"completed", "partial", "failed"}:
        errors.append("status must be completed, partial, or failed")

    _validate_extractor(payload.get("extractor"), errors)
    _validate_document_analysis(payload.get("document_analysis"), errors)
    _validate_pages(payload.get("pages"), errors)
    _validate_event_orders(payload.get("event_orders"), errors)
    _validate_issues(payload.get("issues"), "issues", errors)
    _validate_issues(payload.get("warnings"), "warnings", errors)
    _validate_unresolved(payload.get("unresolved_items"), errors)
    _validate_processing(payload.get("processing"), errors)
    _walk_confidence(payload, "", errors)
    _reject_canonical_ids(payload, errors)
    _validate_json_schema(payload, "extraction-result.schema.json", errors)

    if payload.get("status") == "partial" and not any(payload.get(key) for key in ("issues", "warnings", "unresolved_items")):
        errors.append("partial results must declare issues, warnings, or unresolved_items")
    if payload.get("status") == "failed" and payload.get("event_orders"):
        errors.append("failed results cannot contain useful event_orders")
    if payload.get("status") == "completed" and any(item.get("severity") == "error" for item in payload.get("issues", []) if isinstance(item, dict)):
        errors.append("completed results cannot contain error-severity issues")

    _raise(errors)
    return payload


def _validate_envelope(payload: dict[str, Any], errors: list[str], *, require_result_fields: bool) -> None:
    keys = ["schema_version", "extraction_run_id", "document_id", "import_batch_id", "correlation_id"]
    if require_result_fields:
        keys.extend(["status", "extractor", "document_analysis", "pages", "event_orders", "issues", "warnings", "unresolved_items", "processing"])
    else:
        keys.extend(["document", "options", "requested_at"])
    _require(payload, tuple(keys), "", errors)
    version = payload.get("schema_version")
    if not isinstance(version, str) or not re.fullmatch(r"1\.\d+\.\d+", version):
        errors.append("schema_version must be a supported 1.x.y version")
    for key in ("extraction_run_id", "document_id", "correlation_id"):
        if not isinstance(payload.get(key), str) or not payload[key].strip():
            errors.append(f"{key} must be a non-empty string")
    if payload.get("import_batch_id") is not None and not isinstance(payload.get("import_batch_id"), str):
        errors.append("import_batch_id must be a string or null")


def _validate_extractor(value: Any, errors: list[str]) -> None:
    if not isinstance(value, dict):
        errors.append("extractor must be an object")
        return
    _require(value, ("extractor_name", "extractor_version", "parser_profile", "parser_version", "ocr_engine", "ocr_version", "layout_engine", "layout_version", "ai_fallback_provider", "ai_fallback_model", "started_at", "completed_at", "duration_ms"), "extractor", errors)
    for key in ("extractor_name", "extractor_version", "started_at"):
        if not isinstance(value.get(key), str) or not value[key].strip():
            errors.append(f"extractor.{key} must be a non-empty string")


def _validate_document_analysis(value: Any, errors: list[str]) -> None:
    if not isinstance(value, dict):
        errors.append("document_analysis must be an object")
        return
    _require(value, ("detected_provider_type", "page_count", "text_mode", "ocr_used", "languages", "overall_confidence", "sha256", "source_filename"), "document_analysis", errors)
    if not isinstance(value.get("page_count"), int) or value["page_count"] < 0:
        errors.append("document_analysis.page_count must be a non-negative integer")
    if value.get("text_mode") not in {"text_native", "scanned", "mixed", "unknown"}:
        errors.append("document_analysis.text_mode is invalid")
    if not isinstance(value.get("ocr_used"), bool) or not _string_list(value.get("languages")):
        errors.append("document_analysis.ocr_used/languages have invalid types")
    if not _is_sha256(value.get("sha256")):
        errors.append("document_analysis.sha256 must be a 64-character hexadecimal checksum")


def _validate_pages(value: Any, errors: list[str]) -> None:
    if not _list(value):
        errors.append("pages must be a list")
        return
    for index, page in enumerate(value):
        path = f"pages[{index}]"
        if not isinstance(page, dict):
            errors.append(f"{path} must be an object")
            continue
        _require(page, ("page_number", "page_type", "detected_event_order_number", "confidence", "text_available", "ocr_used", "source_trace", "warnings"), path, errors)
        if page.get("page_type") not in {"EVENT_ORDER", "CONTINUATION", "DIAGRAM", "ATTACHMENT", "UNKNOWN"}:
            errors.append(f"{path}.page_type is invalid")
        _validate_trace(page.get("source_trace"), f"{path}.source_trace", errors)


def _validate_event_orders(value: Any, errors: list[str]) -> None:
    if not _list(value):
        errors.append("event_orders must be a list")
        return
    for index, order in enumerate(value):
        path = f"event_orders[{index}]"
        if not isinstance(order, dict):
            errors.append(f"{path} must be an object")
            continue
        _require(order, ("source_key", "event_order_number", "quote_number", "folio_number", "organization", "program_name", "event_date", "property_name", "location_text", "revision", "source_pages", "functions", "references", "attachments", "source_trace", "confidence"), path, errors)
        _validate_trace(order.get("source_trace"), f"{path}.source_trace", errors)
        _validate_functions(order.get("functions"), f"{path}.functions", errors)
        _validate_revision(order.get("revision"), f"{path}.revision", errors)
        _validate_references(order.get("references"), f"{path}.references", errors)
        _validate_attachments(order.get("attachments"), f"{path}.attachments", errors)


def _validate_functions(value: Any, path: str, errors: list[str]) -> None:
    if not _list(value):
        errors.append(f"{path} must be a list")
        return
    required = ("source_key", "source_function_name", "normalized_type", "post_as", "start_time", "end_time", "start_datetime", "end_datetime", "source_location_text", "venue_candidates", "attendance", "menu", "dietary_requirements", "operational_instructions", "staffing", "setup", "av", "attachments", "relevance_signals", "source_trace", "confidence")
    for index, function in enumerate(value):
        item_path = f"{path}[{index}]"
        if not isinstance(function, dict):
            errors.append(f"{item_path} must be an object")
            continue
        _require(function, required, item_path, errors)
        _validate_trace(function.get("source_trace"), f"{item_path}.source_trace", errors)
        _validate_attendance(function.get("attendance"), f"{item_path}.attendance", errors)
        _validate_menu(function.get("menu"), f"{item_path}.menu", errors)
        for key in ("venue_candidates", "dietary_requirements", "operational_instructions", "staffing", "setup", "av", "attachments"):
            if not _list(function.get(key)):
                errors.append(f"{item_path}.{key} must be a list")
        _validate_attachments(function.get("attachments"), f"{item_path}.attachments", errors)


def _validate_attendance(value: Any, path: str, errors: list[str]) -> None:
    if not isinstance(value, dict):
        errors.append(f"{path} must be an object")
        return
    _require(value, ("expected_count", "guaranteed_count", "set_count"), path, errors)
    for key in ("expected_count", "guaranteed_count", "set_count"):
        if value.get(key) is not None and (not isinstance(value[key], int) or value[key] < 0):
            errors.append(f"{path}.{key} must be a non-negative integer or null")


def _validate_menu(value: Any, path: str, errors: list[str]) -> None:
    if not isinstance(value, dict):
        errors.append(f"{path} must be an object")
        return
    _require(value, ("status", "source_title", "sections", "raw_text", "confidence", "source_trace"), path, errors)
    if value.get("status") not in {"available", "partial", "tbd", "none", "unknown"}:
        errors.append(f"{path}.status is invalid")
    if not _list(value.get("sections")):
        errors.append(f"{path}.sections must be a list")
    if value.get("status") == "tbd" and value.get("sections"):
        errors.append(f"{path}.tbd cannot contain sections")
    _validate_trace(value.get("source_trace"), f"{path}.source_trace", errors)


def _validate_revision(value: Any, path: str, errors: list[str]) -> None:
    if not isinstance(value, dict):
        errors.append(f"{path} must be an object")
        return
    _require(value, ("kind", "number", "raw_label", "is_revised", "confidence", "source_trace"), path, errors)
    if value.get("kind") not in {"original", "revision", "popup", "unknown"}:
        errors.append(f"{path}.kind is invalid")
    _validate_trace(value.get("source_trace"), f"{path}.source_trace", errors)


def _validate_references(value: Any, path: str, errors: list[str]) -> None:
    if not _list(value):
        errors.append(f"{path} must be a list")
        return
    for index, reference in enumerate(value):
        item_path = f"{path}[{index}]"
        if not isinstance(reference, dict):
            errors.append(f"{item_path} must be an object")
            continue
        _require(reference, ("source_event_order_number", "source_function_key", "target_event_order_number", "reference_type", "source_text", "confidence", "source_trace", "resolved"), item_path, errors)
        _validate_trace(reference.get("source_trace"), f"{item_path}.source_trace", errors)


def _validate_attachments(value: Any, path: str, errors: list[str]) -> None:
    if not _list(value):
        errors.append(f"{path} must be a list")
        return
    for index, attachment in enumerate(value):
        item_path = f"{path}[{index}]"
        if not isinstance(attachment, dict):
            errors.append(f"{item_path} must be an object")
            continue
        _require(attachment, ("type", "page_number", "source_document", "labels", "extracted_text", "related_function_source_key", "source_location_text", "confidence", "source_trace"), item_path, errors)
        _validate_trace(attachment.get("source_trace"), f"{item_path}.source_trace", errors)


def _validate_issues(value: Any, path: str, errors: list[str]) -> None:
    if not _list(value):
        errors.append(f"{path} must be a list")
        return
    for index, issue in enumerate(value):
        item_path = f"{path}[{index}]"
        if not isinstance(issue, dict):
            errors.append(f"{item_path} must be an object")
            continue
        _require(issue, ("code", "message", "severity", "path", "entity", "retryable", "stage", "page_number", "source_key", "details", "correlation_id"), item_path, errors)
        if issue.get("severity") not in {"info", "warning", "error"}:
            errors.append(f"{item_path}.severity is invalid")


def _validate_unresolved(value: Any, errors: list[str]) -> None:
    if not _list(value):
        errors.append("unresolved_items must be a list")
        return
    for index, item in enumerate(value):
        path = f"unresolved_items[{index}]"
        if not isinstance(item, dict):
            errors.append(f"{path} must be an object")
            continue
        _require(item, ("type", "source_text", "page_number", "related_source_key", "reason", "confidence", "review_recommended", "source_trace"), path, errors)
        _validate_trace(item.get("source_trace"), f"{path}.source_trace", errors)


def _validate_processing(value: Any, errors: list[str]) -> None:
    if not isinstance(value, dict):
        errors.append("processing must be an object")
        return
    _require(value, ("started_at", "completed_at", "duration_ms"), "processing", errors)


def _validate_trace(value: Any, path: str, errors: list[str]) -> None:
    if not isinstance(value, dict):
        errors.append(f"{path} must be an object")
        return
    _require(value, ("document_id", "page_numbers", "source_text", "confidence"), path, errors)
    if not _list(value.get("page_numbers")) or any(not isinstance(page, int) or page < 1 for page in value.get("page_numbers", [])):
        errors.append(f"{path}.page_numbers must contain positive integers")
    if not isinstance(value.get("source_text"), str) or not value["source_text"].strip():
        errors.append(f"{path}.source_text must be non-empty")


def _walk_confidence(value: Any, path: str, errors: list[str]) -> None:
    if isinstance(value, dict):
        for key, child in value.items():
            child_path = f"{path}.{key}" if path else key
            if key == "confidence" and (not isinstance(child, (int, float)) or isinstance(child, bool) or not 0 <= child <= 1):
                errors.append(f"{child_path} must be between 0 and 1")
            _walk_confidence(child, child_path, errors)
    elif isinstance(value, list):
        for index, child in enumerate(value):
            _walk_confidence(child, f"{path}[{index}]", errors)


def _reject_canonical_ids(value: Any, errors: list[str], path: str = "") -> None:
    if not isinstance(value, dict):
        if isinstance(value, list):
            for index, child in enumerate(value):
                _reject_canonical_ids(child, errors, f"{path}[{index}]")
        return
    for key, child in value.items():
        child_path = f"{path}.{key}" if path else key
        if key in CANONICAL_ID_KEYS:
            errors.append(f"{child_path} is a forbidden canonical Humoo identifier")
        _reject_canonical_ids(child, errors, child_path)


def _require(value: dict[str, Any], keys: tuple[str, ...], path: str, errors: list[str]) -> None:
    for key in keys:
        if key not in value:
            errors.append(f"{path + '.' if path else ''}{key} is required")


def _is_sha256(value: Any) -> bool:
    return isinstance(value, str) and bool(re.fullmatch(r"[a-fA-F0-9]{64}", value))


def _string_list(value: Any) -> bool:
    return _list(value) and all(isinstance(item, str) for item in value)


def _list(value: Any) -> bool:
    return isinstance(value, list)


def _raise(errors: list[str]) -> None:
    if errors:
        raise ContractValidationError("; ".join(dict.fromkeys(errors)))


def _validate_json_schema(payload: dict[str, Any], schema_name: str, errors: list[str]) -> None:
    """Use the canonical Draft 2020-12 schema when the optional package exists."""
    try:
        from jsonschema import Draft202012Validator, RefResolver
    except ImportError:
        return

    schema = load_schema(schema_name)
    shared = load_schema("shared.schema.json")
    resolver = RefResolver.from_schema(schema, store={shared.get("$id", ""): shared, "shared.schema.json": shared})
    validator = Draft202012Validator(schema, resolver=resolver)
    for error in validator.iter_errors(payload):
        path = ".".join(str(part) for part in error.absolute_path)
        errors.append(f"schema {path}: {error.message}")
