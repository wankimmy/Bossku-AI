<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\LlmErrorFormatter;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Company\CompanyStaffService;

class DirectAnswerService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback,
        protected CompanyStaffService $companyStaff,
    ) {}

    public function answer(string $userPrompt, array $routeContext, ?string $runId = null, array $conversation = []): string
    {
        $cfg = $this->config->directAnswer();
        $primary = (string) ($cfg['primary'] ?? 'gpt-4o-mini');
        $fallbacks = $cfg['fallback'] ?? [];
        $models = array_merge([$primary], is_array($fallbacks) ? $fallbacks : []);
        $retry = (int) ($cfg['retry_count'] ?? 1);
        $payload = ['route' => $routeContext, 'question' => $userPrompt];
        if ($conversation !== []) {
            $payload['conversation_context'] = array_slice($conversation, -6);
        }
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($routeContext)],
            ['role' => 'user', 'content' => "Context (JSON):\n".json_encode($payload)],
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

    /** @param  array<string, mixed>  $routeContext */
    protected function systemPrompt(array $routeContext = []): string
    {
        $specialist = is_array($routeContext['specialist_agent'] ?? null) ? $routeContext['specialist_agent'] : null;
        if ($specialist !== null && trim((string) ($specialist['persona_content'] ?? '')) !== '') {
            return trim((string) $specialist['persona_content'])
                ."\n\nAnswer clearly and conversationally for the user. No JSON unless they request it.";
        }

        $skill = (string) ($routeContext['skill'] ?? 'generic');
        $skillPersona = match ($skill) {
            'seo' => 'You are the SEO Writer specialist. Answer with search intent, metadata, and discoverability in mind.',
            'marketing' => 'You are the Marketing Manager specialist. Answer with positioning, audience, and channel fit in mind.',
            'sales' => 'You are the Sales Manager specialist. Answer with buyer pain, objections, and next actions in mind.',
            'uiux' => 'You are the UI/UX Designer specialist. Answer with layout clarity, usability, and interaction quality in mind.',
            default => null,
        };
        if ($skillPersona !== null) {
            return $skillPersona.' No JSON unless the user requests it.';
        }

        $staffLines = [];
        foreach ($this->companyStaff->defaultRoster() as $member) {
            $staffLines[] = '- '.$member['display_name'].': '.$member['description'];
        }
        $staffBlock = implode("\n", $staffLines);

        return <<<SYS
You are BosskuAI — a local-first AI coding assistant with a safety layer that plans before editing, checks work after, and remembers what it learns.

Answer clearly and conversationally. No JSON unless the user asks for it.

When asked what you are good at, what you can do, or who you are, describe these modes honestly:

**1. Coding agent (full pipeline)**
- Plan before editing (orchestrator + planner)
- Implement changes in the active project (executor with file tools)
- Audit and security review on risky work (auditor, security auditor, final reviewer)
- Run tests, propose file changes with approval, project understanding (/project-understanding)

**2. Chat / direct answers (fast path)**
- Explain concepts, compare options, brainstorm, answer factual questions
- Advice without touching code unless the user asks for implementation

**3. Specialist sub-agents (non-coding and mixed)**
Company staff you can delegate to for domain work:
{$staffBlock}

For SEO, marketing, sales copy, UI/UX critique, blog writing, and support messaging, route to the writer path with the matching specialist persona rather than running a code pipeline.

If the user wants code changed, say you can switch to the implementation pipeline. If they want content or strategy, offer the relevant specialist angle.
SYS;
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

        if (preg_match('/\b(what are you good at|what can you do|who are you|your capabilities)\b/i', $trimmed)) {
            return "I'm BosskuAI — a coding agent with planning, execution, and review stages, plus fast chat answers and specialist staff for SEO, marketing, sales, UI/UX, blogging, QA, and security advisory.\n\n"
                ."LLM is unavailable right now: {$reason}";
        }

        return "I could not reach the configured LLM.\n\n{$reason}";
    }
}
