<?php

return [
    'default' => env('AI_PROVIDER', 'openai'),
    'max_orchestration_iterations' => (int) env('AI_MAX_ORCHESTRATION_ITERATIONS', 3),
    'max_tool_calls_per_turn' => (int) env('AI_MAX_TOOL_CALLS_PER_TURN', 4),
    'max_advisory_tool_calls' => (int) env('AI_MAX_ADVISORY_TOOL_CALLS', env('AI_MAX_TOOL_CALLS_PER_TURN', 4)),
    'entity_resolution' => [
        'candidate_limit' => (int) env('AI_ENTITY_RESOLUTION_CANDIDATE_LIMIT', 40),
        'read_threshold' => (float) env('AI_ENTITY_RESOLUTION_READ_THRESHOLD', 0.76),
        'write_threshold' => (float) env('AI_ENTITY_RESOLUTION_WRITE_THRESHOLD', 0.90),
        'minimum_score_gap' => (float) env('AI_ENTITY_RESOLUTION_MINIMUM_SCORE_GAP', 0.08),
    ],
    'semantic_fallback' => [
        'max_attempts' => (int) env('AI_SEMANTIC_FALLBACK_MAX_ATTEMPTS', 1),
        'max_search_variants' => (int) env('AI_SEMANTIC_FALLBACK_MAX_SEARCH_VARIANTS', 3),
        'max_candidates_per_search' => (int) env('AI_SEMANTIC_FALLBACK_MAX_CANDIDATES_PER_SEARCH', 5),
    ],
    'hybrid_router_version' => env('AI_HYBRID_ROUTER_VERSION', 'hybrid-router-v1'),
    'pattern_learning' => [
        'candidate_occurrences' => (int) env('AI_PATTERN_CANDIDATE_OCCURRENCES', 2),
        'validated_occurrences' => (int) env('AI_PATTERN_VALIDATED_OCCURRENCES', 3),
        'active_occurrences' => (int) env('AI_PATTERN_ACTIVE_OCCURRENCES', 5),
        'minimum_confidence' => (float) env('AI_PATTERN_MIN_CONFIDENCE', 0.90),
        'maximum_ambiguity_rate' => (float) env('AI_PATTERN_MAX_AMBIGUITY_RATE', 0.10),
        'active_success_rate' => (float) env('AI_PATTERN_ACTIVE_SUCCESS_RATE', 0.95),
    ],
    'prompt_version' => env('AI_PROMPT_VERSION', 'humoo-chat-v1'),
    'providers' => [
        'rule_based' => [
            'driver' => 'rule_based',
            'model' => env('AI_RULE_BASED_MODEL', 'humoo-rule-based'),
        ],
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1/responses'),
            'connect_timeout_seconds' => (int) env('OPENAI_CONNECT_TIMEOUT_SECONDS', 10),
            'model' => env('OPENAI_MODEL', 'gpt-5'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
        ],
    ],
];
