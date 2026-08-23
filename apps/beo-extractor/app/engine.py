from __future__ import annotations

from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from .classification import classify_pages
from .contracts import ContractValidationError, validate_job, validate_result
from .ingestion import DocumentReader
from .normalization import stable_source_key
from .parsers.generic import parse_event_order
from .segmentation import segment_event_orders


def extract_document(
    job: dict[str, Any],
    *,
    document_path: str | Path | None = None,
    page_texts: list[str] | None = None,
    reader: DocumentReader | None = None,
) -> dict[str, Any]:
    """Extract a local document into a validated ExtractionResultV1 payload."""
    validate_job(job)
    started = datetime.now(timezone.utc)
    document_meta = job["document"]
    reader = reader or DocumentReader()

    if document_path is not None:
        document = reader.read_pdf(document_path)
        if document.checksum.lower() != document_meta["sha256"].lower():
            return _validated_failure(job, document, started, "CHECKSUM_MISMATCH", "The local document checksum does not match the ExtractionJob.")
    elif page_texts is not None:
        document = reader.read_text_pages(page_texts, source_filename=document_meta["filename"], checksum=document_meta["sha256"])
    else:
        raise ValueError("document_path or page_texts is required")

    classifications = classify_pages(document)
    segments, unresolved = segment_event_orders(document.pages, classifications)
    event_orders = [
        parse_event_order(segment, document_id=job["document_id"], source_filename=document.source_filename, profile=job["options"].get("parser_profile"))
        for segment in segments
    ]
    _resolve_cross_order_references(event_orders)
    unresolved.extend(_find_unresolved_source_lines(document, segments))
    unresolved = [_with_trace(item, job["document_id"], item.get("page_number", 1)) for item in unresolved]

    issues = [_issue(job, "PAGE_CLASSIFICATION_FAILED", item["reason"], severity="warning", page_number=item.get("page_number")) for item in unresolved if "Page could not" in str(item.get("reason"))]
    warnings = [_issue(job, "PARSE_FAILED", "Source text was preserved as unresolved data.", severity="warning", page_number=item.get("page_number"), source_key=item.get("related_source_key")) for item in unresolved if "Page could not" not in str(item.get("reason"))]
    if not event_orders and any(page.text for page in document.pages):
        issues.append(_issue(job, "PAGE_CLASSIFICATION_FAILED", "No EventOrder could be segmented from the document.", severity="error"))
    if not any(page.text for page in document.pages):
        issues.append(_issue(job, "EMPTY_DOCUMENT", "The document contained no extractable text.", severity="error"))

    status = "failed" if not event_orders and any(issue["severity"] == "error" for issue in issues) else "partial" if unresolved or issues or warnings else "completed"
    completed = datetime.now(timezone.utc)
    result = {
        "schema_version": "1.0.0",
        "extraction_run_id": job["extraction_run_id"],
        "document_id": job["document_id"],
        "import_batch_id": job["import_batch_id"],
        "correlation_id": job["correlation_id"],
        "status": status,
        "extractor": {
            "extractor_name": "humoo-beo-extractor",
            "extractor_version": "0.1.0",
            "parser_profile": job["options"].get("parser_profile"),
            "parser_version": "1.0.0",
            "ocr_engine": None,
            "ocr_version": None,
            "layout_engine": "pymupdf",
            "layout_version": None,
            "ai_fallback_provider": None,
            "ai_fallback_model": None,
            "started_at": started.isoformat().replace("+00:00", "Z"),
            "completed_at": completed.isoformat().replace("+00:00", "Z"),
            "duration_ms": max(0, int((completed - started).total_seconds() * 1000)),
        },
        "document_analysis": {
            "detected_provider_type": job["options"].get("parser_profile"),
            "page_count": len(document.pages),
            "text_mode": document.text_mode,
            "ocr_used": document.ocr_used,
            "languages": document_meta.get("language_hints") or [],
            "overall_confidence": round(sum(page.confidence for page in classifications) / len(classifications), 3) if classifications else 0.0,
            "sha256": document.checksum,
            "source_filename": document.source_filename,
        },
        "pages": [_page_result(job["document_id"], item) for item in classifications],
        "event_orders": event_orders,
        "issues": issues,
        "warnings": warnings,
        "unresolved_items": unresolved,
        "processing": {
            "started_at": started.isoformat().replace("+00:00", "Z"),
            "completed_at": completed.isoformat().replace("+00:00", "Z"),
            "duration_ms": max(0, int((completed - started).total_seconds() * 1000)),
        },
    }
    return validate_result(result)


