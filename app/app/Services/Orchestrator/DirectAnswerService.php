<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\LlmErrorFormatter;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;

class DirectAnswerService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback
    ) {}

    public function answer(string $userPrompt, array $routeContext, ?string $runId = null): string
    {
        $cfg = $this->config->directAnswer();
        $primary = (string) ($cfg['primary'] ?? 'gpt-4o-mini');
        $fallbacks = $cfg['fallback'] ?? [];
        $models = array_merge([$primary], is_array($fallbacks) ? $fallbacks : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);
        $messages = [
            ['role' => 'system', 'content' => 'You are BosskuAI. Answer clearly and concisely. No JSON.'],
            ['role' => 'user', 'content' => "Context (JSON):\n".json_encode(['route' => $routeContext, 'question' => $userPrompt])],
        ];
        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                $messages,
                (float) ($cfg['temperature'] ?? 0.2),
                $retry,
                'direct_answer',
                null,
                null,
                $runId,
            );

            return $out['text'];
        } catch (\Throwable $e) {
            return $this->offlineAnswer($userPrompt, LlmErrorFormatter::humanize($e->getMessage()));
        }
    }

    protected function offlineAnswer(string $userPrompt, string $reason): string
    {
        $trimmed = trim($userPrompt);
        if (preg_match('/^(test|ping|hello|hi|hey)\s*[!?.]*$/i', $trimmed)) {
            return "BosskuAI is running. Your prompt \"{$trimmed}\" was received.\n\n"
                ."LLM is unavailable: {$reason}\n\n"
                .'To enable full AI responses: use a local Ollama server (OLLAMA_BASE_URL=http://host.docker.internal:11434) '
                .'or upgrade Ollama Cloud at https://ollama.com/upgrade.';
        }

        return "I could not reach the configured LLM.\n\n{$reason}";
    }
}
