# BEO Golden Validation

This directory contains the manifest, runner, and compact reports for the
official February, April, and August 2026 BEO Golden set. The PDFs are private
fixtures and are intentionally excluded from Git.

Set `HUMOO_BEO_GOLDEN_ROOT` to a local directory containing one subdirectory
per manifest `path`. Each directory may contain one PDF; if it contains more
than one, the runner selects the first file matching the manifest pattern.
The checksum in `manifest.json` must be filled only after the supplied PDF has
been verified. A checksum mismatch blocks that document.

Run from `apps/beo-extractor`:

```text
python -m tests.golden.run_golden --phase baseline
python -m tests.golden.run_golden --phase final
```

The runner performs document/page/EO/function/source metrics and validates the
result through the Prompt 64/65 contract validator. It does not fabricate
expectations when a PDF is absent. Human-verified assertions can be added to a
manifest entry under `expectations` after inspecting the real document; they
must remain targeted and structural rather than full JSON snapshots.

The reports deliberately omit raw page text, guest names, contact data, and
full extraction payloads. `BLOCKED` means the official input was unavailable;
it is never treated as a passing Golden result.
