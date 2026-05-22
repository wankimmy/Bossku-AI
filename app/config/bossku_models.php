<?php

/**
 * BosskuAI model routing — static limits, fallbacks, and alias defaults.
 * Role primary models are controlled via Settings → Ollama & Models (bossku_ai_settings), not .env.
 */
return [
    'defaults' => [
        'router' => 'kimi-k2.6',
        'orchestrator' => 'kimi-k2.6',
        'executor_default' => 'qwen3-coder-next',
        'executor_devops' => 'glm-5.1',
        'executor_high_risk' => 'deepseek-v4-pro',
        'auditor' => 'deepseek-v4-pro',
        'embedding' => 'nomic-embed-text',
        'router_llm_enabled' => true,
    ],

    'router' => [
        'primary' => 'kimi-k2.6',
        'fallback' => ['glm-5.1', 'deepseek-v4-pro', 'qwen3-coder-next'],
        'enabled' => true,
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
        'primary' => 'kimi-k2.6',
        'fallback' => ['glm-5.1', 'deepseek-v4-pro', 'qwen3-coder-next'],
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
            'primary' => 'glm-5.1',
            'fallback' => ['deepseek-v4-pro', 'kimi-k2.6', 'qwen3-coder-next'],
            'max_context_files' => 15,
            'max_tokens' => 12000,
            'temperature' => 0.2,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'frontend_ui' => [
            'primary' => 'glm-5.1',
            'fallback' => ['deepseek-v4-pro', 'kimi-k2.6', 'qwen3-coder-next'],
            'max_context_files' => 20,
            'max_tokens' => 14000,
            'temperature' => 0.25,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'backend' => [
            'primary' => 'glm-5.1',
            'fallback' => ['deepseek-v4-pro', 'kimi-k2.6', 'qwen3-coder-next'],
            'max_context_files' => 15,
            'max_tokens' => 12000,
            'temperature' => 0.2,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'devops' => [
            'primary' => 'glm-5.1',
            'fallback' => ['qwen3-coder-next', 'kimi-k2.6', 'deepseek-v4-pro'],
            'max_context_files' => 20,
            'max_tokens' => 14000,
            'temperature' => 0.1,
            'timeout_seconds' => 300,
            'retry_count' => 1,
        ],
        'high_risk' => [
            'primary' => 'deepseek-v4-pro',
            'fallback' => ['qwen3-coder-next', 'glm-5.1', 'kimi-k2.6'],
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
        'primary' => 'deepseek-v4-pro',
        'fallback' => ['glm-5.1', 'kimi-k2.6', 'qwen3-coder-next'],
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
        'primary' => 'deepseek-v4-pro',
        'fallback' => ['glm-5.1', 'kimi-k2.6', 'qwen3-coder-next'],
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
        'primary' => 'deepseek-v4-pro',
        'fallback' => ['glm-5.1', 'kimi-k2.6', 'qwen3-coder-next'],
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
        'primary' => 'kimi-k2.6',
        'fallback' => ['glm-5.1', 'deepseek-v4-pro', 'qwen3-coder-next'],
        'max_tokens' => 6000,
        'temperature' => 0.3,
        'timeout_seconds' => 120,
        'retry_count' => 1,
    ],

    'direct_answer' => [
        'primary' => 'kimi-k2.6',
        'fallback' => ['glm-5.1', 'deepseek-v4-pro', 'qwen3-coder-next'],
        'max_tokens' => 4000,
        'temperature' => 0.2,
        'timeout_seconds' => 90,
        'retry_count' => 1,
    ],

    /**
     * Logical BosskuAI model ids → Ollama Cloud (or compatible) ids.
     * Override per logical id in Settings → Ollama & Models → Cloud aliases.
     */
    'aliases' => [
        'gpt-5.5' => 'kimi-k2.6:cloud',
        'gpt-5.5-instant' => 'kimi-k2.6:cloud',
        'gpt-5.4' => 'kimi-k2.6:cloud',
        'claude-opus-4.7' => 'deepseek-v4-pro:cloud',
        'claude-sonnet-4.6' => 'glm-5.1:cloud',
        'kimi-k2.6' => 'kimi-k2.6:cloud',
        'deepseek-v4-pro' => 'deepseek-v4-pro:cloud',
        'deepseek-v4-flash' => 'glm-5.1:cloud',
        'qwen-3-coder' => 'qwen3-coder-next:cloud',
        'qwen3-coder-next' => 'qwen3-coder-next:cloud',
        'glm-5.1' => 'glm-5.1:cloud',
        'gemini-3-pro' => 'kimi-k2.6:cloud',
    ],

    /** @var list<string> Allowed Ollama Cloud model tags (documentation + validation reference). */
    'allowed_cloud_models' => [
        'kimi-k2.6:cloud',
        'glm-5.1:cloud',
        'deepseek-v4-pro:cloud',
        'qwen3-coder-next:cloud',
    ],

    'model_providers' => [
        'default' => 'ollama',
        'ollama_patterns' => ['llama', 'mistral', 'codellama', 'phi', 'gemma', 'qwen', 'kimi', 'deepseek', 'glm', 'gpt', 'claude', 'gemini', 'o1', 'o3', 'o4', 'chatgpt', 'cloud', 'nomic'],
    ],
];
