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
    ],
];
