# PHPUnit quarantine

## `legacy-semantic-routing`

These tests exercise the retired deterministic semantic router, local language parsers,
or the legacy free-text recipe ingestion pipeline. The normal chat runtime is AI-first:
the model selects registered tools and Laravel validates authorization, tenant scope,
structured inputs, confirmations, and execution.

The default suite excludes this group so obsolete local-language expectations cannot
force production back toward a competing parser/router. The active boundary is covered
by `AiFirstOrchestrationBoundaryTest` and the focused tool-loop, execution-plan,
confirmation, tenant-isolation, and security suites.

The quarantined inventory remains executable for removal archaeology with:

```bash
php artisan test --group legacy-semantic-routing
```

Quarantined files:

- `tests/Unit/Unit/AdvisoryModeTest.php`
- `tests/Unit/Unit/ConversationalEvaluationTest.php`
- `tests/Unit/Unit/MenuDraftParserTest.php`
- `tests/Unit/Unit/RecipeInputIngestionPipelineTest.php`
- `tests/Unit/Unit/RecipeUpdateIntentRoutingTest.php`
- `tests/Unit/Unit/UnsupportedCapabilityProviderTest.php`
- `tests/Feature/Feature/HybridIntentRouterTest.php`
