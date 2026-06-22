<?php

namespace App\Services\Specialists;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Support\StringCoercion;
use Illuminate\Support\Str;

/**
 * Synthesizes a brand-new specialist sub-agent, on demand, for a task that no
 * existing agent covers.
 *
 * This is what takes BosskuAI past LangGraph: LangGraph's `Send` dynamically
 * dispatches to nodes that already exist in a graph compiled up front — it
 * cannot invent a new agent type at runtime. Here, when the router finds no
 * matching specialist, an LLM designs one (role, expertise, task strategy,
 * pitfalls, reusable triggers) tailored to the actual task, which is then
 * persisted and used in the same run.
 *
 * Returns a plain spec array; persistence is owned by
 * {@see SpecialistAgentDraftingService::draftFromSpec()} so there is one writer.
 */
class DynamicSpecialistSynthesizer
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @return array{display_name: string, role_slug: string, description: string, trigger_keywords: list<string>, expertise: list<string>, persona_content: string}|null
     */
    public function synthesize(
        string $prompt,
        array $plan,
        ?SpecialistIntent $intent,
        array $modelRoute = [],
        ?string $runId = null,
    ): ?array {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return null;
        }

        $cfg = $this->modelConfig->orchestrator();
        $primary = StringCoercion::toString($cfg['primary'] ?? null, 'kimi-k2.6');
        $fallbacks = is_array($cfg['fallback'] ?? null) ? $cfg['fallback'] : [];
        $models = array_values(array_filter(array_unique(array_merge([$primary], array_map('strval', $fallbacks)))));

        $context = json_encode([
            'task' => $prompt,
            'intent' => $intent?->value,
            'plan_summary' => StringCoercion::toString($plan['summary'] ?? null),
            'target_files' => array_slice(is_array($plan['target_file_list'] ?? null) ? $plan['target_file_list'] : [], 0, 20),
            'checklist' => array_slice(is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [], 0, 12),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';

        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $context],
                ],
                0.3,
                (int) ($cfg['retry_count'] ?? 0),
                'specialist_synthesizer',
                fn (mixed $j): bool => $this->valid($j),
                (int) ($cfg['max_tokens'] ?? 2048),
                $runId,
            );
        } catch (\Throwable) {
            return null;
        }

        $parsed = is_array($out['parsed'] ?? null) ? $out['parsed'] : [];
        if (! $this->valid($parsed)) {
            return null;
        }

        return $this->normalize($parsed, $prompt);
    }

    private function valid(mixed $spec): bool
    {
        return is_array($spec)
            && StringCoercion::toString($spec['display_name'] ?? null) !== ''
            && StringCoercion::toString($spec['persona_content'] ?? null) !== '';
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{display_name: string, role_slug: string, description: string, trigger_keywords: list<string>, expertise: list<string>, persona_content: string}
     */
    private function normalize(array $spec, string $prompt): array
    {
        $displayName = Str::limit(StringCoercion::toString($spec['display_name'] ?? null, 'Task Specialist'), 60, '');
        $roleSlug = StringCoercion::toString($spec['role_slug'] ?? null);
        $roleSlug = Str::slug($roleSlug !== '' ? $roleSlug : $displayName);
        if ($roleSlug === '') {
            $roleSlug = 'task';
        }
        if (! str_ends_with($roleSlug, '-specialist')) {
            $roleSlug .= '-specialist';
        }

        return [
            'display_name' => $displayName,
            'role_slug' => Str::limit($roleSlug, 80, ''),
            'description' => Str::limit(StringCoercion::toString($spec['description'] ?? null, 'On-demand specialist for: '.$prompt), 240, ''),
            'trigger_keywords' => $this->stringList($spec['trigger_keywords'] ?? []),
            'expertise' => $this->stringList($spec['expertise'] ?? []),
            'persona_content' => StringCoercion::toString($spec['persona_content'] ?? null),
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($v) => Str::lower(trim(StringCoercion::toString($v))),
            $value,
        ), static fn (string $v): bool => $v !== '')));
    }

    private function systemPrompt(): string
    {
        return <<<'SYS'
You design a single, focused BosskuAI specialist sub-agent for a task that no existing agent covers. The specialist is ADVISORY: it reads the plan and produces implementation guidance for an executor — it does not write code itself.

Given the task, intent, and plan, output ONLY valid JSON (no prose, no fences):
{
  "display_name": "<2-4 word human name, e.g. 'Stripe Webhook Specialist'>",
  "role_slug": "<kebab-case, no spaces>",
  "description": "<one sentence on what this specialist is for>",
  "trigger_keywords": ["<lowercase keywords that should route similar future tasks here>"],
  "expertise": ["<concrete skills/areas this specialist is strong in>"],
  "persona_content": "<a markdown persona: '# <name>', '## When to use', '## Expertise', '## Approach' (how it reasons about this kind of task), '## Common pitfalls', and '## Output contract' stating it returns summary, task_strategy[], pitfalls[], files_or_areas_to_focus[], handoff_to_executor>"
}

Rules:
- Make the specialist genuinely specific to THIS task domain — name the technology/concern, not 'general developer'.
- trigger_keywords must be real terms from the task domain so the agent is reusable for similar future tasks.
- Keep persona_content tight and actionable; no filler, no fake metrics.
SYS;
    }
}
