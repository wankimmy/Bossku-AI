<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;

class WriterService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback
    ) {}

    public function write(string $userPrompt, array $routeContext, ?string $runId = null): string
    {
        $cfg = $this->config->writer();
        $primary = (string) ($cfg['primary'] ?? 'gpt-4o');
        $fallbacks = $cfg['fallback'] ?? [];
        $models = array_merge([$primary], is_array($fallbacks) ? $fallbacks : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);
        $messages = [
            ['role' => 'system', 'content' => 'You are BosskuAI writer. Produce polished prose or documentation text. No JSON unless user requests code samples inline.'],
            ['role' => 'user', 'content' => "Context (JSON):\n".json_encode(['route' => $routeContext, 'task' => $userPrompt])],
        ];
        $out = $this->fallback->chatWithFallbacks(
            $models,
            $messages,
            (float) ($cfg['temperature'] ?? 0.3),
            $retry,
            'writer',
            null,
            null,
            $runId,
        );

        return $out['text'];
    }
}
