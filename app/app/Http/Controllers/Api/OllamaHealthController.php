<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BosskuAi\LlmErrorFormatter;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Llm\OllamaClient;
use Illuminate\Http\JsonResponse;

class OllamaHealthController extends Controller
{
    public function __invoke(OllamaClient $ollama, RuntimeSettings $settings): JsonResponse
    {
        $aliases = $settings->modelAliases();
        $model = $aliases['kimi-k2.6'] ?? 'kimi-k2.6:cloud';

        try {
            $out = $ollama->chatWithUsage($model, [
                ['role' => 'user', 'content' => 'Reply with exactly: ok'],
            ], 0.0);

            return response()->json([
                'status' => 'ok',
                'model' => $model,
                'base_url' => $settings->ollamaBaseUrl(),
                'preview' => mb_substr(trim($out['text']), 0, 120),
            ]);
        } catch (\Throwable $e) {
            $message = LlmErrorFormatter::humanize($e->getMessage());

            return response()->json([
                'status' => 'error',
                'model' => $model,
                'base_url' => $settings->ollamaBaseUrl(),
                'message' => $message,
                'hint' => str_contains($message, 'subscription')
                    ? 'Upgrade at https://ollama.com/upgrade and ensure OLLAMA_API_KEY is from that account.'
                    : 'Check OLLAMA_BASE_URL and OLLAMA_API_KEY in app/.env, then restart: docker compose restart backend',
            ], 503);
        }
    }
}
