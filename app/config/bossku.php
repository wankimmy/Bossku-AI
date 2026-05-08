<?php

return [
    'repo_root' => env('BOSSKU_REPO_PATH') ?: (dirname((string) base_path())),
    'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'),
    'ollama_api_key' => env('OLLAMA_API_KEY'),
    'ollama_executor_model' => env('OLLAMA_EXECUTOR_MODEL', 'llama3'),
    'openai_api_key' => env('OPENAI_API_KEY'),
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
];
