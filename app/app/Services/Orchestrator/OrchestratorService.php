<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Skill;
use App\Services\BosskuAi\BosskuResponseIndicator;
use App\Services\BosskuAi\ContextBudgetGuard;
use App\Services\BosskuAi\RepoTaskDetector;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\PromptRouteClassifier;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\BosskuAi\SkillRouterService;
use App\Services\Graph\KnowledgeGraphBuilder;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\ProjectService;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrchestratorService
{
    use OrchestratorClarificationTrait;

    public function __construct(
        protected MemoryService $memory,
        protected SkillRouterService $router,
        protected PlannerService $planner,
        protected ClarificationService $clarification,
        protected ExecutorService $executor,
        protected AuditorService $auditor,
        protected SecurityAuditorService $securityAuditor,
        protected FinalReviewerService $finalReviewer,
        protected DirectAnswerService $directAnswer,
        protected WriterService $writer,
        protected ToolRegistry $tools,
        protected RuntimeSettings $settings,
        protected PromptRouteClassifier $promptRouteClassifier,
        protected ContextBudgetGuard $budgetGuard,
        protected ModelRoutingConfig $modelConfig,
        protected RunEventFactory $events,
        protected ProjectPathResolver $paths,
        protected ProjectService $projects,
        protected KnowledgeGraphBuilder $knowledgeGraph,
    ) {}

    /**
     * @param  callable(array<string,mixed>): void|null  $emit
     * @return array<string,mixed>
     */
    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @return array<string,mixed>
     */
    public function run(string $prompt, ?callable $emit = null, array $conversation = []): array
    {
        $tRun = microtime(true);
        $tokenAcc = 0;
        $userPrompt = $prompt;
        $prompt = $this->effectivePrompt($userPrompt, $conversation);
        $agentPrompt = trim($prompt."\n\n".$this->projects->agentWorkspaceContext());

        $runMeta = ['conversation_turns' => count($conversation)];
        $activeProject = $this->paths->activeProject();
        if ($activeProject !== null) {
            $runMeta['active_project_id'] = $activeProject->id;
            $runMeta['active_project_name'] = $activeProject->name;
        }

        $run = Run::query()->create([
            'prompt' => $userPrompt,
            'status' => 'running',
            'metadata' => $runMeta,
        ]);

        $this->emit($emit, $this->basePayload($run, 'run_started', [
            'status' => 'success',
            'summary' => 'Run started.',
            'message' => 'BosskuAI is preparing the Ollama agent workflow.',
        ]));

        $t0 = microtime(true);
        $classified = $this->promptRouteClassifier->classify($agentPrompt);
        /** @var array<string, mixed> $modelRoute */
        $modelRoute = $classified['route'];
        $modelsResolved = $classified['models_resolved'];
        $routerMeta = $classified['router_meta'];
        $routerMs = (int) round((microtime(true) - $t0) * 1000);
        $routerJson = json_encode($modelRoute) ?: '';
        $routerTok = $this->estimateTokens($routerJson);

        $this->logStep($run, -2, 'model_router', $modelsResolved['router'] ?? null, $routerMeta['provider'] ?? null, null, 'success', $agentPrompt, $routerJson, $routerJson, null, null, null, $routerMs, $routerTok, null, [
            'routing_decision' => $modelRoute,
            'models_resolved' => $modelsResolved,
            'router_meta' => $routerMeta,
        ]);
        $tokenAcc += $routerTok;

        $this->emit($emit, $this->basePayload($run, 'model_router_done', [
            'status' => 'success',
            'agent' => 'router',
            'model_role' => 'fast',
            'model' => $modelsResolved['router'] ?? null,
            'summary' => 'Model router selected Ollama role models.',
            'message' => 'Routing is role-based: reasoning, coding, review, and fast.',
            'latency_ms' => $routerMs,
            'routing' => $modelRoute,
            'models' => $modelsResolved,
            'router_meta' => $routerMeta,
            'artifacts' => [
                'routing_decision' => $modelRoute,
                'models_resolved' => $modelsResolved,
            ],
        ]));

        $memoryMode = (string) ($modelRoute['memory_mode'] ?? 'read_only');
        $memPayload = [];
        $memMs = 0;
        $memTokens = 0;

        if ($memoryMode !== 'none') {
            $t0 = microtime(true);
            $memories = $this->memory->search($agentPrompt, $this->settings->maxMemoryResults());
            $memPayload = $memories->map(fn (Memory $m) => [
                'id' => $m->id,
                'summary' => $m->human_summary ?: Str::limit($m->content, 200),
                'type' => $m->type,
            ])->values()->all();
            foreach ($memories as $m) {
                MemoryRunLink::query()->firstOrCreate(
                    [
                        'memory_id' => $m->id,
                        'run_id' => $run->id,
                    ],
                    ['similarity_score' => null]
                );
            }
            $memMs = (int) round((microtime(true) - $t0) * 1000);
            $memTokens = $this->estimateTokens(json_encode($memPayload) ?: '');

            $this->logStep($run, 0, 'memory_retrieval', null, null, null, 'success', null, json_encode(['query' => $prompt]), json_encode($memPayload), null, null, null, $memMs, $memTokens, null, [
                'memory_used' => $memPayload,
            ]);
            $tokenAcc += $memTokens;

            $this->emit($emit, $this->basePayload($run, 'memory_retrieved', [
                'status' => 'success',
                'agent' => 'memory',
                'model_role' => 'fast',
                'summary' => count($memPayload).' memory item(s) retrieved.',
                'message' => 'Memory context is available for planning.',
                'memory_used' => $memPayload,
                'latency_ms' => $memMs,
                'token_estimate' => $memTokens,
                'artifacts' => [
                    'memory_used' => $memPayload,
                ],
            ]));
        }

        if ($this->shouldRequireClarification($run)) {
            $clarification = $this->clarification->ask($userPrompt, $conversation, $modelRoute, 'pre_execution', []);
            if ($clarification['questions'] !== [] && ! $clarification['ready_to_proceed']) {
                return $this->pauseForClarification(
                    $run,
                    $clarification,
                    'pre_execution',
                    [
                        'user_prompt' => $userPrompt,
                        'effective_prompt' => $prompt,
                        'agent_prompt' => $agentPrompt,
                        'conversation' => $conversation,
                        'model_route' => $modelRoute,
                        'models_resolved' => $modelsResolved,
                        'router_meta' => $routerMeta,
                        'mem_payload' => $memPayload,
                        'token_acc' => $tokenAcc,
                        't_run' => $tRun,
                    ],
                    $emit,
                );
            }
        }

        return $this->runPipelineAfterMemory(
            $run,
            $userPrompt,
            $prompt,
            $agentPrompt,
            $conversation,
            $modelRoute,
            $modelsResolved,
            $routerMeta,
            $memPayload,
            $emit,
            $tokenAcc,
            $tRun,
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string>  $modelsResolved
     * @param  array<string, mixed>  $routerMeta
     * @param  list<array<string, mixed>>  $memPayload
     * @return array<string, mixed>
     */
    protected function runPipelineAfterMemory(
        Run $run,
        string $userPrompt,
        string $prompt,
        string $agentPrompt,
        array $conversation,
        array $modelRoute,
        array $modelsResolved,
        array $routerMeta,
        array $memPayload,
        ?callable $emit,
        int $tokenAcc,
        float $tRun,
    ): array {
        $activeProject = $this->paths->activeProject();
        $workflow = (string) ($modelRoute['workflow'] ?? 'orchestrator_executor_auditor');

        if ($workflow === 'direct_answer') {
            return $this->finishShortPath(
                $run,
                $prompt,
                $modelRoute,
                $modelsResolved,
                $memPayload,
                $emit,
                $tokenAcc,
                $tRun,
                'direct_answer',
                fn () => $this->directAnswer->answer($prompt, $modelRoute)
            );
        }

        if ($workflow === 'writer_only') {
            return $this->finishShortPath(
                $run,
                $prompt,
                $modelRoute,
                $modelsResolved,
                $memPayload,
                $emit,
                $tokenAcc,
                $tRun,
                'writer_only',
                fn () => $this->writer->write($prompt, $modelRoute)
            );
        }

        $t0 = microtime(true);
        $routerCtx = $this->router->route($agentPrompt, collect([]));
        $routerMs2 = (int) round((microtime(true) - $t0) * 1000);
        $routerTokens2 = $this->estimateTokens(json_encode($routerCtx) ?: '');

        $this->logStep($run, 1, 'skill_router', null, null, null, 'success', null, $prompt, json_encode($routerCtx), null, null, null, $routerMs2, $routerTokens2, null, [
            'memory_used' => $memPayload,
        ]);
        $tokenAcc += $routerTokens2;

        $this->emit($emit, $this->basePayload($run, 'skill_router_done', [
            'status' => 'success',
            'agent' => 'router',
            'model_role' => 'fast',
            'summary' => 'Skill router selected the execution context.',
            'message' => (string) ($routerCtx['primary_skill']['name'] ?? 'No primary skill selected.'),
            'latency_ms' => $routerMs2,
            'token_estimate' => $routerTokens2,
            'input' => $prompt,
            'output' => json_encode($routerCtx),
            'artifacts' => [
                'routing_context' => $routerCtx,
                'skills_used' => [$routerCtx['primary_skill']['name'] ?? null],
            ],
        ]));

        $repoAvailable = true;
        $repoError = '';
        $repoRoot = '';
        try {
            $repoRoot = $this->paths->repoRoot();
        } catch (\Throwable $e) {
            $repoAvailable = false;
            $repoError = $e->getMessage();
        }

        if (! $repoAvailable && (RepoTaskDetector::requiresRepositoryAccess($userPrompt) || $this->promptMentionsRepo($agentPrompt))) {
            $failMsg = 'Active project is not mounted: '.$repoError.' Register and activate the project under Project → Paths first.';
            $run->update([
                'status' => 'failed',
                'total_latency_ms' => (int) round((microtime(true) - $tRun) * 1000),
                'total_token_estimate' => $tokenAcc,
            ]);
            $this->emit($emit, $this->basePayload($run, 'run_failed', [
                'status' => 'fail',
                'stage' => 'repo_unavailable',
                'error' => $failMsg,
                'summary' => $failMsg,
            ]));

            return [
                'run_id' => $run->id,
                'final_output' => '',
                'steps' => [],
                'memory_used' => $memPayload,
                'skills_used' => [],
                'rules_used' => [],
                'playbooks_used' => [],
                'audit' => ['error' => $failMsg],
                'routing' => $modelRoute,
            ];
        }

        $this->emit($emit, $this->basePayload($run, 'planner_started', [
            'status' => 'running',
            'agent' => 'orchestrator',
            'model_role' => 'reasoning',
            'summary' => 'Orchestrator is planning the task.',
        ]));
        $t0 = microtime(true);
        try {
            $this->emit($emit, $this->basePayload($run, 'active_project', [
                'status' => $repoAvailable ? 'success' : 'fail',
                'summary' => 'Active project: '.($activeProject?->name ?? 'default /repo'),
                'message' => $this->projects->agentWorkspaceContext(),
                'repo_root' => $repoRoot,
                'repo_mounted' => $repoAvailable,
                'repo_error' => $repoAvailable ? null : $repoError,
                'active_project' => $activeProject?->only(['id', 'name', 'host_path', 'container_path']),
            ]));
        } catch (\Throwable) {
            //
        }

        $plan = $this->planner->plan($agentPrompt, $memPayload, $routerCtx, $modelRoute);
        $planMs = (int) round((microtime(true) - $t0) * 1000);
        $planTokens = $this->estimateTokens(json_encode($plan) ?: '');

        if (! empty($plan['error'])) {
            $orchModel = (string) ($plan['_planner_model'] ?? $modelsResolved['orchestrator'] ?? '');
            $plannerErr = (string) ($plan['message'] ?? 'Planner failed');
            $this->logStep($run, 2, 'planner', $orchModel, null, null, 'failed', $prompt, json_encode(['router' => $routerCtx, 'route' => $modelRoute]), json_encode($plan), null, null, null, $planMs, $planTokens, $plannerErr, null);
            $run->update(['status' => 'failed', 'total_latency_ms' => (int) round((microtime(true) - $tRun) * 1000), 'total_token_estimate' => $tokenAcc + $planTokens]);

            $this->emit($emit, $this->basePayload($run, 'planner_failed', [
                'status' => 'fail',
                'latency_ms' => $planMs,
                'model' => $orchModel,
                'error' => $plannerErr,
            ]));
            $this->emit($emit, $this->basePayload($run, 'run_failed', [
                'status' => 'fail',
                'stage' => 'planner',
                'error' => $plannerErr,
            ]));

            return [
                'run_id' => $run->id,
                'final_output' => '',
                'steps' => [],
                'memory_used' => $memPayload,
                'skills_used' => [],
                'rules_used' => [],
                'playbooks_used' => [],
                'audit' => $plan,
                'routing' => $modelRoute,
            ];
        }

        $orchModel = (string) ($plan['_planner_model'] ?? $modelsResolved['orchestrator'] ?? $this->settings->plannerModel());
        $this->logStep($run, 2, 'planner', $orchModel, null, null, 'success', $prompt, json_encode(['router' => $routerCtx, 'route' => $modelRoute]), json_encode($plan), null, null, null, $planMs, $planTokens, null, $this->events->metadata(
            'orchestrator',
            'reasoning',
            'Planner created '.count($plan['checklist'] ?? []).'-step execution checklist.',
            (string) ($plan['handoff_message'] ?? 'Sending execution task to Executor.'),
            ['plan' => $plan, 'checklist' => $plan['checklist'] ?? []],
            'orchestrator',
            'executor'
        ));
        $tokenAcc += $planTokens;

        $modelsResolved['orchestrator'] = $orchModel;

        $this->emit($emit, $this->events->plannerDone($run, $plan, $orchModel, $planMs, $planTokens));

        $execProfileKey = (string) ($plan['executor_profile'] ?? $modelRoute['executor_profile'] ?? 'default');
        $plan = $this->budgetGuard->narrowPlan($plan, $execProfileKey);

        if (
            RepoTaskDetector::requiresRepositoryAccess($userPrompt)
            || ($modelRoute['needs_repo_context'] ?? false)
        ) {
            $workflow = 'orchestrator_executor_auditor';
            $modelRoute['workflow'] = $workflow;
            $modelRoute['needs_executor'] = true;
            $modelRoute['needs_auditor'] = true;
        }

        $mustRunExecutor = ($modelRoute['needs_executor'] ?? true)
            || ($modelRoute['needs_security_auditor'] ?? false)
            || RepoTaskDetector::requiresRepositoryAccess($userPrompt);

        if (($workflow === 'orchestrator_only' || ! ($modelRoute['needs_executor'] ?? true)) && ! $mustRunExecutor) {
            $body = (string) ($plan['summary'] ?? json_encode($plan));
            $indicator = BosskuResponseIndicator::line($modelRoute, array_merge($modelsResolved, ['executor' => 'skipped']));

            return $this->completeRun(
                $run,
                $prompt,
                BosskuResponseIndicator::prepend($body, $indicator),
                $modelRoute,
                $modelsResolved,
                $memPayload,
                $routerCtx,
                $plan,
                [],
                [],
                null,
                null,
                $emit,
                $tokenAcc,
                $tRun
            );
        }

        $preflightReads = $this->preflightReadTargetFiles($run, $plan, $emit);

        $skillName = (string) ($routerCtx['primary_skill']['name'] ?? 'cofounder');
        $step = [
            'id' => 1,
            'title' => (string) ($plan['summary'] ?? 'Execute'),
            'task' => $agentPrompt,
            'skill' => $skillName,
            'tool' => null,
        ];

        $skill = Skill::query()->where('name', $skillName)->first();
        $skillRow = [
            'name' => $skillName,
            'content' => $skill ? Str::limit($skill->content, 8000) : '',
        ];
        $ruleLines = $this->pickRules($routerCtx, $step);
        $pbExcerpt = $this->pickPlaybookExcerpt($routerCtx, $step);
        $chkExcerpt = $this->pickChecklistExcerpt($routerCtx, $step);

        $this->emit($emit, $this->basePayload($run, 'executor_step_started', [
            'status' => 'running',
            'agent' => 'executor',
            'model_role' => 'coding',
            'from_agent' => 'orchestrator',
            'to_agent' => 'executor',
            'summary' => 'Executor is applying the plan.',
            'message' => (string) ($plan['handoff_message'] ?? 'Executor received the plan.'),
            'step_number' => 3,
            'skill' => $skillName,
            'model' => $modelsResolved['executor'] ?? '',
        ]));

        $execResult = $this->executor->execute(
            $step,
            $skillRow,
            $ruleLines,
            $pbExcerpt,
            $chkExcerpt,
            null,
            $plan,
            $modelRoute,
            $execProfileKey,
            $this->projects->agentWorkspaceContext(),
            $preflightReads,
        );
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $preflightReads);
        $execResult = $this->ensureExecutorEvidence($run, $plan, $execResult, $userPrompt, $emit);
        $modelsResolved['executor'] = (string) ($execResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');

        $exTok = $this->estimateTokens(json_encode($execResult) ?: '');
        $this->logStep($run, 3, 'executor', $modelsResolved['executor'], 'ollama', $skillName, ($execResult['status'] ?? '') === 'failed' ? 'failed' : 'success', json_encode($step), json_encode($execResult), json_encode($execResult), null, null, null, (int) ($execResult['latency_ms'] ?? 0), $exTok, null, $this->events->metadata(
            'executor',
            'coding',
            'Executor completed the requested changes.',
            (string) ($execResult['handoff_message'] ?? 'Sending changes to Auditor.'),
            $this->events->executorArtifacts($execResult),
            'executor',
            'auditor'
        ));
        $tokenAcc += $exTok;
        $this->emit($emit, $this->events->executorDone($run, $execResult, $modelsResolved['executor'], (int) ($execResult['latency_ms'] ?? 0), $exTok));

        if (! empty($execResult['tool_request'] ?? null)) {
            $this->tools->invoke($run->id, null, $execResult['tool_request'], $emit);
        }

        if ($this->shouldPauseForExecutorStuck($run, $execResult, null, 0)) {
            return $this->pauseForExecutorStuck(
                $run,
                $this->buildExecutorPipelineSnapshot(
                    $run,
                    $userPrompt,
                    $prompt,
                    $agentPrompt,
                    $conversation,
                    $modelRoute,
                    $modelsResolved,
                    $routerCtx,
                    $memPayload,
                    $plan,
                    $workflow,
                    $preflightReads,
                    $execProfileKey,
                    $skillName,
                    $step,
                    $skillRow,
                    $ruleLines,
                    $pbExcerpt,
                    $chkExcerpt,
                    $tokenAcc,
                    $tRun,
                    null,
                ),
                $execResult,
                $emit,
            );
        }

        return $this->runPostExecutorPhase(
            $run,
            $userPrompt,
            $prompt,
            $agentPrompt,
            $conversation,
            $modelRoute,
            $modelsResolved,
            $memPayload,
            $routerCtx,
            $plan,
            $workflow,
            $execResult,
            $step,
            $skillRow,
            $skillName,
            $ruleLines,
            $pbExcerpt,
            $chkExcerpt,
            $preflightReads,
            $execProfileKey,
            $emit,
            $tokenAcc,
            $tRun,
            [$execResult],
            3,
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string>  $modelsResolved
     * @param  list<array<string, mixed>>  $memPayload
     * @param  array<string, mixed>  $routerCtx
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $skillRow
     * @param  list<string>  $ruleLines
     * @param  list<array<string, mixed>>  $preflightReads
     * @param  list<array<string, mixed>>  $executorOutputs
     * @return array<string, mixed>
     */
    protected function runPostExecutorPhase(
        Run $run,
        string $userPrompt,
        string $prompt,
        string $agentPrompt,
        array $conversation,
        array $modelRoute,
        array $modelsResolved,
        array $memPayload,
        array $routerCtx,
        array $plan,
        string $workflow,
        array $execResult,
        array $step,
        array $skillRow,
        string $skillName,
        array $ruleLines,
        string $pbExcerpt,
        string $chkExcerpt,
        array $preflightReads,
        string $execProfileKey,
        ?callable $emit,
        int $tokenAcc,
        float $tRun,
        array $executorOutputs,
        int $stepNum,
    ): array {
        $lastAudit = [];
        $lastSecurity = null;
        $lastFinal = null;
        $revisionRoundsUsed = 0;

        $needsAuditor = ($modelRoute['needs_auditor'] ?? true)
            && $this->settings->auditEnabled()
            && (str_contains($workflow, 'auditor'));

        if ($needsAuditor && ($execResult['needs_audit'] ?? true)) {
            $execResult = $this->ensureExecutorEvidence($run, $plan, $execResult, $userPrompt, $emit);

            $this->emit($emit, $this->basePayload($run, 'auditor_started', [
                'status' => 'running',
                'agent' => 'auditor',
                'model_role' => 'review',
                'from_agent' => 'executor',
                'to_agent' => 'auditor',
                'summary' => 'Auditor is reviewing executor output.',
                'step_number' => $stepNum,
            ]));
            $tA = microtime(true);
            $lastAudit = $this->auditor->auditStep(
                $agentPrompt,
                $routerCtx,
                $modelRoute,
                $plan,
                $step,
                $execResult,
                $ruleLines,
                $chkExcerpt,
                ($modelRoute['risk_level'] ?? '') === 'high'
            );
            $auditMs = (int) round((microtime(true) - $tA) * 1000);
            $auditTok = $this->estimateTokens(json_encode($lastAudit) ?: '');
            $modelsResolved['auditor'] = (string) ($lastAudit['_auditor_model'] ?? $modelsResolved['auditor'] ?? '');
            $pass = ($lastAudit['_legacy_pass'] ?? false) === true;
            $this->logStep($run, $stepNum + 100, 'auditor', $modelsResolved['auditor'], 'ollama', $skillName, $pass ? 'success' : (($lastAudit['status'] ?? '') === 'needs_revision' ? 'needs_revision' : 'failed'), json_encode($step), json_encode($execResult), json_encode($lastAudit), null, null, null, $auditMs, $auditTok, null, $this->events->metadata(
                'auditor',
                'review',
                (string) ($lastAudit['summary'] ?? 'Audit completed.'),
                (($lastAudit['status'] ?? '') === 'needs_revision') ? 'Returning feedback to Executor.' : 'Sending audit result to Final Reviewer.',
                ['audit' => $lastAudit, 'audit_findings' => $lastAudit['findings'] ?? []],
                'auditor',
                (($lastAudit['status'] ?? '') === 'needs_revision') ? 'executor' : 'final-reviewer'
            ));
            $tokenAcc += $auditTok;
            $this->emit($emit, $this->events->auditorDone($run, $lastAudit, $modelsResolved['auditor'], $auditMs, $auditTok));

            if (($lastAudit['status'] ?? '') === 'needs_revision' && $this->settings->maxRevisionRounds() > 0) {
                $this->emit($emit, $this->basePayload($run, 'executor_revision_started', [
                    'status' => 'running',
                    'agent' => 'executor',
                    'model_role' => 'coding',
                    'from_agent' => 'auditor',
                    'to_agent' => 'executor',
                    'summary' => 'Executor is applying audit feedback.',
                    'message' => (string) ($lastAudit['summary'] ?? 'Audit requested a revision.'),
                ]));

                $revisionStep = array_merge($step, [
                    'id' => 2,
                    'title' => 'Fix audit feedback',
                ]);
                $revisionResult = $this->executor->execute(
                    $revisionStep,
                    $skillRow,
                    $ruleLines,
                    $pbExcerpt,
                    $chkExcerpt,
                    null,
                    $plan,
                    $modelRoute,
                    $execProfileKey,
                    $this->projects->agentWorkspaceContext(),
                    $preflightReads,
                    [
                        'original_prompt' => $agentPrompt,
                        'original_plan' => $plan,
                        'executor_result' => $execResult,
                        'audit_findings' => $lastAudit['findings'] ?? [],
                        'required_fixes' => $lastAudit['required_fixes'] ?? [],
                    ]
                );
                $revisionResult = ExecutorEvidenceSupport::mergePreflightReads($revisionResult, $preflightReads);
                $revisionResult = $this->ensureExecutorEvidence($run, $plan, $revisionResult, $userPrompt, $emit);
                $modelsResolved['executor'] = (string) ($revisionResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');
                $revTok = $this->estimateTokens(json_encode($revisionResult) ?: '');
                $this->logStep($run, 4, 'executor_revision', $modelsResolved['executor'], 'ollama', $skillName, ($revisionResult['status'] ?? '') === 'failed' ? 'failed' : 'success', json_encode($revisionStep), json_encode(['audit' => $lastAudit, 'previous_executor' => $execResult]), json_encode($revisionResult), null, null, null, (int) ($revisionResult['latency_ms'] ?? 0), $revTok, null, $this->events->metadata(
                    'executor',
                    'coding',
                    'Executor applied audit follow-up fixes.',
                    'Sending revised result to Final Reviewer.',
                    $this->events->executorArtifacts($revisionResult),
                    'executor',
                    'final-reviewer'
                ));
                $tokenAcc += $revTok;
                $this->emit($emit, $this->events->executorDone($run, $revisionResult, $modelsResolved['executor'], (int) ($revisionResult['latency_ms'] ?? 0), $revTok, 'executor_revision_done'));
                $execResult = $revisionResult;
                $executorOutputs[] = $revisionResult;
                $revisionRoundsUsed = 1;

                if ($this->shouldPauseForExecutorStuck($run, $execResult, $lastAudit, $revisionRoundsUsed)) {
                    return $this->pauseForExecutorStuck(
                        $run,
                        $this->buildExecutorPipelineSnapshot(
                            $run,
                            $userPrompt,
                            $prompt,
                            $agentPrompt,
                            $conversation,
                            $modelRoute,
                            $modelsResolved,
                            $routerCtx,
                            $memPayload,
                            $plan,
                            $workflow,
                            $preflightReads,
                            $execProfileKey,
                            $skillName,
                            $step,
                            $skillRow,
                            $ruleLines,
                            $pbExcerpt,
                            $chkExcerpt,
                            $tokenAcc,
                            $tRun,
                            [
                                'original_prompt' => $agentPrompt,
                                'audit_findings' => $lastAudit['findings'] ?? [],
                            ],
                        ),
                        $execResult,
                        $emit,
                    );
                }
            }
        }

        if (($modelRoute['needs_security_auditor'] ?? false)) {
            $execResult = $this->ensureExecutorEvidence($run, $plan, $execResult, $userPrompt, $emit);

            $this->emit($emit, $this->basePayload($run, 'security_auditor_started', ['status' => 'running']));
            $tS = microtime(true);
            if (! ExecutorEvidenceSupport::hasReadEvidence($execResult, ExecutorEvidenceSupport::toolEvidenceForRun($run->id))) {
                $lastSecurity = ExecutorEvidenceSupport::deterministicNoFilesRead();
            } else {
                $lastSecurity = $this->securityAuditor->audit($agentPrompt, $modelRoute, $plan, $execResult, $run->id);
            }
            $sMs = (int) round((microtime(true) - $tS) * 1000);
            $sTok = $this->estimateTokens(json_encode($lastSecurity) ?: '');
            $this->logStep($run, $stepNum + 150, 'security_auditor', $modelsResolved['security_auditor'] ?? null, null, $skillName, 'success', null, null, json_encode($lastSecurity), null, null, null, $sMs, $sTok, null, null);
            $tokenAcc += $sTok;
            $this->emit($emit, $this->basePayload($run, 'security_auditor_done', [
                'status' => 'success',
                'latency_ms' => $sMs,
                'output' => json_encode($lastSecurity),
            ]));
        }

        if (($modelRoute['needs_final_reviewer'] ?? false) && ($modelRoute['risk_level'] ?? '') === 'high') {
            $this->emit($emit, $this->basePayload($run, 'final_reviewer_started', [
                'status' => 'running',
                'agent' => 'final-reviewer',
                'model_role' => 'reasoning',
                'from_agent' => 'auditor',
                'to_agent' => 'final-reviewer',
                'summary' => 'Final Reviewer is closing the run.',
            ]));
            $tF = microtime(true);
            $lastFinal = $this->finalReviewer->review($agentPrompt, $modelRoute, $lastAudit, $lastSecurity, $execResult);
            $fMs = (int) round((microtime(true) - $tF) * 1000);
            $fTok = $this->estimateTokens(json_encode($lastFinal) ?: '');
            $this->logStep($run, $stepNum + 200, 'final_reviewer', $modelsResolved['final_reviewer'] ?? null, 'ollama', $skillName, 'success', null, null, json_encode($lastFinal), null, null, null, $fMs, $fTok, null, $this->events->metadata(
                'final-reviewer',
                'reasoning',
                (string) ($lastFinal['reason'] ?? 'Final review completed.'),
                'Final reviewer closed the run.',
                ['final_review' => $lastFinal],
                'final-reviewer',
                'system'
            ));
            $tokenAcc += $fTok;
            $this->emit($emit, $this->events->finalReviewerDone($run, $lastFinal, $modelsResolved['final_reviewer'] ?? null, $fMs, $fTok));
        }
        $finalOutput = $this->composeUserOutput($lastAudit, $execResult, $lastFinal, $lastSecurity, $modelRoute, $modelsResolved, $memPayload);

        return $this->completeRun(
            $run,
            $prompt,
            $finalOutput,
            $modelRoute,
            $modelsResolved,
            $memPayload,
            $routerCtx,
            $plan,
            $executorOutputs,
            $lastAudit,
            $lastSecurity,
            $lastFinal,
            $emit,
            $tokenAcc,
            $tRun
        );
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string> $modelsResolved
     * @param  array<int, array<string, mixed>>  $memPayload
     */
    protected function finishShortPath(
        Run $run,
        string $prompt,
        array $modelRoute,
        array $modelsResolved,
        array $memPayload,
        ?callable $emit,
        int $tokenAcc,
        float $tRun,
        string $kind,
        callable $generator
    ): array {
        $t0 = microtime(true);
        $body = $generator();
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $tok = $this->estimateTokens($body);

        if ($kind === 'direct_answer') {
            $indicatorModels = [
                'router' => $modelsResolved['router'] ?? '',
                'orchestrator' => '',
                'executor' => '',
                'auditor' => '',
                'direct_answer' => $modelsResolved['direct_answer'] ?? $modelsResolved['router'] ?? '',
            ];
        } else {
            $indicatorModels = [
                'router' => $modelsResolved['router'] ?? '',
                'orchestrator' => '',
                'executor' => '',
                'auditor' => '',
                'writer' => $modelsResolved['writer'] ?? '',
            ];
        }

        $indicator = BosskuResponseIndicator::line($modelRoute, $indicatorModels);
        $final = BosskuResponseIndicator::prepend($body, $indicator);

        $this->logStep($run, 5, $kind, $modelsResolved['direct_answer'] ?? $modelsResolved['writer'] ?? null, null, null, 'success', $prompt, $prompt, $final, null, null, null, $ms, $tok, null, [
            'routing_decision' => $modelRoute,
            'models_resolved' => $modelsResolved,
        ]);

        $memoryMode = (string) ($modelRoute['memory_mode'] ?? 'read_only');
        $this->writeMemoryIfNeeded($memoryMode, $prompt, $modelRoute, $modelsResolved, ['patch_summary' => Str::limit($body, 2000)], [], null, null);

        $totalMs = (int) round((microtime(true) - $tRun) * 1000);
        $run->update([
            'final_output' => $final,
            'status' => 'completed',
            'total_latency_ms' => $totalMs,
            'total_token_estimate' => $tokenAcc + $tok,
            'metadata' => [
                'routing_decision' => $modelRoute,
                'models_resolved' => $modelsResolved,
                'short_path' => $kind,
            ],
        ]);

        $this->emit($emit, $this->events->runCompleted($run, $final, $totalMs, $tokenAcc + $tok, $modelRoute, $modelsResolved));

        Log::info('bosskuai.run.complete', [
            'run_id' => $run->id,
            'workflow' => $modelRoute['workflow'] ?? null,
            'risk' => $modelRoute['risk_level'] ?? null,
            'skill' => $modelRoute['skill'] ?? null,
            'path' => $kind,
        ]);

        return [
            'run_id' => $run->id,
            'final_output' => $final,
            'steps' => [],
            'memory_used' => $memPayload,
            'skills_used' => [],
            'rules_used' => [],
            'playbooks_used' => [],
            'audit' => [],
            'routing' => $modelRoute,
        ];
    }

    /**
     * @param array<string, mixed>|null $lastFinal
     * @param array<string, mixed> $modelRoute
     * @param array<string, string> $modelsResolved
     * @param array<int, array<string, mixed>> $memPayload
     */
    protected function composeUserOutput(
        array $lastAudit,
        array $execResult,
        ?array $lastFinal,
        ?array $lastSecurity,
        array $modelRoute,
        array $modelsResolved,
        array $memPayload,
    ): string {
        $status = (($execResult['status'] ?? '') === 'failed' || ($lastAudit['status'] ?? '') === 'failed') ? 'Partially Completed' : 'Completed';
        $files = array_values(array_filter(array_map(fn ($file) => is_array($file) ? (string) ($file['path'] ?? '') : (string) $file, $execResult['files_changed'] ?? [])));
        $commands = array_values(array_filter(array_map(fn ($command) => is_array($command) ? (string) ($command['command'] ?? '') : (string) $command, $execResult['commands_run'] ?? [])));
        $risks = $execResult['known_issues'] ?? [];
        if (($lastAudit['optional_improvements'] ?? []) !== []) {
            $risks = array_merge($risks, $lastAudit['optional_improvements']);
        }
        if (is_array($lastSecurity['security_issues'] ?? null) && $lastSecurity['security_issues'] !== []) {
            $risks = array_merge($risks, $lastSecurity['security_issues']);
        }

        $auditStatus = (string) ($lastAudit['status'] ?? 'not_run');
        $nextStep = 'Review the changed files and run any missing checks before merge.';
        if ($commands === []) {
            $nextStep = 'Run the relevant test suite before merge.';
        } elseif ($lastFinal !== null && ($lastFinal['required_actions'] ?? []) !== []) {
            $nextStep = implode('; ', $lastFinal['required_actions']);
        }

        $lines = [
            '[BOSSKUAI]',
            'Skill: '.(string) ($modelRoute['skill'] ?? 'general'),
            'Agent: final-reviewer',
            'Model Role: reviewer',
            'Model Backend: Ollama',
            'Memory Used: '.($memPayload !== [] ? 'yes' : 'no'),
            '',
            '## Status',
            $status,
            '',
            '## What changed',
            (string) ($execResult['patch_summary'] ?? $lastAudit['final_output'] ?? 'No change summary recorded.'),
            '',
            '## Files changed',
            ...($files !== [] ? array_map(fn ($file) => '- '.$file, $files) : ['- No files recorded']),
            '',
            '## Checks run',
            ...($commands !== [] ? array_map(fn ($command) => '- '.$command, $commands) : ['- No checks recorded']),
            '',
            '## Audit result',
            $auditStatus,
            '',
            '## Remaining risks',
            ...($risks !== [] ? array_map(fn ($risk) => '- '.$this->formatRiskLine($risk), $risks) : ['- Full verification status depends on the checks recorded above.']),
            '',
            '## Next recommended step',
            $nextStep,
        ];

        return implode("\n", $lines);
    }

    protected function formatRiskLine(mixed $risk): string
    {
        if (! is_array($risk)) {
            return (string) $risk;
        }

        $issue = (string) ($risk['issue'] ?? $risk['title'] ?? 'Risk');
        $severity = strtoupper((string) ($risk['severity'] ?? 'medium'));
        $location = (string) ($risk['location'] ?? '');
        $description = (string) ($risk['description'] ?? $risk['recommendation'] ?? '');

        $line = "[{$severity}] {$issue}";
        if ($location !== '') {
            $line .= " ({$location})";
        }
        if ($description !== '' && $description !== $issue) {
            $line .= ' — '.$description;
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string>  $modelsResolved
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastSecurity
     * @param  array<string, mixed>|null  $lastFinal
     */
    protected function writeMemoryIfNeeded(
        string $memoryMode,
        string $prompt,
        array $modelRoute,
        array $modelsResolved,
        array $execResult,
        array $lastAudit,
        ?array $lastSecurity,
        ?array $lastFinal
    ): void {
        if (! $this->settings->memoryStorageEnabled()) {
            return;
        }
        if (! in_array($memoryMode, ['write_after_task', 'read_and_write'], true)) {
            return;
        }

        $summary = [
            'task_summary' => Str::limit($prompt, 500),
            'skill' => $modelRoute['skill'] ?? null,
            'risk_level' => $modelRoute['risk_level'] ?? null,
            'workflow' => $modelRoute['workflow'] ?? null,
            'files_changed' => $execResult['files_changed'] ?? [],
            'model_route' => $modelsResolved,
            'audit_status' => $lastAudit['status'] ?? null,
            'security_audit_status' => $lastSecurity['status'] ?? null,
            'final_reviewer' => $lastFinal['decision'] ?? null,
            'lessons' => $execResult['known_issues'] ?? [],
            'ts' => now()->toIso8601String(),
        ];

        try {
            $this->memory->store(
                json_encode($summary, JSON_THROW_ON_ERROR),
                'routing_run',
                ['routing' => true],
                Str::limit($prompt, 200),
                ['bosskuai', 'routing'],
                'orchestrator'
            );
        } catch (\Throwable) {
            //
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $memPayload
     * @param  array<string, mixed>  $routerCtx
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $executorOutputs
     * @param  array<string, mixed>  $lastAudit
     */
    protected function completeRun(
        Run $run,
        string $prompt,
        string $finalOutput,
        array $modelRoute,
        array $modelsResolved,
        array $memPayload,
        array $routerCtx,
        array $plan,
        array $executorOutputs,
        array $lastAudit,
        ?array $lastSecurity,
        ?array $lastFinal,
        ?callable $emit,
        int $tokenAcc,
        float $tRun
    ): array {
        $tokenAcc += $this->estimateTokens($finalOutput);
        $totalMs = (int) round((microtime(true) - $tRun) * 1000);

        $run->update([
            'final_output' => $finalOutput,
            'status' => 'completed',
            'total_latency_ms' => $totalMs,
            'total_token_estimate' => $tokenAcc,
            'metadata' => [
                'plan' => $plan,
                'router' => $routerCtx,
                'routing_decision' => $modelRoute,
                'models_resolved' => $modelsResolved,
                'security_audit' => $lastSecurity,
                'final_reviewer' => $lastFinal,
            ],
        ]);

        $this->logStep($run, 9999, 'final', null, 'ollama', null, 'success', $prompt, $finalOutput, $finalOutput, null, null, null, null, null, null, $this->events->metadata(
            'final-reviewer',
            'reasoning',
            'Run completed.',
            'Final result is ready.',
            ['final_output' => $finalOutput, 'memory_used' => $memPayload],
            'final-reviewer',
            'system'
        ));

        $this->emit($emit, $this->events->runCompleted($run, $finalOutput, $totalMs, $tokenAcc, $modelRoute, $modelsResolved));

        $this->writeMemoryIfNeeded(
            (string) ($modelRoute['memory_mode'] ?? 'read_and_write'),
            $prompt,
            $modelRoute,
            $modelsResolved,
            $executorOutputs[0] ?? ['patch_summary' => ''],
            $lastAudit,
            $lastSecurity,
            $lastFinal
        );

        try {
            $run->refresh();
            $this->knowledgeGraph->buildForRun($run);
        } catch (\Throwable $e) {
            Log::warning('bosskuai.knowledge_graph.build_failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('bosskuai.run.complete', [
            'run_id' => $run->id,
            'workflow' => $modelRoute['workflow'] ?? null,
            'risk' => $modelRoute['risk_level'] ?? null,
            'skill' => $modelRoute['skill'] ?? null,
        ]);

        $skillsUsed = collect($plan['selected_skills'] ?? [])
            ->filter(fn ($s) => is_array($s))
            ->map(fn ($s) => $s['name'] ?? null)
            ->filter()
            ->values()
            ->all();
        if ($skillsUsed === []) {
            $skillsUsed = [(string) ($routerCtx['primary_skill']['name'] ?? $modelRoute['skill'] ?? '')];
            $skillsUsed = array_values(array_filter($skillsUsed));
        }
        $rulesUsed = collect($routerCtx['rules'] ?? [])->map(fn ($r) => $r['name'] ?? '')->filter()->values()->all();

        return [
            'run_id' => $run->id,
            'final_output' => $finalOutput,
            'steps' => $executorOutputs,
            'memory_used' => $memPayload,
            'skills_used' => $skillsUsed,
            'rules_used' => $rulesUsed,
            'playbooks_used' => collect($routerCtx['playbooks'] ?? [])->pluck('name')->all(),
            'audit' => $lastAudit,
            'routing' => $modelRoute,
        ];
    }

    /**
     * @param array<string,mixed> $extras
     * @return array<string,mixed>
     */
    protected function basePayload(Run $run, string $type, array $extras = []): array
    {
        return $this->events->event($run, $type, $extras);
    }

    /**
     * @param  array<string,mixed>  $routerCtx
     * @param  array<string,mixed>  $step
     * @return list<string>
     */
    protected function pickRules(array $routerCtx, array $step): array
    {
        $want = isset($step['rules']) && is_array($step['rules']) ? $step['rules'] : [];
        /** @var list<array{name?:string,text?:string,priority?:int}> $rules */
        $rules = collect($routerCtx['rules'] ?? [])->sortByDesc(fn ($r) => $r['priority'] ?? 0)->values()->all();

        $lines = [];
        foreach ($rules as $r) {
            $name = (string) ($r['name'] ?? '');
            if ($want !== [] && ! in_array($name, $want, true)) {
                continue;
            }
            $lines[] = $name.': '.($r['text'] ?? '');
            if (count($lines) >= 8) {
                break;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string,mixed>  $routerCtx
     * @param  array<string,mixed>  $step
     */
    protected function pickPlaybookExcerpt(array $routerCtx, array $step): string
    {
        $want = isset($step['playbooks']) && is_array($step['playbooks']) ? $step['playbooks'] : [];
        foreach ($routerCtx['playbooks'] ?? [] as $pb) {
            if (! is_array($pb)) {
                continue;
            }
            if ($want === [] || in_array($pb['name'] ?? '', $want, true)) {
                return (string) ($pb['excerpt'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param  array<string,mixed>  $routerCtx
     * @param  array<string,mixed>  $step
     */
    protected function pickChecklistExcerpt(array $routerCtx, array $step): string
    {
        $want = isset($step['checklists']) && is_array($step['checklists']) ? $step['checklists'] : [];
        foreach ($routerCtx['checklists'] ?? [] as $c) {
            if (! is_array($c)) {
                continue;
            }
            if ($want === [] || in_array($c['name'] ?? '', $want, true)) {
                return (string) ($c['excerpt'] ?? '');
            }
        }

        return '';
    }

    protected function logStep(
        Run $run,
        int $stepNumber,
        string $type,
        ?string $model,
        ?string $provider,
        ?string $skillName,
        string $status,
        ?string $input,
        ?string $inputDetail,
        ?string $output,
        ?array $rulesUsed,
        ?array $playbooksUsed,
        ?array $checklistsUsed,
        ?int $latencyMs,
        ?int $tokenEstimate,
        ?string $error,
        ?array $extra
    ): RunStep {
        $data = [
            'run_id' => $run->id,
            'step_number' => $stepNumber,
            'type' => $type,
            'model' => $model,
            'provider' => $provider,
            'skill_name' => $skillName,
            'status' => $status,
            'input' => $inputDetail ?? $input,
            'output' => $output,
            'rules_used' => $rulesUsed,
            'playbooks_used' => $playbooksUsed,
            'checklists_used' => $checklistsUsed,
            'memory_used' => $extra['memory_used'] ?? null,
            'latency_ms' => $latencyMs,
            'token_estimate' => $tokenEstimate,
            'error' => $error,
            'metadata' => $extra,
        ];

        return RunStep::query()->create($data);
    }

    protected function estimateTokens(string $text): int
    {
        return (int) max(1, round(strlen($text) / 4));
    }

    /** @param callable(array<string,mixed>): void|null $emit */
    protected function emit(?callable $emit, array $payload): void
    {
        if ($emit === null) {
            return;
        }
        $emit($payload);
    }

    /**
     * @param  list<array{role: string, content: string}>  $conversation
     */
    protected function effectivePrompt(string $userPrompt, array $conversation): string
    {
        $userPrompt = trim($userPrompt);
        if ($conversation === []) {
            return $userPrompt;
        }

        $lines = [];
        $used = 0;
        $maxChars = 12_000;

        foreach (array_slice($conversation, -40) as $turn) {
            $role = strtolower((string) ($turn['role'] ?? 'user'));
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $line = ($role === 'assistant' ? 'Assistant' : 'User').': '.$content;
            if ($used + strlen($line) > $maxChars) {
                break;
            }
            $lines[] = $line;
            $used += strlen($line);
        }

        if ($lines === []) {
            return $userPrompt;
        }

        return "Previous conversation:\n".implode("\n\n", $lines)."\n\nCurrent request:\n".$userPrompt;
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function ensureExecutorEvidence(Run $run, array $plan, array $execResult, string $userPrompt, ?callable $emit): array
    {
        if (ExecutorEvidenceSupport::countFilesRead($execResult) > 0) {
            return $execResult;
        }

        $preflight = $this->preflightReadTargetFiles($run, $plan, $emit);
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $preflight);

        if (ExecutorEvidenceSupport::countFilesRead($execResult) > 0) {
            return $execResult;
        }

        $bootstrap = $this->bootstrapReadTargetFiles($run, $emit);
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $bootstrap);

        if (ExecutorEvidenceSupport::countFilesRead($execResult) > 0) {
            return $execResult;
        }

        if (RepoTaskDetector::requiresRepositoryAccess($userPrompt)) {
            $searchReads = $this->auditFileSearchReads($run, $emit);
            $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $searchReads);
        }

        return $execResult;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function bootstrapReadTargetFiles(Run $run, ?callable $emit): array
    {
        $candidates = [
            'composer.json',
            'package.json',
            'README.md',
            'readme.md',
            'go.mod',
            'Cargo.toml',
            'pom.xml',
            'routes/web.php',
            'routes/api.php',
            '.env.example',
            'src',
            'app',
        ];

        $reads = [];
        foreach ($candidates as $path) {
            if (count($reads) >= 10) {
                break;
            }
            $reads = array_merge($reads, $this->readTargetPath($run, $path, 'bootstrap probe', $emit));
        }

        if ($reads !== []) {
            $this->emit($emit, $this->basePayload($run, 'bootstrap_reads_done', [
                'status' => 'success',
                'summary' => count($reads).' bootstrap path(s) probed.',
                'artifacts' => ['bootstrap_reads' => $reads],
            ]));
        }

        return $reads;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function auditFileSearchReads(Run $run, ?callable $emit): array
    {
        $inv = $this->tools->invoke($run->id, null, [
            'tool' => 'file_search',
            'payload' => ['q' => 'function', 'glob' => '*'],
        ], $emit);

        $reads = [];
        if (($inv['status'] ?? '') !== 'ok') {
            return $reads;
        }

        $result = is_array($inv['result'] ?? null) ? $inv['result'] : [];
        $matches = is_array($result['matches'] ?? null) ? $result['matches'] : [];
        foreach (array_slice($matches, 0, 15) as $match) {
            if (! is_array($match)) {
                continue;
            }
            $path = (string) ($match['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $reads[] = [
                'path' => $path,
                'found' => true,
                'reason' => 'repo audit file_search',
                'tool_status' => 'ok',
            ];
        }

        if ($reads !== []) {
            $this->emit($emit, $this->basePayload($run, 'audit_file_search_done', [
                'status' => 'success',
                'summary' => count($reads).' file(s) matched audit file_search.',
                'artifacts' => ['file_search_reads' => $reads],
            ]));
        }

        return $reads;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function readTargetPath(Run $run, string $path, string $reason, ?callable $emit): array
    {
        $normalized = $this->paths->normalizeRelativePath($path);
        if ($normalized === '') {
            return [];
        }

        $inv = $this->tools->invoke($run->id, null, [
            'tool' => 'file_read_safe',
            'payload' => ['path' => $normalized],
        ], $emit);

        $result = is_array($inv['result'] ?? null) ? $inv['result'] : [];

        return [[
            'path' => $normalized,
            'found' => (bool) ($result['found'] ?? false),
            'preview' => isset($result['preview']) ? Str::limit((string) $result['preview'], 500) : null,
            'reason' => $reason,
            'tool_status' => $inv['status'] ?? 'error',
        ]];
    }

    protected function preflightReadTargetFiles(Run $run, array $plan, ?callable $emit): array
    {
        $reads = [];
        $targets = is_array($plan['target_file_list'] ?? null) ? $plan['target_file_list'] : [];

        if ($targets === []) {
            foreach (['composer.json', 'package.json', 'README.md', 'routes/web.php', 'routes/api.php', '.env.example'] as $bootstrap) {
                $targets[] = ['path' => $bootstrap, 'reason' => 'bootstrap scan'];
            }
        }

        foreach (array_slice($targets, 0, 10) as $target) {
            if (! is_array($target)) {
                continue;
            }
            $path = (string) ($target['path'] ?? '');
            $reads = array_merge(
                $reads,
                $this->readTargetPath($run, $path, (string) ($target['reason'] ?? 'planner target'), $emit),
            );
        }

        if ($reads !== []) {
            $this->emit($emit, $this->basePayload($run, 'preflight_reads_done', [
                'status' => 'success',
                'summary' => count($reads).' file(s) read from the active project.',
                'artifacts' => ['preflight_reads' => $reads],
            ]));
        }

        return $reads;
    }

    protected function promptMentionsRepo(string $prompt): bool
    {
        return RepoTaskDetector::requiresRepositoryAccess($prompt);
    }
}
