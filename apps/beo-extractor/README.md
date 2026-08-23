# Humoo BEO Extractor

The official local, transport-agnostic extraction engine for Humoo. It answers “what does the document say?” and returns the Prompt 64 `ExtractionResultV1` shape. Laravel later owns entity resolution, persistence, operational derivations, and visibility.

## Pipeline

```text
PDF/text pages
  -> ingestion (PyMuPDF fast path)
  -> page classification
  -> EventOrder segmentation
  -> provider-profile parsing
  -> source normalization
  -> confidence and unresolved items
  -> contract validation
  -> ExtractionResultV1 JSON
```

Run locally from this directory:

```text
python -m app.cli extract path/to/beo.pdf --job path/to/job.json --output result.json
python -m unittest discover -s tests
```

## Pull worker

The same package can run as a private pull worker. It never exposes an inbound
HTTP server. Configure `HUMOO_API_BASE_URL`, `HUMOO_WORKER_ID`,
`HUMOO_WORKER_TOKEN`, `POLL_INTERVAL_SECONDS`, `JOB_LEASE_SECONDS`,
`REQUEST_TIMEOUT_SECONDS`, and `TEMP_DIR`, then run:

```text
python -m app.worker
```

`HUMOO_API_BASE_URL` is the Laravel API prefix that contains `/internal`; for
this repository the local value is normally `http://127.0.0.1:8000/api/v1`.
Laravel authenticates the worker separately from user Sanctum tokens, derives
workspace/document ownership from the claimed run, streams only the claimed
private document, and validates the submitted result before persistence.

For Docker, build from the repository root with
`docker build -f apps/beo-extractor/Dockerfile .`. Runtime secrets are supplied
only through environment/secrets management. Temporary PDFs live below
`TEMP_DIR` and are removed after each run.

Repeated uploads are deduplicated only when the workspace, document type, and
SHA-256 are identical; a different PDF with the same filename remains a new
document and can produce a new revision. A deliberate re-import of identical
bytes should use the existing document retry flow.

The job must already be an `ExtractionJobV1` envelope. The CLI checks the local document checksum when the job provides one, does not upload files, and writes only the requested local output. No database, Laravel, R2, queue, login, or network service is used.

`PyMuPDF` is the default reader. OCR, Docling/layout, and ambiguity resolution are adapter boundaries; they are optional and disabled unless configured. The current engine preserves low-text/unknown pages and reports them as evidence or unresolved data instead of silently dropping them.

The canonical schemas live at `contracts/beo-extraction/v1/`. The engine loads those files by repository path. If `jsonschema` is installed (`pip install -e ".[contract]"`), Draft 2020-12 validation runs before a result is returned. A deterministic standard-library contract validator remains available for local development when the optional package is absent.

Legacy migration decisions are recorded in `LEGACY_MIGRATION.md`. Operational rules such as setup offsets, pickup/cleanup calculations, breakdown stations, equipment quantities, OPS API writes, and food-only visibility filtering are deliberately excluded from the extraction core.
