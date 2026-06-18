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
            ['role' => 'system', 'content' => $this->systemPrompt($routeContext)],
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

    protected function systemPrompt(array $routeContext): string
    {
        $skill = (string) ($routeContext['skill'] ?? 'generic');
        $specialist = is_array($routeContext['specialist_agent'] ?? null) ? $routeContext['specialist_agent'] : null;

        if ($specialist !== null && trim((string) ($specialist['persona_content'] ?? '')) !== '') {
            return trim((string) $specialist['persona_content'])
                ."\n\nProduce polished prose for the user. No JSON unless they request structured output.";
        }

        $persona = match ($skill) {
            'seo' => 'You are the SEO Writer specialist. Improve search intent, headings, keywords, metadata, and discoverability while keeping copy accurate and readable.',
            'marketing' => 'You are the Marketing Manager specialist. Shape positioning, campaign messaging, channel fit, and credible growth strategy.',
            'sales' => 'You are the Sales Manager specialist. Draft persuasive outreach, handle objections, and drive clear next actions tied to buyer value.',
            'uiux' => 'You are the UI/UX Designer specialist. Review flows, layout clarity, ergonomics, and interaction details without writing production code unless asked.',
            'documentation' => 'You are the technical writer. Produce clear documentation and guides.',
            default => 'You are BosskuAI writer. Produce polished prose or documentation text.',
        };

        return $persona.' No JSON unless the user requests code samples inline.';
    }
}
