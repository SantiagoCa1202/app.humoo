<?php

return [
    'default' => env('AI_PROVIDER', 'rule_based'),
    'max_orchestration_iterations' => (int) env('AI_MAX_ORCHESTRATION_ITERATIONS', 3),
    'max_tool_calls_per_turn' => (int) env('AI_MAX_TOOL_CALLS_PER_TURN', 4),
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
            'model' => env('OPENAI_MODEL', 'gpt-5'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 30),
        ],
    ],
];
