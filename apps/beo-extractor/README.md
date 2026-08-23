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

The job must already be an `ExtractionJobV1` envelope. The CLI checks the local document checksum when the job provides one, does not upload files, and writes only the requested local output. No database, Laravel, R2, queue, login, or network service is used.

`PyMuPDF` is the default reader. OCR, Docling/layout, and ambiguity resolution are adapter boundaries; they are optional and disabled unless configured. The current engine preserves low-text/unknown pages and reports them as evidence or unresolved data instead of silently dropping them.

The canonical schemas live at `contracts/beo-extraction/v1/`. The engine loads those files by repository path. If `jsonschema` is installed (`pip install -e ".[contract]"`), Draft 2020-12 validation runs before a result is returned. A deterministic standard-library contract validator remains available for local development when the optional package is absent.

Legacy migration decisions are recorded in `LEGACY_MIGRATION.md`. Operational rules such as setup offsets, pickup/cleanup calculations, breakdown stations, equipment quantities, OPS API writes, and food-only visibility filtering are deliberately excluded from the extraction core.
