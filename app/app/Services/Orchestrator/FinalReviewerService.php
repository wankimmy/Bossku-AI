<?php

namespace App\Services\Orchestrator;

use App\Support\LlmTelemetry;
use App\Support\StringCoercion;
use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\DomainModelSelector;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\ReviewerAccessList;

class FinalReviewerService
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected ModelFallbackService $fallback,
        protected AgentPersonaService $personas,
        protected DomainModelSelector $modelSelector,
        protected ReviewerAccessList $accessList,
    ) {}

    /**
     * @param  array<string, mixed>  $route
     * @param  array<string, mixed>  $auditor
     * @param  array<string, mixed>|null  $securityAudit
     * @param  array<string, mixed>  $executorResult
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $memoryContext
     * @param  list<array{role: string, content: string}>  $conversation
     * @return array<string, mixed>
     */
    public function review(
        string $userPrompt,
        array $route,
        array $auditor,
        ?array $securityAudit,
        array $executorResult,
        array $plan = [],
        array $memoryContext = [],
        array $conversation = [],
        ?string $runId = null,
    ): array {
        $cfg = $this->config->finalReviewer();
        $primary = (string) ($cfg['primary'] ?? 'deepseek-v4-pro');
        $models = array_merge([$primary], is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : []);
        $models = $this->modelSelector->order(
            $models,
            $this->modelSelector->domainFor($route),
            $this->config->roleModelIsPinned('final_reviewer'),
        );
        $retry = (int) ($cfg['retry_count'] ?? 1);

        $memBlock = $this->buildMemoryBlock($memoryContext);
        $conversationBlock = $this->buildConversationBlock($conversation);

        // Summarise the verdict trail from the auditor for quick reference
        $verdictTrail = is_array($auditor['verdict_trail'] ?? null) ? $auditor['verdict_trail'] : [];
        $disputedItems = array_filter($verdictTrail, fn ($v) => ($v['auditor_verdict'] ?? '') !== 'verified');
        $verdictSummary = $verdictTrail !== []
            ? count($verdictTrail).' checklist item(s) reviewed; '.count($disputedItems).' disputed or unverifiable.'
            : 'No verdict trail provided.';

        $system = <<<SYS
You are the BosskuAI Final Reviewer — Stage 4 of 4 in the pipeline (Planner → Executor → Auditor → Final Reviewer).

PIPELINE CONTEXT:
- The Planner (Stage 1) produced a plan with a task summary, confidence score, and risk notes.
- The Executor (Stage 2) implemented the plan and reported per-item checklist completion.
- The Auditor (Stage 3) adversarially verified executor output and produced a verdict trail.
- YOU are the final gate. Your decision is the last one before the result reaches the user.

YOUR ROLE:
- Synthesise all prior stages into a single MERGE / REVISE / REJECT decision.
- Be honest: if the auditor disputed items and the executor provided no evidence, that is a REVISE.
- Do NOT re-audit or invent new findings — cite the auditor's verdict_trail and findings.
- Use conversation history to understand user intent and prior retry context.
- Apply any prior memory lessons that are still relevant.

CONVERSATION HISTORY (use to understand prior attempts and user intent):
{$conversationBlock}

Prior memory context (lessons from past runs):
{$memBlock}

Checklist verdict summary: {$verdictSummary}

Output ONLY valid JSON (no markdown fences):
decision ("MERGE"|"REVISE"|"REJECT"),
reason (string — cite specific audit findings or verdict trail items),
required_actions (string[] — on REVISE/REJECT: concrete executor fix steps; on MERGE: the single most valuable next verification step the user should run, written as a ready-to-paste Bossku prompt; empty only if nothing meaningful remains),
confidence (number 0.0–1.0 — your confidence in this decision),
memory_lessons_applied (string[] — cite any [Memory N] lessons that shaped your decision; empty if none).
SYS;

        $payload = json_encode([
            'user_prompt' => $userPrompt,
            'route' => $route,
            'plan_summary' => $plan['summary'] ?? $plan['task_summary'] ?? null,
            'plan_confidence' => $plan['confidence'] ?? null,
            'plan_risk_level' => $plan['risk_level'] ?? null,
            // Bounded access list of prior-stage outputs (severity-ranked + capped) so a
            // large audit cannot bury the critical findings or blow the context window.
            ...$this->accessList->forFinalReviewer($auditor, $executorResult, $securityAudit),
            'conversation_turns' => count($conversation),
            'memory_context_count' => count($memoryContext),
        ], JSON_THROW_ON_ERROR);

        $fromRole = $securityAudit !== null ? 'security_auditor' : 'auditor';
        $handoffMessage = StringCoercion::toString($auditor['summary'] ?? null, 'Final review handoff.');
        $userContent = $this->personas->wrapHandoffUserContent('final_reviewer', $fromRole, $handoffMessage, $payload);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
        ];

        $out = $this->fallback->chatWithFallbacks(
            $models,
            $messages,
            (float) ($cfg['temperature'] ?? 0.1),
            $retry,
            'final_reviewer',
            function (mixed $j): bool {
                return is_array($j) && isset($j['decision'], $j['reason']);
            },
            (int) ($cfg['max_tokens'] ?? 2048),
            $runId,
        );

        /** @var array<string, mixed> $parsed */
        $parsed = is_array($out['parsed']) ? $out['parsed'] : [];

        return LlmTelemetry::mergeAgentResult(array_merge([
            'decision' => StringCoercion::toString($parsed['decision'] ?? null, 'REVISE'),
            'reason' => StringCoercion::toString($parsed['reason'] ?? null, 'Final review completed.'),
            'required_actions' => is_array($parsed['required_actions'] ?? null) ? $parsed['required_actions'] : [],
            'confidence' => is_numeric($parsed['confidence'] ?? null) ? min(1.0, max(0.0, (float) $parsed['confidence'])) : 0.7,
            'memory_lessons_applied' => is_array($parsed['memory_lessons_applied'] ?? null) ? $parsed['memory_lessons_applied'] : [],
        ], [
            '_model_used' => $out['model_used'],
            '_model_resolved' => $out['model_resolved'] ?? '',
        ]), $out);
    }

    /** @param list<array{role: string, content: string}> $conversation */
    protected function buildConversationBlock(array $conversation): string
    {
        if ($conversation === []) {
            return '(no prior conversation — this is the first turn)';
        }
        $total = count($conversation);
        $recent = array_slice($conversation, -8);
        $offset = max(0, $total - 8);
        $lines = [];
        foreach ($recent as $idx => $turn) {
            $role = strtolower((string) ($turn['role'] ?? 'user'));
            $cap = $role === 'assistant' ? 1200 : 800;
            $content = mb_substr((string) ($turn['content'] ?? ''), 0, $cap);
            $lines[] = '[Turn '.($offset + $idx).'] '.strtoupper($role).': '.$content;
        }

        return implode("\n\n", $lines);
    }

    /** @param list<array<string, mixed>> $memories */
    protected function buildMemoryBlock(array $memories): string
    {
        if ($memories === []) {
            return '(no prior memory retrieved)';
        }
        $lines = [];
        foreach ($memories as $i => $m) {
            $summary = is_array($m) ? ($m['summary'] ?? $m['human_summary'] ?? $m['content'] ?? '') : (string) $m;
            $type = is_array($m) ? ($m['type'] ?? '') : '';
            $lines[] = '[Memory '.($i + 1).']'.($type !== '' ? ' ['.$type.']' : '').' '.mb_substr((string) $summary, 0, 300);
        }

        return implode("\n", $lines);
    }
}
