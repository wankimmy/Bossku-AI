#!/usr/bin/env sh
set -e

export APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
php artisan config:clear >/dev/null 2>&1 || true

export DB_CONNECTION=sqlite
export DB_DATABASE=/tmp/verify_s3.sqlite
export CACHE_DRIVER=array
export SESSION_DRIVER=array
export QUEUE_DRIVER=sync
export BOSSKU_MEMORY_ENABLED=false
export BOSSKU_MEMORY_OLLAMA_ENABLED=false

php artisan migrate --force --no-interaction >/dev/null 2>&1

# Syntax check all changed files
php -l app/Services/Orchestrator/AgentConversationTrait.php
php -l app/Services/Orchestrator/PlannerService.php
php -l app/Services/Orchestrator/ExecutorService.php
php -l app/Services/Orchestrator/AuditorService.php
php -l app/Services/Llm/Providers/AnthropicProvider.php

# Full targeted suite (all slices)
php artisan test \
  --filter "LlmJsonParserTest|OllamaClientTest|ModelFallbackServiceTest|CodebaseIndexServiceTest" \
  --no-coverage 2>&1
