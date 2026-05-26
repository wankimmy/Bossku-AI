<?php

return [
    /**
     * Optional API token (no login). Off by default for easy OSS local setup.
     * Enable when exposing Bossku to the public internet.
     */
    'api_auth_enabled' => env('BOSSKU_API_AUTH_ENABLED', false),
    'api_token' => env('BOSSKU_API_TOKEN', ''),
    'runs_rate_per_minute' => (int) env('BOSSKU_RUNS_RATE_PER_MINUTE', 60),

    'repo_root' => env('BOSSKU_REPO_PATH') ?: (dirname((string) base_path())),
    'knowledge_import_paths' => [
        'codex' => array_values(array_filter(explode(PATH_SEPARATOR, (string) env('BOSSKU_CODEX_MEMORY_PATHS', '')))),
        'claude' => array_values(array_filter(explode(PATH_SEPARATOR, (string) env('BOSSKU_CLAUDE_MEMORY_PATHS', '')))),
    ],
    'workspace_mount' => env('BOSSKU_WORKSPACE_MOUNT', '/workspace'),
    'workspace_host_prefix' => env('BOSSKU_WORKSPACE_HOST_PREFIX', ''),
    'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'ollama_api_key' => env('OLLAMA_API_KEY'),
    'max_revision_rounds' => env('BOSSKUAI_MAX_REVISION_ROUNDS', 1),
    /** Max executor re-proposal rounds after user code-review "request changes" on approvals. */
    'max_approval_review_rounds' => (int) env('BOSSKU_MAX_APPROVAL_REVIEW_ROUNDS', 3),
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
    /**
     * When true, each executor file change and git command requires user approve/reject
     * (with optional comment) before applying; the run pauses until all are decided.
     */
    'require_user_approval_before_apply' => env('BOSSKU_REQUIRE_USER_APPROVAL', true),
    /**
     * When false (default), allowlisted commands_run run immediately while file writes still use the approval modal.
     * Only applies when require_user_approval_before_apply is true.
     */
    'require_user_approval_for_commands' => env('BOSSKU_REQUIRE_USER_APPROVAL_FOR_COMMANDS', false),
    /**
     * Docker only: when true, entrypoint chmods /workspace so www-data can auto-apply file writes.
     */
    'workspace_writable' => env('BOSSKU_WORKSPACE_WRITABLE', true),
    /**
     * When true, executor commands_run entries that match allowlisted project commands
     * are executed in the active project repo (git, docker compose, php artisan, tests).
     */
    'auto_execute_project_commands' => env('BOSSKU_AUTO_EXECUTE_PROJECT_COMMANDS', env('BOSSKU_AUTO_EXECUTE_GIT_COMMANDS', true)),
    /** @deprecated Use auto_execute_project_commands */
    'auto_execute_git_commands' => env('BOSSKU_AUTO_EXECUTE_GIT_COMMANDS', true),
    'allow_docker_compose_commands' => env('BOSSKU_ALLOW_DOCKER_COMPOSE', true),
    'project_command_timeout_seconds' => (int) env('BOSSKU_PROJECT_COMMAND_TIMEOUT', 300),
    /** @deprecated Use project_command_timeout_seconds */
    'git_command_timeout_seconds' => (int) env('BOSSKU_GIT_COMMAND_TIMEOUT', 60),
    'project_command_max_output_chars' => (int) env('BOSSKU_PROJECT_COMMAND_MAX_OUTPUT', 32768),
    'security_audit_preview_max_files' => (int) env('BOSSKU_SECURITY_PREVIEW_MAX_FILES', 10),
    'audit_preview_chars' => (int) env('BOSSKU_AUDIT_PREVIEW_CHARS', 800),

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
    /**
     * Documented default pipeline name. Routing heuristics (DeterministicTaskClassifier / PromptRouteClassifier) remain primary.
     */
    'default_workflow' => env('BOSSKU_DEFAULT_WORKFLOW', 'orchestrator_executor'),
    /**
     * Pre-execution clarification: smart (default), always (every run), or off.
     */
    'orchestrator_clarification_mode' => env('BOSSKU_ORCHESTRATOR_CLARIFICATION_MODE', 'smart'),
];
