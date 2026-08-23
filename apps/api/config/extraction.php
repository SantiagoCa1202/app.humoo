<?php

return [
    'contract_path' => env(
        'BEO_EXTRACTION_CONTRACT_PATH',
        base_path('../../contracts/beo-extraction/v1')
    ),
    'schema_version' => env('BEO_EXTRACTION_SCHEMA_VERSION', '1.0.0'),
    'prompt_version' => env('BEO_EXTRACTION_PROMPT_VERSION', '65.0.0'),
    'provider' => env('BEO_EXTRACTION_PROVIDER', 'humoo-beo-extractor'),
    'worker_id' => env('BEO_EXTRACTOR_WORKER_ID'),
    'worker_token' => env('BEO_EXTRACTOR_WORKER_TOKEN'),
    'lease_seconds' => (int) env('BEO_EXTRACTOR_JOB_LEASE_SECONDS', 300),
    'max_attempts' => (int) env('BEO_EXTRACTOR_MAX_ATTEMPTS', 3),
];
