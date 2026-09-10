<?php

return [
    // A read followed by an exact detail lookup needs one additional provider
    // turn to produce the final remote component response.
    'max_orchestration_iterations' => (int) env('AI_MAX_ORCHESTRATION_ITERATIONS', 12),
    'max_tool_calls_per_turn' => (int) env('AI_MAX_TOOL_CALLS_PER_TURN', 12),
    'chat' => [
        'max_message_chars' => (int) env('AI_CHAT_MAX_MESSAGE_CHARS', 16000),
    ],
    'chat_streaming_enabled' => filter_var(env('AI_CHAT_STREAMING_ENABLED', true), FILTER_VALIDATE_BOOL),
    'deadlines' => [
        'run_seconds' => (int) env('AI_RUN_DEADLINE_SECONDS', 540),
        'continuation_seconds' => (int) env('AI_CONTINUATION_DEADLINE_SECONDS', 540),
        'worker_stale_seconds' => (int) env('AI_WORKER_STALE_SECONDS', 150),
        'tool_seconds' => (int) env('AI_TOOL_TIMEOUT_SECONDS', 90),
        'batch_seconds' => (int) env('AI_BATCH_TIMEOUT_SECONDS', 110),
    ],
    'context' => [
        'max_serialized_characters' => (int) env('AI_CONTEXT_MAX_SERIALIZED_CHARACTERS', 60000),
        'max_snapshot_results' => (int) env('AI_CONTEXT_MAX_SNAPSHOT_RESULTS', 20),
    ],
    'entity_resolution' => [
        'candidate_limit' => (int) env('AI_ENTITY_RESOLUTION_CANDIDATE_LIMIT', 40),
        'read_threshold' => (float) env('AI_ENTITY_RESOLUTION_READ_THRESHOLD', 0.76),
        'write_threshold' => (float) env('AI_ENTITY_RESOLUTION_WRITE_THRESHOLD', 0.90),
        'minimum_score_gap' => (float) env('AI_ENTITY_RESOLUTION_MINIMUM_SCORE_GAP', 0.08),
    ],
    'prompt_version' => env('AI_PROMPT_VERSION', 'humoo-chat-v1'),
    'temporal' => [
        'fallback_timezone' => env('AI_FALLBACK_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
    ],
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1/responses'),
            'connect_timeout_seconds' => (int) env('OPENAI_CONNECT_TIMEOUT_SECONDS', 10),
            'debug_log_max_characters' => (int) env('AI_PROVIDER_DEBUG_LOG_MAX_CHARACTERS', 100000),
            'debug_logging' => (bool) env('AI_PROVIDER_DEBUG_LOGGING', false),
            'conversations_base_url' => env('OPENAI_CONVERSATIONS_URL', 'https://api.openai.com/v1/conversations'),
            'prompt_cache_key' => env('AI_PROMPT_CACHE_KEY', 'humoo-agent-v1'),
            'prompt_cache_ttl' => env('AI_PROMPT_CACHE_TTL', '30m'),
            'include_encrypted_reasoning' => (bool) env('AI_INCLUDE_ENCRYPTED_REASONING', true),
            'model' => env('OPENAI_MODEL', 'gpt-5'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
        ],
    ],
    'tool_profiles' => [
        'enabled' => (bool) env('AI_TOOL_PROFILES_ENABLED', true),
    ],
    'retry_budgets' => [
        'structural_plan_repairs' => (int) env('AI_STRUCTURAL_PLAN_MAX_REPAIRS', 1),
        'tool_argument_repairs' => (int) env('AI_TOOL_ARGUMENT_MAX_REPAIRS', 1),
        'provider_transient_retries' => (int) env('AI_PROVIDER_TRANSIENT_MAX_RETRIES', 1),
        'provider_transient_backoff_ms' => (int) env('AI_PROVIDER_TRANSIENT_BACKOFF_MS', 1500),
        'provider_transient_max_backoff_ms' => (int) env('AI_PROVIDER_TRANSIENT_MAX_BACKOFF_MS', 5000),
        'provider_retry_after_max_seconds' => (int) env('AI_PROVIDER_RETRY_AFTER_MAX_SECONDS', 60),
    ],
    'conversations' => [
        'recovery_delay_seconds' => (int) env('AI_CONVERSATION_RECOVERY_DELAY_SECONDS', 45),
        'enabled' => (bool) env('AI_CONVERSATIONS_ENABLED', true),
        'bootstrap_message_limit' => (int) env('AI_CONVERSATION_BOOTSTRAP_MESSAGE_LIMIT', 8),
        'compaction_enabled' => (bool) env('AI_CONVERSATION_COMPACTION_ENABLED', true),
        'compact_threshold' => (int) env('AI_CONVERSATION_COMPACT_THRESHOLD', 60000),
    ],
];
