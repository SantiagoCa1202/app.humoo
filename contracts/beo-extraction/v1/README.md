# Humoo BEO Extraction Contract V1

This directory is the versioned, transport-agnostic source contract for a future BEO extraction engine. The initial supported version is `1.0.0`; the Laravel validator accepts compatible `1.x.y` versions and rejects unsupported major versions.

The contract deliberately lives outside the database model layer:

```text
document -> extraction job -> extraction result -> Laravel validation/review -> canonical Humoo domain
```

No extractor, queue, OCR provider, AI provider, storage credential, signed URL, or canonical persistence is part of this contract.

## Files

- `extraction-job.schema.json`: input envelope sent to an extractor.
- `extraction-result.schema.json`: source-oriented extraction result.
- `extraction-error.schema.json`: safe structured error envelope.
- `shared.schema.json`: reusable JSON Schema definitions.
- `examples/`: small fixtures for standard, non-food, menu TBD, and partial results.
- `apps/api/app/Data/BeoExtraction/V1/`: Laravel DTOs and the isolated validator.

## Source data versus canonical data

The extractor may echo Laravel-owned `extraction_run_id`, `document_id`, and nullable `import_batch_id`. It must not return workspace, client, contact, property, venue, event, event group, menu, recipe, inventory, prep, task, or membership IDs. The source contract uses stable `source_key` values, source names, source text, and evidence instead.

| Extraction field | Later Laravel destination | Prompt 64 responsibility |
| --- | --- | --- |
| `event_orders[].event_order_number` | `Beo.event_order_number` / event-order review | Preserve source value; no canonical event creation |
| `event_orders[].functions[]` | `EventFunction` and `BeoVersion` review | Preserve every function, including non-food functions |
| `functions[].attendance` | Event function attendance fields | Keep expected, guaranteed, and set counts independent |
| `functions[].venue_candidates` | Property/Venue resolver | Candidate evidence only; no `venue_id` |
| `functions[].menu` and menu items | Extraction review, then future Menu resolution | Keep TBD/partial states and raw quantities |
| dietary/instructions/setup/AV/staffing | Review and later operational domain mapping | Source-level statements only; no Tasks or calculations |
| `event_orders[].references` | Cross-event-order review | Use source EO numbers; never `target_event_order_id` |
| `relevance_signals` | Laravel operational visibility decisions | Do not encode user/workspace hiding in the extractor |
| `source_trace` and `confidence` | `ExtractedField`/review evidence | Retain page and text evidence per field or entity |

## Contract rules

- Every message declares `schema_version` using semver. Major `1` is the supported compatibility boundary.
- `SourceTrace` is first-class and carries document ID, page numbers, source text, extraction method, and confidence.
- Confidence is granular and always bounded from `0` through `1`.
- `EXP`, `GTD`, and `SET` are separate nullable fields; no computed production count exists.
- Pricing quantities and production quantities are separate. Ambiguous text such as `(3) Brownies @ $56 per Dozen` remains in `raw_quantity_text` and `source_text`.
- Dietary categories describe the source statement; a phrase such as `No beef` is not silently promoted to an allergy.
- A `partial` result must declare issues, warnings, or unresolved items. A `completed` result cannot hide an error-severity issue.
- Errors expose safe codes/details only; stack traces, secrets, credentials, and complete signed URLs are forbidden.

## Laravel validation

Use the isolated validator when a future integration receives a payload:

```php
$result = app(\App\Data\BeoExtraction\V1\BeoExtractionContractValidator::class)
    ->validateResult($payload);

// $result is ExtractionResultData; no Event, Menu, Prep, or other model is written.
```

The same validator exposes `validateJob()` and `validateError()`. It checks required envelopes, nested structures, enums, source traces, confidence ranges, quantity preservation, semver major support, and canonical-ID prohibitions before creating typed DTOs.

The JSON Schema files are the wire-format authority; the PHP validator is the Laravel acceptance boundary. They are intentionally not a database dump and do not implement entity resolution.
