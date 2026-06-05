<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\LlmErrorFormatter;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\RuntimeSettings;
use App\Support\AgentTools;
use App\Support\LlmTelemetry;
use App\Support\StringCoercion;
use Illuminate\Support\Facades\File;

class DesignerService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected RuntimeSettings $settings,
        protected AgentPersonaService $personas,
    ) {}

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    public function design(string $prompt, array $plan, array $modelRoute, ?string $runId = null): array
    {
        $orch = $this->modelConfig->orchestrator();
        $primary = (string) ($orch['primary'] ?? $this->settings->orchestratorModelForRouting());
        $fallbacks = is_array($orch['fallback'] ?? null) ? $orch['fallback'] : [];
        $models = array_values(array_unique(array_merge([$primary], $fallbacks)));
        $retry = (int) ($orch['retry_count'] ?? 1);

        $rawMd = $this->loadDesignerMd();
        $toolsBlock = AgentTools::formatToolsBlock('designer', $rawMd);
        $persona = $this->personas->defaultContentFromAgentsMd('designer') ?? '';

        $system = <<<SYS
You are the BosskuAI Designer — UI/UX specialist before implementation.

{$toolsBlock}

{$persona}

Produce a design spec the Executor can implement without guessing. Include open questions with recommended defaults when intent is unclear.
Output ONLY valid JSON with keys:
design_summary (string),
open_questions (array of {id, question, why, recommended}),
layout_notes (string[]),
token_mapping (array of {element, token_or_component, notes}),
accessibility_notes (string[]),
file_scope (array of {path, notes}),
handoff_message (string),
design_phase_required (boolean — false if UI work is trivial).
SYS;

        $user = json_encode([
            'prompt' => $prompt,
            'plan' => $plan,
            'routing' => $modelRoute,
        ], JSON_THROW_ON_ERROR);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

        try {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                $messages,
                0.3,
                $retry,
                'designer',
                fn (mixed $j) => is_array($j) && isset($j['design_summary']),
                (int) ($orch['max_tokens'] ?? 2048),
                $runId,
            );
            /** @var array<string, mixed> $decoded */
            $decoded = is_array($out['parsed']) ? $out['parsed'] : [];
            $decoded['design_summary'] = StringCoercion::toString($decoded['design_summary'] ?? null, 'Design pass completed.');
            $decoded['_designer_model'] = $out['model_used'] ?? '';

            return LlmTelemetry::mergeAgentResult($decoded, $out);
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => LlmErrorFormatter::humanize($e->getMessage()),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function shouldRun(array $plan, string $execProfileKey): bool
    {
        if ($execProfileKey === 'frontend_ui') {
            return true;
        }

        if ((bool) ($plan['design_phase_required'] ?? false)) {
            return true;
        }

        $phases = is_array($plan['execution_phases'] ?? null) ? $plan['execution_phases'] : [];
        foreach ($phases as $phase) {
            if (! is_array($phase)) {
                continue;
            }
            $tasks = is_array($phase['tasks'] ?? null) ? $phase['tasks'] : [];
            foreach ($tasks as $task) {
                if (is_array($task) && strtolower((string) ($task['agent'] ?? '')) === 'designer') {
                    return true;
                }
            }
        }

        return false;
    }

    protected function loadDesignerMd(): ?string
    {
        $path = rtrim((string) config('bossku.repo_root'), '/\\').'/agents/designer.md';

        return is_file($path) ? (string) File::get($path) : null;
    }
}
