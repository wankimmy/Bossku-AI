<?php

namespace App\Services\Orchestrator;

use App\Services\BosskuAi\CodebaseIndexService;
use App\Services\BosskuAi\LlmErrorFormatter;
use App\Support\AgentTools;
use App\Support\LlmTelemetry;
use App\Support\StringCoercion;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Agents\AgentToolPermissionService;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\ProjectService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class PlannerService
{
    use AgentConversationTrait;
    public function __construct(
        protected ModelFallbackService $fallback,
        protected ModelRoutingConfig $modelConfig,
        protected RuntimeSettings $settings,
        protected ProjectService $projects,
        protected ProjectFileDiscovery $discovery,
        protected CodebaseIndexService $codeIndex,
        protected ProjectPathResolver $paths,
        protected AgentToolPermissionService $toolPermissions,
    ) {}

    /**
     * Orchestrator plan: scoped context + optional legacy steps.
     *
     * @param  array<int, mixed>  $memoryContext
     * @param  array<string, mixed>  $routerContext
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, mixed>  $memoryContext
     * @param  array<string, mixed>  $routerContext
     * @param  array<string, mixed>  $modelRoute
     * @param  list<array{role: string, content: string}>  $conversation
     */
    public function plan(
        string $prompt,
        array $memoryContext,
        array $routerContext,
        array $modelRoute,
        array $conversation = [],
        ?string $runId = null,
    ): array
    {
        $orch = $this->modelConfig->orchestrator();
        $primary = (string) ($orch['primary'] ?? $this->settings->orchestratorModelForRouting());
        $fallbacks = is_array($orch['fallback'] ?? null) ? $orch['fallback'] : [];
        $models = array_values(array_unique(array_merge([$primary], $fallbacks)));
        $retry = (int) ($orch['retry_count'] ?? 1);

        $system = <<<'SYS'
You are the BosskuAI Planner — Stage 1 of 3 in a three-stage pipeline (Planner → Executor → Auditor).

PIPELINE CONTEXT:
- You are Stage 1 (Planner). After you, the Executor (Stage 2) will implement your plan exactly — it cannot ask questions mid-execution. The Auditor (Stage 3) will adversarially verify the Executor's output against your plan and will reject work that deviates, invents paths, or ignores prior memory lessons.
- Your plan is the single source of truth for the entire pipeline. Make it concrete, unambiguous, and honest.

WHAT YOU MUST DO:
1. READ the full conversation history — understand what has been tried, what failed, and what the user's actual intent is.
2. READ all prior memory context — identify lessons from past runs that are directly relevant. Cite them by [Memory N] in memory_applied.
3. QUESTION EVERYTHING — if any assumption could cause the Executor or Designer to take the wrong action, surface it as a planner_question with a recommended default answer. Never guess on product intent, data behavior, UX bar, or risk tolerance.
4. ASSESS confidence — how sure are you about the target files and approach? Be explicit.
5. PRODUCE a concrete, audit-ready plan — every checklist item must have a named owner (executor or auditor) and a concrete, verifiable completion criterion.
6. MAKE the plan executor-ready and audit-ready: executable by the Executor without guessing and verifiable by the Auditor with concrete evidence.

HONESTY RULES (these are hard constraints):
- NEVER invent file paths. Every path in target_file_list must be supported by repo_index evidence.
- If a path is not supported by repo evidence, leave target_file_list empty rather than inventing one.
- If you don't know the right file, say so in risk_notes and set allow_broad_repo_scan true.
- If the user said "retry" or referenced a prior attempt, check conversation history for what was tried and what failed.
- If prior memory shows a known failure pattern, explicitly note it in risk_notes and memory_applied.
- Rate your confidence honestly. A plan with low confidence should have more planner_questions.
- handoff_message must tell the executor exactly what to do first, which files to touch, and what evidence to return.

CHECKLIST QUALITY:
- Each checklist item must have: a specific file or artifact it targets, a concrete success criterion the Auditor can verify, and the correct owner (executor/auditor/final-reviewer).
- Include at least one auditor-owned item for every code change, with explicit acceptance criteria.
- Do not write vague items like "Fix the bug" — write "Modify app/Services/Foo.php: ensure method bar() returns early when $x is null (auditor verifies: no NPE in test case Y)".

CURSOR-STYLE PLAN SECTIONS (populate for UI display):
- goal: one sentence user intent.
- key_design_decisions: 2–6 bullets explaining architectural choices and trade-offs.
- flow_diagram: Mermaid flowchart TD source (3–8 nodes) showing pipeline from user request to completion; also provide flow_steps as ordered text fallback.
- risk_notes + constraints: notes and risks for the executor.
- checklist: concrete to-dos with owners.

MERMAID RULES for flow_diagram (must parse in Mermaid.js):
- Start with flowchart TD on its own line.
- Node IDs: camelCase or underscores only — no spaces in IDs.
- Quote edge labels that contain parentheses, colons, or commas: A -->|"label (detail)"| B
- Do not use style, classDef, click, or subgraph unless essential.
- Output raw Mermaid only — no ```mermaid fences inside the JSON string.

Output ONLY valid JSON (no markdown wrapper) with keys:
task_summary (string),
goal (string — one sentence, the actual user intent),
key_design_decisions (string[] — 2–6 architectural choices / trade-offs),
flow_diagram (string — Mermaid flowchart TD source, 3–8 nodes),
flow_steps (string[] — ordered text steps mirroring flow_diagram, fallback if diagram fails),
risk_level ("low"|"medium"|"high"),
selected_skill (string),
confidence (number 0.0–1.0 — how confident you are in this plan given available evidence),
memory_strategy ("none"|"read_only"|"read_and_write"),
expected_artifacts (string[]),
checklist (array of {id: string, title: string, description: string, target: string, success_criterion: string, owner: string, status: "pending"}),
summary (string, one line),
target_file_list (array of {path: string, reason: string}),
allow_broad_repo_scan (boolean),
executor_profile (one of: default, frontend_ui, backend, devops, high_risk),
suggested_tests (string[]),
risk_notes (string[]),
constraints (string[]),
handoff_message (string — tell the executor exactly what to do first, what files to touch, and what evidence to provide),
execution_mode ("answer_only"|"delegate_executor"|"user_must_run_commands"),
user_commands (string[]),
planner_questions (array of {id: string, question: string, why: string, recommended: string} — surface to user when task is ambiguous; each question must include your recommended answer; empty only when fully confident),
design_phase_required (boolean — true when UI/UX, styling, layout, or component work needs a Designer pass before the Executor),
execution_phases (array of {phase: number, name: string, parallel: boolean, tasks: array of {agent: "executor"|"designer", description: string, files: string[], depends_on: string[]}} — file-scoped delegation; steps with no overlapping files may run in parallel within a phase),
memory_applied (string[] — cite specific [Memory N] lessons and how they shaped this plan; empty if none relevant).

Use relative paths from the repository root only. handoff_message must cite target paths and first action.
When routing.needs_executor is false, set execution_mode to answer_only.
When docker/host commands are needed, set execution_mode to user_must_run_commands and list them in user_commands.

SEMANTIC CODE CONTEXT:
When `semantic_code_context` is present in the user payload, treat it as the most relevant code from the active project retrieved via vector similarity search. Use it to:
- Populate target_file_list with paths from these chunks (they are already ranked by relevance — do not invent other paths).
- Anchor your plan's key_design_decisions and risk_notes to actual code you can see.
- Set confidence higher when relevant code is visible.
SYS;
        $plannerMd = $this->loadPlannerAgentsMd();
        $system .= "\n\n".$this->toolPermissions->formatToolsBlock('planner', $plannerMd);
        if ($plannerMd !== null && preg_match('/<!--\s*runtime-core:start\s*-->(.*?)<!--\s*runtime-core:end\s*-->/s', $plannerMd, $coreMatch)) {
            $plannerCore = trim($coreMatch[1]);
            if ($plannerCore !== '') {
                $system .= "\n\n## Planner persona\n".$plannerCore;
            }
        }
        $system .= "\n\n".$this->projects->evidenceRuleForPrompt();

        $formattedMemory = $this->buildMemoryBlock($memoryContext);
        $conversationSummary = $this->buildConversationBlock($conversation);
        $semanticCodeContext = $this->buildSemanticCodeContext($prompt);

        // When semantic context is present the model already sees relevant file contents;
        // send a lighter repo header (root + dirs only) to save ~2 k tokens per call.
        $repoIndex = '';
        try {
            $repoIndex = $semanticCodeContext !== ''
                ? $this->discovery->repoIndexForPlanner(0)   // header only, no sample paths
                : $this->discovery->repoIndexForPlanner();
        } catch (\Throwable $e) {
            $repoIndex = 'Repo index unavailable: '.$e->getMessage();
        }

        $userPayload = [
            'prompt' => $prompt,
            'conversation_history' => $conversationSummary,
            'conversation_turns' => count($conversation),
            'prior_memory' => $formattedMemory,
            'skill_router' => Arr::except($routerContext, ['_scores']),
            'routing' => $modelRoute,
            'repo_index' => $repoIndex,
        ];
        if ($semanticCodeContext !== '') {
            $userPayload['semantic_code_context'] = $semanticCodeContext;
        }
        $user = json_encode($userPayload, JSON_THROW_ON_ERROR);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];

      try {
          $out = $this->fallback->chatWithFallbacks(
              $models,
              $messages,
              (float) ($orch['temperature'] ?? 0.2),
              $retry,
              'orchestrator',
              function (mixed $j): bool {
                  return is_array($j) && (isset($j['summary']) || isset($j['task_summary']));
              },
              (int) ($orch['max_tokens'] ?? 2048),
              $runId,
          );
          /** @var array<string, mixed> $decoded */
          $decoded = is_array($out['parsed']) ? $out['parsed'] : [];
          $decoded = $this->normalizePlan($decoded, $prompt, $routerContext, $modelRoute);
          $decoded['_planner_model'] = $out['model_used'];
          $decoded['_planner_model_resolved'] = $out['model_resolved'] ?? '';
          $decoded['_planner_fallback'] = $out['fallback_used'];

          return LlmTelemetry::mergeAgentResult($decoded, $out);
      } catch (\Throwable $e) {
          $message = LlmErrorFormatter::humanize($e->getMessage());

          return [
              'error' => true,
              'message' => $message,
          ];
      }
    }

    /**
     * @param array<string, mixed> $decoded
     * @param array<string, mixed> $routerContext
     * @param array<string, mixed> $modelRoute
     * @return array<string, mixed>
     */
    protected function normalizePlan(array $decoded, string $prompt, array $routerContext, array $modelRoute): array
    {
        $skill = (string) ($decoded['selected_skill'] ?? $routerContext['primary_skill']['name'] ?? $modelRoute['skill'] ?? 'general');
        $checklist = $decoded['checklist'] ?? null;

        if (! is_array($checklist) || $checklist === []) {
            $checklist = [
                [
                    'id' => 'plan-1',
                    'title' => 'Inspect relevant files',
                    'description' => 'Read only files needed for this task.',
                    'owner' => 'executor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-2',
                    'title' => 'Apply focused changes',
                    'description' => 'Modify only files related to the request.',
                    'owner' => 'executor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-3',
                    'title' => 'Audit result',
                    'description' => 'Check correctness, security, performance, and maintainability.',
                    'owner' => 'auditor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-4',
                    'title' => 'Fix audit feedback',
                    'description' => 'Apply required fixes if auditor returns needs_revision.',
                    'owner' => 'executor',
                    'status' => 'pending',
                ],
                [
                    'id' => 'plan-5',
                    'title' => 'Finalize response',
                    'description' => 'Summarize files changed, checks run, risks, and next step.',
                    'owner' => 'final-reviewer',
                    'status' => 'pending',
                ],
            ];
        }

        $decoded['task_summary'] = StringCoercion::toString(
            $decoded['task_summary'] ?? $decoded['summary'] ?? null,
            mb_substr($prompt, 0, 220),
        );
        $decoded['goal'] = StringCoercion::toString(
            $decoded['goal'] ?? null,
            'Complete the user request with focused changes and visible audit trail.',
        );
        $decoded['risk_level'] = StringCoercion::toString($decoded['risk_level'] ?? $modelRoute['risk_level'] ?? null, 'medium');
        $decoded['selected_skill'] = $skill;
        $decoded['memory_strategy'] = StringCoercion::toString($decoded['memory_strategy'] ?? $modelRoute['memory_mode'] ?? null, 'read_only');
        $decoded['expected_artifacts'] = is_array($decoded['expected_artifacts'] ?? null)
            ? $decoded['expected_artifacts']
            : ['files_changed', 'audit_findings', 'final_summary'];
        $decoded['checklist'] = array_values(array_map(
            fn ($item, $idx) => [
                'id' => StringCoercion::toString($item['id'] ?? null, 'plan-'.($idx + 1)),
                'title' => StringCoercion::toString($item['title'] ?? null, 'Plan item '.($idx + 1)),
                'description' => StringCoercion::toString($item['description'] ?? null),
                'target' => StringCoercion::toString($item['target'] ?? null),
                'success_criterion' => StringCoercion::toString($item['success_criterion'] ?? $item['description'] ?? null),
                'owner' => StringCoercion::toString($item['owner'] ?? null, 'executor'),
                'status' => StringCoercion::toString($item['status'] ?? null, 'pending'),
            ],
            $checklist,
            array_keys($checklist)
        ));
        $decoded['summary'] = StringCoercion::toString($decoded['summary'] ?? $decoded['task_summary'] ?? null);
        $decoded['target_file_list'] = is_array($decoded['target_file_list'] ?? null) ? $decoded['target_file_list'] : [];
        $decoded['allow_broad_repo_scan'] = (bool) ($decoded['allow_broad_repo_scan'] ?? ($decoded['target_file_list'] === []));
        $decoded['executor_profile'] = StringCoercion::toString($decoded['executor_profile'] ?? $modelRoute['executor_profile'] ?? null, 'default');
        $decoded['suggested_tests'] = is_array($decoded['suggested_tests'] ?? null) ? $decoded['suggested_tests'] : [];
        $decoded['risk_notes'] = is_array($decoded['risk_notes'] ?? null) ? $decoded['risk_notes'] : [];
        $decoded['constraints'] = is_array($decoded['constraints'] ?? null) ? $decoded['constraints'] : [];
        $decoded['handoff_message'] = StringCoercion::toString($decoded['handoff_message'] ?? null, 'Sending execution task to Executor.');
        $defaultMode = ($modelRoute['needs_executor'] ?? true) ? 'delegate_executor' : 'answer_only';
        $decoded['execution_mode'] = StringCoercion::toString($decoded['execution_mode'] ?? null, $defaultMode);
        $decoded['user_commands'] = is_array($decoded['user_commands'] ?? null) ? $decoded['user_commands'] : [];
        $decoded['confidence'] = is_numeric($decoded['confidence'] ?? null)
            ? min(1.0, max(0.0, (float) $decoded['confidence']))
            : 0.7;
        $decoded['planner_questions'] = is_array($decoded['planner_questions'] ?? null) ? $decoded['planner_questions'] : [];
        $decoded['execution_phases'] = is_array($decoded['execution_phases'] ?? null) ? $decoded['execution_phases'] : [];
        $decoded['design_phase_required'] = (bool) ($decoded['design_phase_required'] ?? false);
        if ($decoded['executor_profile'] === 'frontend_ui') {
            $decoded['design_phase_required'] = true;
        }
        $decoded['memory_applied'] = is_array($decoded['memory_applied'] ?? null) ? $decoded['memory_applied'] : [];
        $decoded['key_design_decisions'] = $this->normalizeStringList($decoded['key_design_decisions'] ?? null);
        $decoded['flow_steps'] = $this->normalizeStringList($decoded['flow_steps'] ?? null);
        $decoded['flow_diagram'] = $this->sanitizeFlowDiagram(
            StringCoercion::toString($decoded['flow_diagram'] ?? null, ''),
        );

        return $decoded;
    }

    /** @return list<string> */
    protected function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $line = trim(is_string($item) ? $item : (is_scalar($item) ? (string) $item : ''));
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    protected function sanitizeFlowDiagram(string $raw): string
    {
        $text = trim($raw);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^```(?:mermaid)?\s*\r?\n([\s\S]*?)\r?\n```\s*$/i', $text, $m)) {
            $text = trim($m[1]);
        }

        return $text;
    }

    /** @param list<array<string,mixed>> $memories */
    protected function buildMemoryBlock(array $memories): string
    {
        if ($memories === []) {
            return '(no prior memory context)';
        }
        $lines = [];
        foreach ($memories as $i => $m) {
            $summary = is_array($m) ? ($m['summary'] ?? $m['human_summary'] ?? $m['content'] ?? '') : (string) $m;
            $type = is_array($m) ? ($m['type'] ?? '') : '';
            $lines[] = '[Memory '.($i + 1).']'.($type !== '' ? ' ['.$type.']' : '').' '.mb_substr((string) $summary, 0, 400);
        }

        return implode("\n", $lines);
    }

    /**
     * Retrieve the top semantically-relevant code chunks for the current prompt and format
     * them as a compact block the planner can read to anchor its file-path recommendations.
     * Returns an empty string when the index is empty or embeddings are disabled.
     */
    protected function loadPlannerAgentsMd(): ?string
    {
        $path = rtrim((string) config('bossku.repo_root'), '/\\').'/agents/planner.md';

        return is_file($path) ? (string) File::get($path) : null;
    }

    protected function buildSemanticCodeContext(string $prompt): string
    {
        try {
            $activeProject = $this->paths->activeProject();
            $chunks = $this->codeIndex->retrieve($prompt, 8, $activeProject?->id);
            if ($chunks === []) {
                return '';
            }

            $lines = ['=== Semantically Relevant Code (ranked by similarity) ==='];
            foreach ($chunks as $chunk) {
                $loc = $chunk['path'];
                if (($chunk['start_line'] ?? null) !== null) {
                    $loc .= ':'.$chunk['start_line'].'-'.($chunk['end_line'] ?? $chunk['start_line']);
                }
                $score = isset($chunk['similarity']) ? ' score='.round((float) $chunk['similarity'], 2) : '';
                $lines[] = "[$loc$score]";
                $lines[] = mb_substr((string) $chunk['content'], 0, 1500);
                $lines[] = '---';
            }

            return implode("\n", $lines);
        } catch (\Throwable) {
            return '';
        }
    }

}
