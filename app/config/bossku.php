<?php

return [
    'repo_root' => env('BOSSKU_REPO_PATH') ?: (dirname((string) base_path())),
    'workspace_mount' => env('BOSSKU_WORKSPACE_MOUNT', '/workspace'),
    'workspace_host_prefix' => env('BOSSKU_WORKSPACE_HOST_PREFIX', ''),
    'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'ollama_api_key' => env('OLLAMA_API_KEY'),
    'max_revision_rounds' => env('BOSSKUAI_MAX_REVISION_ROUNDS', 1),
    'show_raw_json' => env('BOSSKUAI_SHOW_RAW_JSON', true),
    /**
     * When false, MemoryService skips Ollama /api/embed and LLM humanize (text fallback only).
     * Overridable via Settings → Ollama & Models. PHPUnit forces this off in phpunit.xml.
     */
    'memory_ollama_enabled' => true,
];
