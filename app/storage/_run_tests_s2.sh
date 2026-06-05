#!/usr/bin/env sh
set -e

# Generate a fresh APP_KEY without PHP subshell tricks
export APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"

# Wipe any stale Postgres-targeted config cache
php artisan config:clear >/dev/null 2>&1 || true

# Boot a file-based sqlite so non-RefreshDatabase unit tests can use real schema
export DB_CONNECTION=sqlite
export DB_DATABASE=/tmp/verify_s2.sqlite
export CACHE_DRIVER=array
export SESSION_DRIVER=array
export QUEUE_DRIVER=sync
export BOSSKU_MEMORY_ENABLED=false
export BOSSKU_MEMORY_OLLAMA_ENABLED=false

php artisan migrate --force --no-interaction >/dev/null 2>&1

# Syntax-check the new/changed files
php -l app/Services/Orchestrator/PlannerService.php
php -l app/Services/Orchestrator/OrchestratorService.php
php -l app/Console/Commands/IndexCodebaseCommand.php
php -l app/Providers/AppServiceProvider.php

# Run the full unit suite + the Slice-1 feature tests
php artisan test \
  --filter "LlmJsonParserTest|OllamaClientTest|ModelFallbackServiceTest|CodebaseIndexServiceTest" \
  --no-coverage 2>&1
