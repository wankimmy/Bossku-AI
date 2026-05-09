<?php

return [
    'repo_root' => env('BOSSKU_REPO_PATH') ?: (dirname((string) base_path())),
    'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'ollama_api_key' => env('OLLAMA_API_KEY'),
    'ollama_executor_model' => env('OLLAMA_EXECUTOR_MODEL', 'glm-5.1:cloud'),
    'max_revision_rounds' => env('BOSSKUAI_MAX_REVISION_ROUNDS', 1),
    'show_raw_json' => env('BOSSKUAI_SHOW_RAW_JSON', true),
    'ollama_embedding_model' => env('OLLAMA_EMBEDDING_MODEL', env('EMBEDDING_MODEL', 'nomic-embed-text')),
    /**
     * When false, MemoryService skips Ollama /api/embed and LLM humanize (text fallback only).
     * PHPUnit defaults this off via phpunit.xml to avoid outbound HTTP during tests.
     */
    'memory_ollama_enabled' => env('BOSSKU_MEMORY_OLLAMA_ENABLED', true),
];
