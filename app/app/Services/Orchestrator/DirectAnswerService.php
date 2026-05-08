<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;

class DirectAnswerService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback
    ) {}

    public function answer(string $userPrompt, array $routeContext): string
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
        $out = $this->fallback->chatWithFallbacks(
            $models,
            $messages,
            (float) ($cfg['temperature'] ?? 0.2),
            $retry,
            'direct_answer',
            null
        );

        return $out['text'];
    }
}
