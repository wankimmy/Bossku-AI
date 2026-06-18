<?php

return [
    'codex' => [
        'client_id' => env('CODEX_OAUTH_CLIENT_ID', 'app_EMoamEEZ73f0CkXaXp7hrann'),
        'authorize_url' => env('CODEX_OAUTH_AUTHORIZE_URL', 'https://auth.openai.com/oauth/authorize'),
        'token_url' => env('CODEX_OAUTH_TOKEN_URL', 'https://auth.openai.com/oauth/token'),
        'redirect_uri' => env('CODEX_OAUTH_REDIRECT_URI', 'http://localhost:28480/api/oauth/codex/callback'),
        'scopes' => env('CODEX_OAUTH_SCOPES', 'openid profile email offline_access'),
        'frontend_return_url' => env('CODEX_OAUTH_FRONTEND_RETURN_URL', 'http://localhost:28470/settings/models'),
        'api_base_url' => env('CODEX_API_BASE_URL', 'https://api.openai.com'),
    ],

    /** Curated models for Settings dropdown when Codex OAuth is connected. */
    'codex_models' => [
        ['id' => 'gpt-5.5', 'label' => 'GPT-5.5'],
        ['id' => 'gpt-4o', 'label' => 'GPT-4o'],
        ['id' => 'o3', 'label' => 'o3'],
    ],

    'anthropic_models' => [
        ['id' => 'claude-opus-4-8', 'label' => 'Claude Opus 4.8'],
        ['id' => 'claude-sonnet-4-6', 'label' => 'Claude Sonnet 4.6'],
        ['id' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5'],
    ],

    'ollama_cloud_models' => [
        ['id' => 'kimi-k2.6:cloud', 'label' => 'Kimi K2.6 (Cloud)'],
        ['id' => 'kimi-k2.7-code:cloud', 'label' => 'Kimi K2.7 Code (Cloud)'],
        ['id' => 'glm-5.2:cloud', 'label' => 'GLM 5.2 (Cloud)'],
        ['id' => 'glm-5.1:cloud', 'label' => 'GLM 5.1 (Cloud)'],
        ['id' => 'deepseek-v4-pro:cloud', 'label' => 'DeepSeek V4 Pro (Cloud)'],
        ['id' => 'qwen3-coder-next:cloud', 'label' => 'Qwen3 Coder Next (Cloud)'],
        ['id' => 'qwen3.5:cloud', 'label' => 'Qwen 3.5 (Cloud)'],
    ],
];
