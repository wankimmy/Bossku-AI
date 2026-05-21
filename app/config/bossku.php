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
    /**
     * When true, orchestrator executor files_changed and file_write_proposed tool calls
     * are approved and written to the active project without manual Project UI steps.
     */
    'auto_apply_file_writes' => env('BOSSKU_AUTO_APPLY_FILE_WRITES', true),

    /** Directories skipped during recursive repo walk (search, glob, manifest). */
    'skip_dirs' => [
        '.git',
        'node_modules',
        'vendor',
        '.nuxt',
        '.output',
        'dist',
        'build',
        'storage',
        'bootstrap/cache',
    ],
    'max_search_matches' => (int) env('BOSSKU_MAX_SEARCH_MATCHES', 100),
    'max_glob_matches' => (int) env('BOSSKU_MAX_GLOB_MATCHES', 100),
    'max_manifest_paths' => (int) env('BOSSKU_MAX_MANIFEST_PATHS', 5000),
];
