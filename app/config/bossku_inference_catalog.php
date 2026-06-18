<?php

/**
 * Curated cloud-only inference model catalog.
 * Source: Artificial Analysis Intelligence Index v4.1 + provider docs (June 2026).
 * Refresh via `php artisan bosskuai:sync-inference-catalog`.
 */
return [
    'version' => '2026-06-19',
    'source' => 'artificialanalysis.ai + provider docs',

    'providers' => [
        'ollama-cloud' => [
            'name' => 'Ollama Cloud',
            'type' => 'ollama',
            'auth' => 'api_key',
            'base_url' => 'https://ollama.com',
            'api_key_env' => 'OLLAMA_API_KEY',
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'type' => 'anthropic',
            'auth' => 'api_key',
            'base_url' => 'https://api.anthropic.com',
            'api_key_env' => 'ANTHROPIC_API_KEY',
        ],
        'openai' => [
            'name' => 'OpenAI',
            'type' => 'openai_compatible',
            'auth' => 'api_key',
            'base_url' => 'https://api.openai.com',
            'api_key_env' => 'OPENAI_API_KEY',
        ],
        'codex' => [
            'name' => 'Codex (ChatGPT)',
            'type' => 'codex_oauth',
            'auth' => 'oauth',
            'base_url' => 'https://api.openai.com',
        ],
        'deepseek' => [
            'name' => 'DeepSeek',
            'type' => 'openai_compatible',
            'auth' => 'api_key',
            'base_url' => 'https://api.deepseek.com',
            'api_key_env' => 'DEEPSEEK_API_KEY',
        ],
        'moonshot' => [
            'name' => 'Kimi (Moonshot)',
            'type' => 'openai_compatible',
            'auth' => 'api_key',
            'base_url' => 'https://api.moonshot.ai/v1',
            'api_key_env' => 'MOONSHOT_API_KEY',
        ],
        'zai' => [
            'name' => 'GLM (Z.ai)',
            'type' => 'openai_compatible',
            'auth' => 'api_key',
            'base_url' => 'https://api.z.ai/api/paas/v4',
            'api_key_env' => 'ZHIPU_API_KEY',
        ],
        'dashscope' => [
            'name' => 'Qwen (DashScope)',
            'type' => 'openai_compatible',
            'auth' => 'api_key',
            'base_url' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
            'api_key_env' => 'DASHSCOPE_API_KEY',
        ],
        'openrouter' => [
            'name' => 'OpenRouter',
            'type' => 'openai_compatible',
            'auth' => 'api_key',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key_env' => 'OPENROUTER_API_KEY',
        ],
    ],

    'models' => [
        // ── Ollama Cloud ──────────────────────────────────────────────────────
        ['provider' => 'ollama-cloud', 'id' => 'kimi-k2.6:cloud', 'label' => 'Kimi K2.6 (Cloud)', 'reasoning' => 43, 'coding' => 72, 'speed' => 80, 'cost' => 30, 'roles' => ['orchestrator', 'planner', 'router', 'writer', 'direct_answer']],
        ['provider' => 'ollama-cloud', 'id' => 'kimi-k2.7-code:cloud', 'label' => 'Kimi K2.7 Code (Cloud)', 'reasoning' => 45, 'coding' => 88, 'speed' => 70, 'cost' => 35, 'roles' => ['executor', 'coder']],
        ['provider' => 'ollama-cloud', 'id' => 'glm-5.2:cloud', 'label' => 'GLM 5.2 (Cloud)', 'reasoning' => 51, 'coding' => 78, 'speed' => 65, 'cost' => 25, 'roles' => ['orchestrator', 'planner', 'executor', 'coder', 'auditor']],
        ['provider' => 'ollama-cloud', 'id' => 'glm-5.1:cloud', 'label' => 'GLM 5.1 (Cloud)', 'reasoning' => 40, 'coding' => 70, 'speed' => 70, 'cost' => 28, 'roles' => ['executor', 'coder', 'orchestrator']],
        ['provider' => 'ollama-cloud', 'id' => 'deepseek-v4-pro:cloud', 'label' => 'DeepSeek V4 Pro (Cloud)', 'reasoning' => 44, 'coding' => 75, 'speed' => 60, 'cost' => 30, 'roles' => ['auditor', 'final_reviewer', 'security_auditor', 'executor']],
        ['provider' => 'ollama-cloud', 'id' => 'qwen3.5:cloud', 'label' => 'Qwen 3.5 (Cloud)', 'reasoning' => 42, 'coding' => 68, 'speed' => 75, 'cost' => 28, 'roles' => ['executor', 'coder', 'router']],
        ['provider' => 'ollama-cloud', 'id' => 'qwen3-coder-next:cloud', 'label' => 'Qwen3 Coder Next (Cloud)', 'reasoning' => 38, 'coding' => 82, 'speed' => 72, 'cost' => 32, 'roles' => ['executor', 'coder']],
        ['provider' => 'ollama-cloud', 'id' => 'minimax-m3:cloud', 'label' => 'MiniMax M3 (Cloud)', 'reasoning' => 44, 'coding' => 65, 'speed' => 78, 'cost' => 22, 'roles' => ['router', 'direct_answer', 'writer']],

        // ── Anthropic ─────────────────────────────────────────────────────────
        ['provider' => 'anthropic', 'id' => 'claude-opus-4-8', 'label' => 'Claude Opus 4.8', 'reasoning' => 56, 'coding' => 73, 'speed' => 50, 'cost' => 15, 'roles' => ['orchestrator', 'planner', 'auditor', 'final_reviewer', 'security_auditor']],
        ['provider' => 'anthropic', 'id' => 'claude-sonnet-4-6', 'label' => 'Claude Sonnet 4.6', 'reasoning' => 48, 'coding' => 68, 'speed' => 70, 'cost' => 40, 'roles' => ['executor', 'coder', 'writer', 'orchestrator']],
        ['provider' => 'anthropic', 'id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5', 'reasoning' => 35, 'coding' => 55, 'speed' => 90, 'cost' => 80, 'roles' => ['router', 'direct_answer']],
        ['provider' => 'anthropic', 'id' => 'claude-fable-5', 'label' => 'Claude Fable 5 (unavailable)', 'reasoning' => 60, 'coding' => 77, 'speed' => 45, 'cost' => 10, 'roles' => ['orchestrator', 'planner'], 'available' => false],

        // ── OpenAI API key ────────────────────────────────────────────────────
        ['provider' => 'openai', 'id' => 'gpt-5.5', 'label' => 'GPT-5.5', 'reasoning' => 55, 'coding' => 76, 'speed' => 55, 'cost' => 18, 'roles' => ['orchestrator', 'planner', 'executor', 'coder', 'auditor']],
        ['provider' => 'openai', 'id' => 'gpt-5.4-mini', 'label' => 'GPT-5.4 mini', 'reasoning' => 40, 'coding' => 60, 'speed' => 85, 'cost' => 70, 'roles' => ['router', 'direct_answer', 'writer']],
        ['provider' => 'openai', 'id' => 'gpt-5.4-nano', 'label' => 'GPT-5.4 nano', 'reasoning' => 30, 'coding' => 50, 'speed' => 95, 'cost' => 90, 'roles' => ['router', 'direct_answer']],

        // ── Codex OAuth ───────────────────────────────────────────────────────
        ['provider' => 'codex', 'id' => 'gpt-5.5', 'label' => 'GPT-5.5 (Codex)', 'reasoning' => 55, 'coding' => 76, 'speed' => 55, 'cost' => 0, 'roles' => ['orchestrator', 'planner', 'executor', 'coder']],
        ['provider' => 'codex', 'id' => 'gpt-4o', 'label' => 'GPT-4o (Codex)', 'reasoning' => 42, 'coding' => 65, 'speed' => 70, 'cost' => 0, 'roles' => ['executor', 'coder', 'writer']],
        ['provider' => 'codex', 'id' => 'o3', 'label' => 'o3 (Codex)', 'reasoning' => 50, 'coding' => 70, 'speed' => 60, 'cost' => 0, 'roles' => ['orchestrator', 'planner', 'auditor']],

        // ── DeepSeek ──────────────────────────────────────────────────────────
        ['provider' => 'deepseek', 'id' => 'deepseek-v4-pro', 'label' => 'DeepSeek V4 Pro', 'reasoning' => 44, 'coding' => 75, 'speed' => 60, 'cost' => 35, 'roles' => ['auditor', 'final_reviewer', 'security_auditor', 'orchestrator']],
        ['provider' => 'deepseek', 'id' => 'deepseek-v4-flash', 'label' => 'DeepSeek V4 Flash', 'reasoning' => 35, 'coding' => 62, 'speed' => 85, 'cost' => 75, 'roles' => ['router', 'direct_answer', 'writer', 'executor']],

        // ── Kimi / Moonshot ───────────────────────────────────────────────────
        ['provider' => 'moonshot', 'id' => 'kimi-k2.7-code', 'label' => 'Kimi K2.7 Code', 'reasoning' => 45, 'coding' => 88, 'speed' => 70, 'cost' => 35, 'roles' => ['executor', 'coder']],
        ['provider' => 'moonshot', 'id' => 'kimi-k2.7-code-highspeed', 'label' => 'Kimi K2.7 Code HighSpeed', 'reasoning' => 42, 'coding' => 82, 'speed' => 90, 'cost' => 40, 'roles' => ['executor', 'coder']],
        ['provider' => 'moonshot', 'id' => 'kimi-k2.6', 'label' => 'Kimi K2.6', 'reasoning' => 43, 'coding' => 72, 'speed' => 75, 'cost' => 38, 'roles' => ['orchestrator', 'planner', 'router', 'writer']],

        // ── GLM / Z.ai ────────────────────────────────────────────────────────
        ['provider' => 'zai', 'id' => 'glm-5.2', 'label' => 'GLM 5.2', 'reasoning' => 51, 'coding' => 78, 'speed' => 65, 'cost' => 30, 'roles' => ['orchestrator', 'planner', 'executor', 'coder', 'auditor']],
        ['provider' => 'zai', 'id' => 'glm-5.1', 'label' => 'GLM 5.1', 'reasoning' => 40, 'coding' => 70, 'speed' => 70, 'cost' => 35, 'roles' => ['executor', 'coder', 'orchestrator']],
        ['provider' => 'zai', 'id' => 'glm-4.7', 'label' => 'GLM 4.7', 'reasoning' => 35, 'coding' => 60, 'speed' => 80, 'cost' => 60, 'roles' => ['router', 'direct_answer', 'writer']],

        // ── Qwen / DashScope ──────────────────────────────────────────────────
        ['provider' => 'dashscope', 'id' => 'qwen3.7-max', 'label' => 'Qwen 3.7 Max', 'reasoning' => 48, 'coding' => 70, 'speed' => 60, 'cost' => 25, 'roles' => ['orchestrator', 'planner', 'auditor']],
        ['provider' => 'dashscope', 'id' => 'qwen3.7-plus', 'label' => 'Qwen 3.7 Plus', 'reasoning' => 42, 'coding' => 68, 'speed' => 70, 'cost' => 40, 'roles' => ['executor', 'coder', 'writer']],
        ['provider' => 'dashscope', 'id' => 'qwen3-coder-plus', 'label' => 'Qwen3 Coder Plus', 'reasoning' => 38, 'coding' => 82, 'speed' => 72, 'cost' => 38, 'roles' => ['executor', 'coder']],
    ],

    'role_profiles' => [
        'router' => ['reasoning' => 0.2, 'coding' => 0.1, 'speed' => 0.5, 'cost' => 0.2],
        'direct_answer' => ['reasoning' => 0.2, 'coding' => 0.1, 'speed' => 0.5, 'cost' => 0.2],
        'planner' => ['reasoning' => 0.6, 'coding' => 0.2, 'speed' => 0.1, 'cost' => 0.1],
        'orchestrator' => ['reasoning' => 0.6, 'coding' => 0.2, 'speed' => 0.1, 'cost' => 0.1],
        'executor' => ['reasoning' => 0.2, 'coding' => 0.6, 'speed' => 0.1, 'cost' => 0.1],
        'coder' => ['reasoning' => 0.2, 'coding' => 0.6, 'speed' => 0.1, 'cost' => 0.1],
        'auditor' => ['reasoning' => 0.5, 'coding' => 0.3, 'speed' => 0.1, 'cost' => 0.1],
        'security_auditor' => ['reasoning' => 0.5, 'coding' => 0.3, 'speed' => 0.1, 'cost' => 0.1],
        'final_reviewer' => ['reasoning' => 0.5, 'coding' => 0.3, 'speed' => 0.1, 'cost' => 0.1],
        'writer' => ['reasoning' => 0.3, 'coding' => 0.1, 'speed' => 0.3, 'cost' => 0.3],
        'council' => ['reasoning' => 0.4, 'coding' => 0.3, 'speed' => 0.1, 'cost' => 0.2],
    ],
];
