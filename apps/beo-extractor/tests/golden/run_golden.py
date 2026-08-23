from __future__ import annotations

import argparse
import fnmatch
import hashlib
import json
import os
import time
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from app.contracts import ContractValidationError, validate_result
from app.engine import extract_document


GOLDEN_DIR = Path(__file__).resolve().parent
MANIFEST_PATH = GOLDEN_DIR / "manifest.json"
REPORTS_DIR = GOLDEN_DIR / "reports"


def main() -> int:
    parser = argparse.ArgumentParser(description="Run the official BEO Golden validation set.")
    parser.add_argument("--phase", choices=("baseline", "final"), required=True)
    parser.add_argument("--root", type=Path, default=None, help="Private Golden root; defaults to HUMOO_BEO_GOLDEN_ROOT.")
    args = parser.parse_args()

    manifest = json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
    root = args.root or (Path(os.environ["HUMOO_BEO_GOLDEN_ROOT"]) if os.getenv("HUMOO_BEO_GOLDEN_ROOT") else None)
    report = run_manifest(manifest, root)
    REPORTS_DIR.mkdir(parents=True, exist_ok=True)
    name = "baseline-summary.json" if args.phase == "baseline" else "final-summary.json"
    (REPORTS_DIR / name).write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    unresolved = {
        "generated_at": report["generated_at"],
        "phase": args.phase,
        "status": report["status"],
        "documents": [
            {
                "golden_id": item["golden_id"],
                "status": item["status"],
                "unresolved_count": item["metrics"].get("unresolved_count"),
                "unresolved_by_type": item["metrics"].get("unresolved_by_type", {}),
            }
            for item in report["documents"]
        ],
    }
    (REPORTS_DIR / "unresolved-summary.json").write_text(json.dumps(unresolved, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps({"phase": args.phase, "status": report["status"], "documents": report["documents"]}, indent=2))
    return 0 if report["status"] != "FAILED" else 1


def run_manifest(manifest: dict[str, Any], root: Path | None) -> dict[str, Any]:
    documents = [run_document(entry, root) for entry in manifest["documents"]]
    statuses = {item["status"] for item in documents}
    status = "FAILED" if "FAILED" in statuses else "BLOCKED" if "BLOCKED" in statuses else "PASS"
    return {
        "generated_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "status": status,
        "extractor_version": "0.1.0",
        "contract_version": "1.0.0",
        "root_configured": root is not None,
        "documents": documents,
    }


def run_document(entry: dict[str, Any], root: Path | None) -> dict[str, Any]:
    base = root / entry["path"] if root else None
    pdf = find_pdf(base, entry["expected_filename_pattern"]) if base else None
    base_result = {
        "golden_id": entry["golden_id"],
        "period": entry["period"],
        "status": "BLOCKED",
        "path_configured": str(base) if base else None,
        "file": None,
        "sha256": None,
        "result": None,
        "verified_expectations": {"passed": 0, "failed": 0, "total": 0, "pass_rate": None},
        "metrics": {},
        "warnings": [],
        "errors": [],
    }
    if pdf is None:
        base_result["errors"] = ["Official Golden PDF is not available at the configured fixture path."]
        return base_result

    started = time.perf_counter()
    checksum = sha256_file(pdf)
    base_result["file"] = pdf.name
    base_result["sha256"] = checksum
    if entry.get("sha256") and entry["sha256"].lower() != checksum.lower():
        base_result["errors"] = ["Configured SHA-256 does not match the supplied Golden PDF."]
        return base_result

    try:
        job = build_job(entry, pdf, checksum)
        result = extract_document(job, document_path=pdf)
        validate_result(result)
        base_result["status"] = "PASS"
        base_result["result"] = "valid_extraction_result_v1"
        base_result["metrics"] = collect_metrics(result, time.perf_counter() - started)
        base_result["verified_expectations"] = evaluate_expectations(entry.get("expectations", []), result)
        if base_result["verified_expectations"]["failed"]:
            base_result["status"] = "FAILED"
    except (ContractValidationError, OSError, ValueError) as exc:
        base_result["status"] = "FAILED"
        base_result["result"] = "invalid_or_unhandled"
        base_result["errors"] = [str(exc)[:1000]]
        base_result["metrics"] = {"processing_time_ms": round((time.perf_counter() - started) * 1000)}
    return base_result


def find_pdf(base: Path | None, pattern: str) -> Path | None:
    if not base or not base.exists():
        return None
    candidates = sorted(path for path in base.rglob("*.pdf") if fnmatch.fnmatch(path.name, pattern))
    return candidates[0] if candidates else None


def build_job(entry: dict[str, Any], pdf: Path, checksum: str) -> dict[str, Any]:
    golden_id = entry["golden_id"].lower()
    return {
        "schema_version": "1.0.0",
        "extraction_run_id": f"golden-run-{golden_id}",
        "document_id": f"golden-document-{golden_id}",
        "import_batch_id": f"golden-batch-{golden_id}",
        "correlation_id": f"golden-correlation-{golden_id}",
        "document": {
            "filename": pdf.name,
            "mime_type": "application/pdf",
            "sha256": checksum,
            "file_size": pdf.stat().st_size,
            "source_reference": None,
            "provider_hint": "humoo-beo-extractor",
            "language_hints": [],
        },
        "options": {
            "use_ocr": False,
            "include_layout": True,
            "include_source_trace": True,
            "parser_profile": "marriott_sheraton",
        },
        "requested_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
    }


def collect_metrics(result: dict[str, Any], duration_seconds: float) -> dict[str, Any]:
    pages = result.get("pages", [])
    orders = result.get("event_orders", [])
    functions = [function for order in orders for function in order.get("functions", [])]
    dietary = [item for function in functions for item in function.get("dietary_requirements", [])]
    diagrams = [attachment for order in orders for attachment in order.get("attachments", []) if attachment.get("type") == "diagram"]
    classifications = Counter(page.get("page_type") for page in pages)
    confidences = [value for value in walk_confidences(result)]
    unresolved = result.get("unresolved_items", [])
    warnings = result.get("warnings", [])
    issues = result.get("issues", [])
    return {
        "total_pages": result.get("document_analysis", {}).get("page_count", len(pages)),
        "classified_pages": len(pages),
        "page_classifications": dict(sorted(classifications.items())),
        "event_orders": len(orders),
        "functions": len(functions),
        "functions_with_food_or_beverage": sum(1 for function in functions if function.get("relevance_signals", {}).get("has_food") or function.get("relevance_signals", {}).get("has_beverage")),
        "functions_without_food_or_beverage": sum(1 for function in functions if not function.get("relevance_signals", {}).get("has_food") and not function.get("relevance_signals", {}).get("has_beverage")),
        "menu_functions": sum(1 for function in functions if function.get("menu", {}).get("status") not in {None, "none"}),
        "dietary_records": len(dietary),
        "diagrams": len(diagrams),
        "unresolved_count": len(unresolved),
        "unresolved_by_type": dict(sorted(Counter(item.get("type", "unknown") for item in unresolved).items())),
        "warnings": len(warnings),
        "critical_failures": sum(1 for issue in issues if issue.get("severity") == "error"),
        "low_confidence_fields": sum(1 for confidence in confidences if confidence < 0.6),
        "confidence_distribution": confidence_distribution(confidences),
        "ocr_used": bool(result.get("document_analysis", {}).get("ocr_used")),
        "processing_time_ms": round(duration_seconds * 1000),
    }


def evaluate_expectations(expectations: list[dict[str, Any]], result: dict[str, Any]) -> dict[str, Any]:
    # Real Golden expectations are intentionally empty until a human has inspected
    # the supplied PDFs. This avoids claiming accuracy from fabricated values.
    passed = failed = 0
    for expectation in expectations:
        if expectation.get("type") == "event_order_count":
            actual = len(result.get("event_orders", []))
            if actual == expectation.get("value"):
                passed += 1
            else:
                failed += 1
    total = passed + failed
    return {"passed": passed, "failed": failed, "total": total, "pass_rate": passed / total if total else None}


def walk_confidences(value: Any):
    if isinstance(value, dict):
        for key, child in value.items():
            if key == "confidence" and isinstance(child, (int, float)):
                yield float(child)
            yield from walk_confidences(child)
    elif isinstance(value, list):
        for child in value:
            yield from walk_confidences(child)


def confidence_distribution(values: list[float]) -> dict[str, int]:
    buckets = {"0.00-0.59": 0, "0.60-0.79": 0, "0.80-1.00": 0}
    for value in values:
        if value < 0.6:
            buckets["0.00-0.59"] += 1
        elif value < 0.8:
            buckets["0.60-0.79"] += 1
        else:
            buckets["0.80-1.00"] += 1
    return buckets


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


if __name__ == "__main__":
    raise SystemExit(main())
