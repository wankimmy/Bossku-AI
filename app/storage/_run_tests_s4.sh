#!/usr/bin/env sh
set -e
export APP_KEY="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
php artisan config:clear >/dev/null 2>&1 || true
export DB_CONNECTION=sqlite DB_DATABASE=/tmp/verify_s4.sqlite CACHE_DRIVER=array SESSION_DRIVER=array QUEUE_DRIVER=sync BOSSKU_MEMORY_ENABLED=false BOSSKU_MEMORY_OLLAMA_ENABLED=false
php artisan migrate --force --no-interaction >/dev/null 2>&1

php -l app/Services/Orchestrator/ExecutorService.php
php -l app/Services/Orchestrator/OrchestratorService.php
php -l app/Services/Orchestrator/ExecutorEvidenceSupport.php

php artisan test \
  --filter "LlmJsonParserTest|OllamaClientTest|ModelFallbackServiceTest|CodebaseIndexServiceTest|AgentConversationTraitTest" \
  --no-coverage 2>&1
