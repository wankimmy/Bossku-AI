<?php

/**
 * Central BosskuAI model routing configuration.
 * All chat models resolve to Ollama Cloud tags via bossku_models.aliases.
 */
return [
    'router' => [
        'primary' => env('BOSSKU_ROUTER_MODEL', 'kimi-k2.6'),
        'fallback' => ['glm-5.1', 'deepseek-v4-pro'],
        'enabled' => filter_var(env('BOSSKU_ROUTER_LLM_ENABLED', true), FILTER_VALIDATE_BOOL),
        'max_context_files' => 0,
        'max_tokens' => 2000,
        'temperature' => 0.1,
        'timeout_seconds' => 60,
        'retry_count' => 1,
        'purpose' => [
            'classify_task',
            'detect_skill',
            'detect_risk',
            'decide_workflow',
            'estimate_token_level',
        ],
    ],

    'orchestrator' => [
        'primary' => env('BOSSKU_ORCHESTRATOR_MODEL', 'kimi-k2.6'),
        'fallback' => ['glm-5.1', 'deepseek-v4-pro'],
        'enabled' => true,
        'max_context_files' => 20,
        'max_tokens' => 8000,
        'temperature' => 0.2,
        'timeout_seconds' => 180,
        'retry_count' => 1,
        'rules' => [
            'create_target_file_list_first',
            'do_not_scan_entire_repo_unless_needed',
            'pass_only_relevant_context_to_executor',
            'produce_execution_plan_before_executor_runs',
            'estimate_context_budget_before_executor_runs',
        ],
    ],

    'executor' => [
        'default' => [
            'primary' => env('BOSSKU_EXECUTOR_DEFAULT_MODEL', 'glm-5.1'),
            'fallback' => ['kimi-k2.6', 'deepseek-v4-pro'],
            'max_context_files' => 15,
            'max_tokens' => 12000,
            'temperature' => 0.2,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'frontend_ui' => [
            'primary' => env('BOSSKU_EXECUTOR_FRONTEND_MODEL', 'glm-5.1'),
            'fallback' => ['kimi-k2.6', 'deepseek-v4-pro'],
            'max_context_files' => 20,
            'max_tokens' => 14000,
            'temperature' => 0.25,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'backend' => [
            'primary' => env('BOSSKU_EXECUTOR_BACKEND_MODEL', 'glm-5.1'),
            'fallback' => ['kimi-k2.6', 'deepseek-v4-pro'],
            'max_context_files' => 15,
            'max_tokens' => 12000,
            'temperature' => 0.2,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'devops' => [
            'primary' => env('BOSSKU_EXECUTOR_DEVOPS_MODEL', 'glm-5.1'),
            'fallback' => ['kimi-k2.6', 'deepseek-v4-pro'],
            'max_context_files' => 20,
            'max_tokens' => 14000,
            'temperature' => 0.1,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'high_risk' => [
            'primary' => env('BOSSKU_EXECUTOR_HIGH_RISK_MODEL', 'deepseek-v4-pro'),
            'fallback' => ['glm-5.1', 'kimi-k2.6'],
            'max_context_files' => 25,
            'max_tokens' => 16000,
            'temperature' => 0.1,
            'timeout_seconds' => 360,
            'retry_count' => 1,
        ],
        'rules' => [
            'only_read_target_files',
            'avoid_full_repo_scan',
            'avoid_unrelated_refactor',
            'run_related_tests_only',
            'summarize_patch_for_auditor',
            'never_expose_secrets',
            'never_modify_unrelated_files',
        ],
    ],

    'auditor' => [
        'primary' => env('BOSSKU_AUDITOR_MODEL', 'deepseek-v4-pro'),
        'fallback' => ['glm-5.1', 'kimi-k2.6'],
        'enabled' => true,
        'max_context_files' => 10,
        'max_tokens' => 10000,
        'temperature' => 0.1,
        'timeout_seconds' => 240,
        'retry_count' => 1,
        'run_when' => [
            'code_changed', 'migration_changed', 'auth_changed', 'payment_changed',
            'deployment_changed', 'security_changed', 'medium_risk', 'high_risk',
        ],
    ],

    'security_auditor' => [
        'primary' => env('BOSSKU_SECURITY_AUDITOR_MODEL', 'deepseek-v4-pro'),
        'fallback' => ['glm-5.1', 'kimi-k2.6'],
        'enabled' => true,
        'max_context_files' => 15,
        'max_tokens' => 12000,
        'temperature' => 0.1,
        'timeout_seconds' => 240,
        'retry_count' => 1,
        'run_when' => [
            'authentication', 'authorization', 'payment', 'billing', 'secrets',
            'production_config', 'database_migration', 'user_data', 'file_upload',
            'webhook', 'api_key', 'password', 'token', 'policy', 'permission',
        ],
    ],

    'final_reviewer' => [
        'enabled' => 'conditional',
        'primary' => env('BOSSKU_FINAL_REVIEWER_MODEL', 'deepseek-v4-pro'),
        'fallback' => ['glm-5.1', 'kimi-k2.6'],
        'max_context_files' => 8,
        'max_tokens' => 8000,
        'temperature' => 0.1,
        'timeout_seconds' => 180,
        'retry_count' => 1,
        'run_when' => [
            'high_risk', 'payment', 'authentication', 'authorization',
            'database_migration', 'production_deployment', 'large_refactor',
            'multi_module_change', 'security_sensitive',
        ],
        'output_format' => ['merge', 'revise', 'reject'],
    ],

    'writer' => [
        'primary' => env('BOSSKU_WRITER_MODEL', 'kimi-k2.6'),
        'fallback' => ['glm-5.1', 'deepseek-v4-pro'],
        'max_tokens' => 6000,
        'temperature' => 0.3,
        'timeout_seconds' => 120,
        'retry_count' => 1,
    ],

    'direct_answer' => [
        'primary' => env('BOSSKU_DIRECT_ANSWER_MODEL', 'kimi-k2.6'),
        'fallback' => ['glm-5.1', 'deepseek-v4-pro'],
        'max_tokens' => 4000,
        'temperature' => 0.2,
        'timeout_seconds' => 90,
        'retry_count' => 1,
    ],

    /**
     * Logical BosskuAI model ids → Ollama Cloud (or compatible) ids.
     */
    'aliases' => [
        // Legacy logical labels → Ollama Cloud (stale env cannot open external GPT/Anthropic).
        'gpt-5.5' => env('BOSSKU_ALIAS_GPT_5_5', 'kimi-k2.6:cloud'),
        'gpt-5.5-instant' => env('BOSSKU_ALIAS_GPT_5_5_INSTANT', 'kimi-k2.6:cloud'),
        'gpt-5.4' => env('BOSSKU_ALIAS_GPT_5_4', 'kimi-k2.6:cloud'),

        'claude-opus-4.7' => env('BOSSKU_ALIAS_CLAUDE_OPUS_4_7', 'deepseek-v4-pro:cloud'),
        'claude-sonnet-4.6' => env('BOSSKU_ALIAS_CLAUDE_SONNET_4_6', 'glm-5.1:cloud'),

        // Primary roles (Ollama Cloud).
        'kimi-k2.6' => env('BOSSKU_ALIAS_KIMI_K2_6', 'kimi-k2.6:cloud'),
        'deepseek-v4-pro' => env('BOSSKU_ALIAS_DEEPSEEK_V4_PRO', 'deepseek-v4-pro:cloud'),
        'deepseek-v4-flash' => env('BOSSKU_ALIAS_DEEPSEEK_V4_FLASH', 'glm-5.1:cloud'),
        'qwen-3-coder' => env('BOSSKU_ALIAS_QWEN_3_CODER', 'glm-5.1:cloud'),

        'glm-5.1' => env('BOSSKU_ALIAS_GLM_5_1', 'glm-5.1:cloud'),
        'gemini-3-pro' => env('BOSSKU_ALIAS_GEMINI_3_PRO', 'kimi-k2.6:cloud'),
    ],

    /**
     * Map model id string to LLM gateway provider (runtime is Ollama-only).
     */
    'model_providers' => [
        'default' => env('BOSSKU_DEFAULT_LLM_PROVIDER', 'ollama'),
        'ollama_patterns' => explode('|', env('BOSSKU_OLLAMA_MODEL_PATTERNS', 'llama|mistral|codellama|phi|gemma|qwen|kimi|deepseek|glm|gpt|claude|gemini|o1|o3|o4|chatgpt|cloud|nomic')),
    ],
];
