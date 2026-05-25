<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Project\ProjectService;
use Illuminate\Support\Str;

class ClarificationService
{
    public function __construct(
        protected ModelFallbackService $fallback,
        protected RuntimeSettings $settings,
        protected ProjectService $projects,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $context
     * @return array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}
     */
    public function ask(
        string $userPrompt,
        array $conversation,
        array $modelRoute,
        string $stage,
        array $context = [],
    ): array {
        if ($this->settings->orchestratorClarificationMode() === 'off') {
            return $this->emptyProceed();
        }

        if (
            $this->settings->orchestratorClarificationMode() === 'smart'
            && ClarificationPromptAnalyzer::isClearEnough($userPrompt, $modelRoute)
        ) {
            return $this->emptyProceed();
        }

        try {
            $parsed = $this->callLlm($userPrompt, $conversation, $modelRoute, $stage, $context);
        } catch (\Throwable) {
            $parsed = $this->fallbackQuestions($userPrompt, $stage, $context);
        }

        return $this->normalize($parsed, $stage, $userPrompt);
    }

    /**
     * Whether pre-execution clarification should run (smart mode uses heuristics; always mode is gated elsewhere).
     *
     * @param  array<string, mixed>  $modelRoute
     */
    public function shouldAskForPrompt(string $userPrompt, array $modelRoute): bool
    {
        return match ($this->settings->orchestratorClarificationMode()) {
            'off' => false,
            'always' => true,
            default => ! ClarificationPromptAnalyzer::isClearEnough($userPrompt, $modelRoute),
        };
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}
     */
    public function normalize(array $parsed, string $stage, string $userPrompt): array
    {
        $questions = [];
        foreach (is_array($parsed['questions'] ?? null) ? $parsed['questions'] : [] as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (string) ($item['id'] ?? 'q'.($idx + 1));
            $prompt = trim((string) ($item['prompt'] ?? ''));
            if ($prompt === '') {
                continue;
            }
            $options = [];
            foreach (is_array($item['options'] ?? null) ? $item['options'] : [] as $oIdx => $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $label = trim((string) ($opt['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $options[] = [
                    'id' => (string) ($opt['id'] ?? 'opt'.($oIdx + 1)),
                    'label' => $label,
                    'recommendation' => (bool) ($opt['recommendation'] ?? false),
                ];
            }
            if ($options === []) {
                $options = $this->defaultOptionsForStage($stage);
            }
            $questions[] = [
                'id' => $id,
                'prompt' => $prompt,
                'why_it_matters' => stringOrUndefined($item['why_it_matters'] ?? $item['why'] ?? null),
                'options' => $this->normalizeOptionsToThree($options, $stage),
                'allow_free_text' => ($item['allow_free_text'] ?? true) !== false,
            ];
            if (count($questions) >= 3) {
                break;
            }
        }

        $mode = $this->settings->orchestratorClarificationMode();
        $ready = (bool) ($parsed['ready_to_proceed'] ?? false);
        if ($mode === 'always') {
            $ready = false;
        }

        if ($questions === []) {
            if ($mode === 'smart' && $ready) {
                return $this->emptyProceed();
            }
            $questions = $this->fallbackQuestions($userPrompt, $stage, [])['questions'];
            $ready = false;
        }

        foreach ($questions as $qIdx => $question) {
            $questions[$qIdx]['options'] = $this->normalizeOptionsToThree(
                is_array($question['options'] ?? null) ? $question['options'] : [],
                $stage,
            );
        }

        $assumptions = array_values(array_filter(array_map(
            fn ($a) => is_string($a) ? trim($a) : '',
            is_array($parsed['assumptions'] ?? null) ? $parsed['assumptions'] : [],
        )));

        $summary = trim((string) ($parsed['summary'] ?? ''));
        if ($summary === '') {
            $summary = $stage === 'executor_stuck'
                ? 'The run needs your input before it can continue.'
                : 'Please confirm scope and preferences before BosskuAI proceeds.';
        }

        return [
            'questions' => $questions,
            'assumptions' => $assumptions,
            'ready_to_proceed' => $ready,
            'summary' => $summary,
        ];
    }

    /**
     * @param  list<array{question_id: string, option_id?: string|null, free_text?: string|null}>  $answers
     */
    public function formatAnswersBlock(array $answers, array $questions): string
    {
        $byId = [];
        foreach ($questions as $q) {
            if (is_array($q) && isset($q['id'])) {
                $byId[(string) $q['id']] = $q;
            }
        }

        $lines = ['## User clarification answers'];
        foreach ($answers as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            $qid = (string) ($answer['question_id'] ?? '');
            $q = $byId[$qid] ?? null;
            $label = is_array($q) ? (string) ($q['prompt'] ?? $qid) : $qid;
            $parts = [];
            if (! empty($answer['option_id']) && is_array($q)) {
                foreach ($q['options'] ?? [] as $opt) {
                    if (is_array($opt) && (string) ($opt['id'] ?? '') === (string) $answer['option_id']) {
                        $parts[] = 'Selected: '.(string) ($opt['label'] ?? $answer['option_id']);
                        break;
                    }
                }
            }
            if (! empty($answer['free_text'])) {
                $parts[] = 'Notes: '.trim((string) $answer['free_text']);
            }
            $lines[] = '- '.$label.': '.($parts !== [] ? implode(' | ', $parts) : '(no answer)');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function callLlm(
        string $userPrompt,
        array $conversation,
        array $modelRoute,
        string $stage,
        array $context,
    ): array {
        $primary = $this->settings->routerModel();
        $fallbacks = [$this->settings->reasoningModel()];
        $models = array_values(array_unique(array_filter([$primary, ...$fallbacks])));

        $clarificationMode = $this->settings->orchestratorClarificationMode();

        $system = <<<'SYS'
You are a skeptical BosskuAI orchestrator. Surface only ambiguity that changes what agents do next.
Output ONLY valid JSON (no markdown) with keys:
summary (string, one line for the user),
assumptions (string[], what you would do if the user says proceed),
ready_to_proceed (boolean — true when the prompt is clear enough to run without asking),
questions (array of 0-3 items: {
  id: string,
  prompt: string,
  why_it_matters: string,
  allow_free_text: true,
  options: array of 2-3 items: { id, label, recommendation?: boolean } — short labels
}).
Rules:
- Only ask when the answer would change scope, target files, risk, data policy, environment, verification, or definition of done.
- If the prompt is unambiguous, set ready_to_proceed true and questions [].
- Prefer ONE question for a single blocking unknown; use 2-3 only for independent blockers that are truly independent.
- Do not ask generic confirmation when the user already named files, routes, or a concrete fix.
- When you ask, make the question decision-oriented and concrete, then provide 2-3 options. Mark the safest recommended option with recommendation: true.
SYS;

        $user = json_encode([
            'stage' => $stage,
            'clarification_mode' => $clarificationMode,
            'user_prompt' => $userPrompt,
            'conversation' => array_slice($conversation, -10),
            'routing' => [
                'workflow' => $modelRoute['workflow'] ?? null,
                'skill' => $modelRoute['skill'] ?? null,
                'risk_level' => $modelRoute['risk_level'] ?? null,
                'task_type' => $modelRoute['task_type'] ?? null,
            ],
            'workspace' => $this->projects->agentWorkspaceContext(),
            'context' => $context,
        ], JSON_THROW_ON_ERROR);

        $out = $this->fallback->chatWithFallbacks(
            $models,
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            0.2,
            1,
            'clarification',
            fn (mixed $j): bool => is_array($j) && isset($j['questions']),
            4096,
        );

        return is_array($out['parsed']) ? $out['parsed'] : [];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}
     */
    protected function fallbackQuestions(string $userPrompt, string $stage, array $context): array
    {
        $short = Str::limit(trim($userPrompt), 120);
        if ($stage === 'executor_stuck') {
            $exec = is_array($context['exec_result'] ?? null) ? $context['exec_result'] : [];
            $suggested = is_array($exec['suggested_options'] ?? null) ? $exec['suggested_options'] : [];

            $options = [];
            foreach (array_slice($suggested, 0, 4) as $idx => $opt) {
                if (! is_array($opt)) {
                    continue;
                }
                $options[] = [
                    'id' => (string) ($opt['id'] ?? 'opt'.($idx + 1)),
                    'label' => (string) ($opt['label'] ?? 'Option '.($idx + 1)),
                    'recommendation' => $idx === 0,
                ];
            }
            if ($options === []) {
                $options = $this->defaultOptionsForStage('executor_stuck');
            }
            $options = $this->normalizeOptionsToThree($options, 'executor_stuck');

            return [
                'questions' => [[
                    'id' => 'stuck-1',
                    'prompt' => 'Executor is blocked. How should BosskuAI proceed?',
                    'why_it_matters' => implode(' ', ExecutorStuckDetector::stuckSummary($exec)),
                    'options' => $options,
                    'allow_free_text' => true,
                ]],
                'assumptions' => [],
                'ready_to_proceed' => false,
                'summary' => 'Executor needs your decision to continue.',
            ];
        }

        return [
            'questions' => [[
                'id' => 'scope-1',
                'prompt' => 'Which blocker should BosskuAI resolve first before acting on "'.$short.'"?',
                'why_it_matters' => 'The answer changes scope, target files, or verification before the run starts.',
                'options' => [
                    ['id' => 'scope', 'label' => 'Scope or target files', 'recommendation' => true],
                    ['id' => 'environment', 'label' => 'Environment or repo access', 'recommendation' => false],
                    ['id' => 'risk', 'label' => 'Risk, policy, or verification', 'recommendation' => false],
                ],
                'allow_free_text' => true,
            ]],
            'assumptions' => ['Will follow the selected blocker and active project mount.'],
            'ready_to_proceed' => false,
            'summary' => 'Need one decision-changing blocker before BosskuAI runs agents on your request.',
        ];
    }

    /**
     * @return array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}
     */
    protected function emptyProceed(): array
    {
        return [
            'questions' => [],
            'assumptions' => [],
            'ready_to_proceed' => true,
            'summary' => '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array{id: string, label: string, recommendation: bool}>
     */
    public function normalizeOptionsToThree(array $options, string $stage): array
    {
        $normalized = [];
        foreach (array_slice($options, 0, 3) as $idx => $opt) {
            if (! is_array($opt)) {
                continue;
            }
            $label = trim((string) ($opt['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $normalized[] = [
                'id' => (string) ($opt['id'] ?? 'opt'.($idx + 1)),
                'label' => $label,
                'recommendation' => (bool) ($opt['recommendation'] ?? false),
            ];
        }

        $usedIds = array_column($normalized, 'id');
        foreach ($this->defaultOptionsForStage($stage) as $def) {
            if (count($normalized) >= 3) {
                break;
            }
            if (in_array($def['id'], $usedIds, true)) {
                continue;
            }
            $normalized[] = $def;
            $usedIds[] = $def['id'];
        }

        while (count($normalized) < 3) {
            $n = count($normalized) + 1;
            $normalized[] = [
                'id' => 'opt'.$n,
                'label' => 'Option '.$n,
                'recommendation' => false,
            ];
        }

        return array_slice($normalized, 0, 3);
    }

    /**
     * @return list<array{id: string, label: string, recommendation: bool}>
     */
    protected function defaultOptionsForStage(string $stage): array
    {
        if ($stage === 'executor_stuck') {
            return [
                ['id' => 'retry', 'label' => 'Retry with a narrower scope', 'recommendation' => true],
                ['id' => 'skip', 'label' => 'Skip changes and continue audit only', 'recommendation' => false],
                ['id' => 'abort', 'label' => 'Stop the run', 'recommendation' => false],
            ];
        }

        return [
            ['id' => 'proceed', 'label' => 'Proceed with your best interpretation', 'recommendation' => true],
            ['id' => 'narrow', 'label' => 'Start narrow — minimal scope first', 'recommendation' => false],
            ['id' => 'explain', 'label' => 'Explain options only — no repo changes yet', 'recommendation' => false],
        ];
    }
}

function stringOrUndefined(mixed $value): ?string
{
    return $value === null || $value === '' ? null : (string) $value;
}
