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
    /**
     * How long Ollama keeps a model loaded in memory after a request. Keeping it warm
     * across the planner→executor→auditor pipeline avoids cold-reload latency between
     * agents (local Ollama). Accepts a duration ("30m"), seconds (int), 0 (unload now),
     * or -1 (keep forever). Empty string = let Ollama use its default.
     */
    'ollama_keep_alive' => env('OLLAMA_KEEP_ALIVE', '30m'),
    /**
     * Context window (num_ctx) for local Ollama models. Default null = do not send
     * (Ollama Cloud sizes this server-side; only set for local models that need a
     * larger window than the Ollama default).
     */
    'ollama_num_ctx' => env('OLLAMA_NUM_CTX') !== null ? (int) env('OLLAMA_NUM_CTX') : null,
    /**
     * Thinking flag for thinking-capable Ollama models. null (default) = omit the param and
     * let Ollama use the per-model default. Set BOSSKU_OLLAMA_THINK=false to suppress
     * chain-of-thought (faster, fewer output tokens — useful when a thinking model returns
     * its answer in `message.thinking` and leaves `message.content` empty).
     */
    'ollama_think' => env('OLLAMA_THINK') !== null ? filter_var(env('OLLAMA_THINK'), FILTER_VALIDATE_BOOLEAN) : null,
    'max_revision_rounds' => env('BOSSKUAI_MAX_REVISION_ROUNDS', 3),
    /**
     * Soft token budget per run (estimated tokens). When exceeded, a warning event is emitted
     * so the Nuxt UI can show an alert. 0 = no limit. Default ~100k tokens.
     */
    'token_budget_per_run' => (int) env('BOSSKU_TOKEN_BUDGET_PER_RUN', 100000),
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
    /**
     * When true, npm/yarn/pnpm/npx commands from commands_run may execute in the active project (Docker-friendly).
     */
    'allow_package_manager_commands' => env('BOSSKU_ALLOW_PACKAGE_MANAGER_COMMANDS', true),
    /**
     * When true, commands may use cwd (or --prefix/--cwd) under BOSSKU_WORKSPACE_MOUNT for sibling repos.
     */
    'allow_workspace_command_paths' => env('BOSSKU_ALLOW_WORKSPACE_COMMAND_PATHS', true),
    /**
     * Extra allowlisted command prefixes (pipe-separated), e.g. "make test|uv run".
     *
     * @var list<string>
     */
    'project_command_extra_prefixes' => array_values(array_filter(array_map(
        'trim',
        explode('|', (string) env('BOSSKU_PROJECT_COMMAND_EXTRA_PREFIXES', '')),
    ))),
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
    /**
     * Post-planner confirmation: always (default), questions (only when planner asks), or off.
     */
    'orchestrator_plan_confirmation_mode' => env('BOSSKU_ORCHESTRATOR_PLAN_CONFIRMATION_MODE', 'always'),

    /**
     * Obsidian vault sync — set these to write run prompts/outputs to your Obsidian vault.
     * Leave blank to disable (no error thrown, writes are silently skipped).
     * In Docker the vault is typically mounted at /workspace/Safwan-Obsidian-Vault via the
     * ../:/workspace volume binding in docker-compose.yml.
     */
    'obsidian_vault_path'    => env('OBSIDIAN_VAULT_PATH', ''),
    'obsidian_project_folder' => env('OBSIDIAN_PROJECT_FOLDER', 'Bossku-AI'),

    /** Auto-promote high-confidence learning events to searchable memory. */
    'learning_auto_promote_enabled' => env('BOSSKU_LEARNING_AUTO_PROMOTE_ENABLED', true),
    'learning_auto_promote_min_confidence' => (float) env('BOSSKU_LEARNING_AUTO_PROMOTE_MIN_CONFIDENCE', 0.85),
    /** @var list<string> */
    'learning_auto_promote_types' => ['pattern', 'preference'],
    'learning_batch_size' => (int) env('BOSSKU_LEARNING_BATCH_SIZE', 50),

    /**
     * URL/YouTube "learn" ingestion chunking. Raise learn_max_chunks for very long
     * articles or video transcripts (default covers ~340k chars ≈ a 3-4 hour talk).
     */
    'learn_chunk_size' => (int) env('BOSSKU_LEARN_CHUNK_SIZE', 1400),
    'learn_chunk_overlap' => (int) env('BOSSKU_LEARN_CHUNK_OVERLAP', 250),
    'learn_max_chunks' => (int) env('BOSSKU_LEARN_MAX_CHUNKS', 300),

    /**
     * Vision model for chat image attachments (Ollama tag, e.g. llava, llava:13b, llama3.2-vision).
     */
    'vision_model' => env('BOSSKU_VISION_MODEL', 'llava'),

    /** Per-run git worktree isolation (AO/Emdash-style). */
    'worktree_enabled' => env('BOSSKU_WORKTREE_ENABLED', true),
    'worktree_auto_provision' => env('BOSSKU_WORKTREE_AUTO_PROVISION', false),
    'worktree_pool_subdir' => env('BOSSKU_WORKTREE_POOL_SUBDIR', '.bossku/worktrees'),
    /** @var list<string> */
    'worktree_preserve_files' => array_filter(array_map('trim', explode(',', env('BOSSKU_WORKTREE_PRESERVE_FILES', '.env.example')))),
    'worktree_cleanup_on_complete' => env('BOSSKU_WORKTREE_CLEANUP_ON_COMPLETE', true),
    'worktree_fail_closed' => env('BOSSKU_WORKTREE_FAIL_CLOSED', true),

    /** Parallel supervisor defaults */
    'supervisor_max_children' => (int) env('BOSSKU_SUPERVISOR_MAX_CHILDREN', 4),
    'supervisor_default_children' => (int) env('BOSSKU_SUPERVISOR_DEFAULT_CHILDREN', 2),
    'supervisor_llm_synthesis' => env('BOSSKU_SUPERVISOR_LLM_SYNTHESIS', false),

    /** Provider CLI runtime */
    'cli_providers_enabled' => env('BOSSKU_CLI_PROVIDERS_ENABLED', true),
    'cli_session_async_default' => env('BOSSKU_CLI_SESSION_ASYNC_DEFAULT', true),
    'cli_session_async_default_windows' => env('BOSSKU_CLI_SESSION_ASYNC_DEFAULT_WINDOWS', false),
    'memory_worktree_scoping' => env('BOSSKU_MEMORY_WORKTREE_SCOPING', true),
    'agent_hook_token' => env('BOSSKU_AGENT_HOOK_TOKEN', ''),
    'agent_hook_allowed_hosts' => env('BOSSKU_AGENT_HOOK_ALLOWED_HOSTS', '127.0.0.1,localhost'),

    /** SCM / reactions (GitHub) */
    'scm_github_token' => env('BOSSKU_GITHUB_TOKEN', env('GITHUB_TOKEN', '')),
    'reactions_poll_interval_seconds' => (int) env('BOSSKU_REACTIONS_POLL_INTERVAL', 60),
    'reactions_poll_batch_size' => (int) env('BOSSKU_REACTIONS_POLL_BATCH_SIZE', 50),
    /** @var list<string> */
    'reactions_watch_statuses' => array_filter(array_map('trim', explode(',', env('BOSSKU_REACTIONS_WATCH_STATUSES', 'running,paused,completed,partial')))),
    'reactions' => [
        'ci_failed' => [
            'auto' => true,
            'action' => 'resume_run',
            'retries' => 2,
            'escalate_after_attempts' => 3,
            'cooldown_seconds' => (int) env('BOSSKU_REACTION_COOLDOWN_SECONDS', 300),
        ],
        'changes_requested' => [
            'auto' => true,
            'action' => 'resume_run',
            'retries' => 2,
            'escalate_after_attempts' => 3,
            'cooldown_seconds' => (int) env('BOSSKU_REACTION_COOLDOWN_SECONDS', 300),
        ],
        'merge_conflict' => [
            'auto' => true,
            'action' => 'notify',
            'retries' => 1,
        ],
        'approved_and_green' => [
            'auto' => false,
            'action' => 'notify',
        ],
        'agent_stuck' => [
            'auto' => false,
            'action' => 'notify',
        ],
    ],

    /** Verified learning promotion */
    'learning_require_verification' => env('BOSSKU_LEARNING_REQUIRE_VERIFICATION', true),
    'learning_verification_commands' => env('BOSSKU_LEARNING_VERIFICATION_COMMANDS', 'php artisan test|npm test|npm run test'),

    /** Remote / BYOI execution */
    'ssh_execution_enabled' => env('BOSSKU_SSH_EXECUTION_ENABLED', false),
    'byoi_enabled' => env('BOSSKU_BYOI_ENABLED', false),

    'attachments' => [
        'max_per_upload' => (int) env('BOSSKU_ATTACHMENTS_MAX_PER_UPLOAD', 10),
        'max_per_run' => (int) env('BOSSKU_ATTACHMENTS_MAX_PER_RUN', 10),
        'max_file_kb' => (int) env('BOSSKU_ATTACHMENTS_MAX_FILE_KB', 10240),
        'max_extracted_chars' => (int) env('BOSSKU_ATTACHMENTS_MAX_EXTRACTED_CHARS', 40000),
        'allowed_mimes' => [
            'text/plain',
            'text/markdown',
            'text/csv',
            'text/html',
            'text/css',
            'text/javascript',
            'text/xml',
            'text/yaml',
            'application/json',
            'application/xml',
            'application/pdf',
            'application/javascript',
            'application/typescript',
            'application/x-yaml',
            'application/sql',
            'application/octet-stream',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
        ],
    ],
];
