# Legacy migration report

The reference repository `SantiagoCa1202/event_extractor` was inspected read-only. The new package migrates behavior, not its application shell or database assumptions.

| Legacy component | Decision | New destination / reason |
| --- | --- | --- |
| `beo_pipeline/beo_parser.py` | REFACTOR | `app/ingestion.py` and `app/segmentation.py`; keep page order, EO grouping, and revision signals |
| `beo_pipeline/event_detector.py` | REFACTOR | `app/parsers/generic.py`; emit `EventOrder.functions[]`, not a list of operational events |
| `beo_pipeline/menu_extractor.py` | REUSE + REFACTOR | `app/parsers/marriott_sheraton.py`; retain section, dietary, display, passed, and broken-line knowledge |
| `beo_pipeline/menu_normalizer.py` | REUSE + REFACTOR | `app/normalization.py`; preserve raw quantities and source text |
| `beo_pipeline/food_service_filter.py` | REFACTOR | `app/relevance.py`; signals only, never discard non-food functions |
| `beo_pipeline/service_classifier.py` | REUSE + REFACTOR | provider profile function classification |
| `beo_pipeline/traceability.py` | REPLACE | `app/contracts.py` source traces use page numbers, snippets, method, and numeric confidence |
| `revision_artifacts.py` | REFACTOR | source revision metadata and deterministic source keys; Laravel owns canonical lineage |
| `text_extraction.py` | REPLACE | `app/ingestion.py`; portable PyMuPDF reader and provider adapters |
| `production_router.py` | DEFER | Humoo operational rules; production routing is not source extraction |
| `breakdown_rules.py` | DEFER | Humoo operational rules; no derived station/equipment calculations in V1 |
| `equipment_rules.py` | DEFER | Humoo operational rules |
| `timeline_rules.py` | DEFER | source schedule text only; no derived setup/pickup/cleanup timestamps |
| `setup_rules.py` | DEFER | explicit setup source statements only |
| `ops_events_api.py` | OBSOLETE FOR EXTRACTOR CORE | Prompt 66 integration; no API or DB writes here |
| tkinter, PyInstaller, Windows paths, upload shell | OBSOLETE | local CLI and package boundaries replace application-shell concerns |
