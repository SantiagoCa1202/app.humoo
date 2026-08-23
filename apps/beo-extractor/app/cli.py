from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

from .contracts import ContractValidationError, validate_job
from .engine import extract_document


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="humoo-beo")
    subparsers = parser.add_subparsers(dest="command", required=True)
    extract = subparsers.add_parser("extract", help="Extract a local PDF into ExtractionResultV1 JSON")
    extract.add_argument("pdf", type=Path)
    extract.add_argument("--job", required=True, type=Path)
    extract.add_argument("--output", required=True, type=Path)
    args = parser.parse_args(argv)

    try:
        job = json.loads(args.job.read_text(encoding="utf-8"))
        validate_job(job)
        result = extract_document(job, document_path=args.pdf)
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(json.dumps(result, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        print(json.dumps({"status": result["status"], "event_orders": len(result["event_orders"]), "output": str(args.output)}, ensure_ascii=False))
        return 0
    except (ContractValidationError, ValueError, OSError) as exc:
        print(f"BEO extraction failed: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