def _validated_failure(job: dict[str, Any], document: Any, started: datetime, code: str, message: str) -> dict[str, Any]:
    now = datetime.now(timezone.utc)
    issue = _issue(job, code, message, severity="error", retryable=False)
    result = {
        "schema_version": "1.0.0", "extraction_run_id": job["extraction_run_id"], "document_id": job["document_id"], "import_batch_id": job["import_batch_id"], "correlation_id": job["correlation_id"], "status": "failed",
        "extractor": {"extractor_name": "humoo-beo-extractor", "extractor_version": "0.1.0", "parser_profile": job["options"].get("parser_profile"), "parser_version": "1.0.0", "ocr_engine": None, "ocr_version": None, "layout_engine": "pymupdf", "layout_version": None, "ai_fallback_provider": None, "ai_fallback_model": None, "started_at": started.isoformat().replace("+00:00", "Z"), "completed_at": now.isoformat().replace("+00:00", "Z"), "duration_ms": 0},
        "document_analysis": {"detected_provider_type": job["options"].get("parser_profile"), "page_count": len(document.pages), "text_mode": document.text_mode, "ocr_used": document.ocr_used, "languages": job["document"].get("language_hints") or [], "overall_confidence": 0.0, "sha256": document.checksum, "source_filename": document.source_filename},
        "pages": [], "event_orders": [], "issues": [issue], "warnings": [], "unresolved_items": [], "processing": {"started_at": started.isoformat().replace("+00:00", "Z"), "completed_at": now.isoformat().replace("+00:00", "Z"), "duration_ms": 0},
    }
    return validate_result(result)


def _page_result(document_id: str, classification: Any) -> dict[str, Any]:
    return {
        "page_number": classification.page_number,
        "page_type": classification.page_type,
        "detected_event_order_number": classification.event_order_number,
        "confidence": classification.confidence,
        "text_available": classification.text_available,
        "ocr_used": classification.ocr_used,
        "source_trace": {"document_id": document_id, "page_numbers": [classification.page_number], "source_text": classification.source_text or "empty page", "confidence": classification.confidence, "extraction_method": "ocr" if classification.ocr_used else "text"},
        "warnings": classification.warnings,
    }


def _with_trace(item: dict[str, object], document_id: str, page_number: int) -> dict[str, object]:
    item = dict(item)
    item["source_trace"] = {"document_id": document_id, "page_numbers": [page_number], "source_text": str(item.get("source_text") or "unresolved source"), "confidence": float(item.get("confidence") or 0.5), "extraction_method": "text"}
    return item


def _find_unresolved_source_lines(document: Any, segments: list[Any]) -> list[dict[str, object]]:
    known = {segment.event_order_number for segment in segments}
    items: list[dict[str, object]] = []
    for page in document.pages:
        for line in page.text.splitlines():
            clean = line.strip()
            if re_search_unknown(clean):
                items.append({"type": "other", "source_text": clean, "page_number": page.page_number, "related_source_key": next((f"eo:{number}" for number in known if number in clean), None), "reason": "Unrecognized source content was preserved for review", "confidence": 0.45, "review_recommended": True})
    return items


def _resolve_cross_order_references(event_orders: list[dict[str, Any]]) -> None:
    numbers = {order["event_order_number"] for order in event_orders}
    for order in event_orders:
        for reference in order["references"]:
            reference["resolved"] = reference["target_event_order_number"] in numbers


def _issue(job: dict[str, Any], code: str, message: str, *, severity: str, retryable: bool = False, page_number: int | None = None, source_key: str | None = None) -> dict[str, Any]:
    return {"code": code, "message": message, "severity": severity, "path": None, "entity": None, "retryable": retryable, "stage": "extraction", "page_number": page_number, "source_key": source_key, "details": {}, "correlation_id": job["correlation_id"]}


def re_search_unknown(value: str) -> bool:
    lower = value.lower()
    return bool(lower.startswith("unknown section") or "mystery item" in lower or lower.startswith("unrecognized:"))
