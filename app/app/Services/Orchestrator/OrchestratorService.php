<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Skill;
use App\Services\BosskuAi\AgentPersonaService;
use App\Support\StringCoercion;
use App\Services\BosskuAi\BosskuResponseIndicator;
use App\Services\BosskuAi\ContextBudgetGuard;
use App\Services\BosskuAi\RepoTaskDetector;
use App\Services\BosskuAi\WorkflowRouteHelper;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\PromptRouteClassifier;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\BosskuAi\SkillRouterService;
use App\Services\Graph\KnowledgeGraphBuilder;
use App\Services\Governance\ExecutorApprovalService;
use App\Services\Learning\UserSelfLearningService;
use App\Services\Project\ProjectCommandRunner;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\ProjectService;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrchestratorService
{
    use OrchestratorApprovalTrait;
    use OrchestratorClarificationTrait;
    use OrchestratorUserLocalCommandsTrait;

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
        protected PostMemoryEvaluationService $postMemoryEvaluation,
        protected ToolRegistry $tools,
        protected RuntimeSettings $settings,
        protected PromptRouteClassifier $promptRouteClassifier,
        protected ContextBudgetGuard $budgetGuard,
        protected ModelRoutingConfig $modelConfig,
        protected RunEventFactory $events,
        protected ProjectPathResolver $paths,
        protected ProjectFileDiscovery $discovery,
        protected ProjectService $projects,
        protected KnowledgeGraphBuilder $knowledgeGraph,
        protected ExecutorFileChangeApplier $executorFileApplier,
        protected ProjectCommandRunner $projectCommands,
        protected ExecutorApprovalService $executorApprovals,
        protected AgentPersonaService $agentPersonas,
        protected UserSelfLearningService $userSelfLearning,
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

        $runMeta = [
            'conversation_turns' => count($conversation),
            'conversation' => $conversation,
        ];
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

        $personasApplied = $this->agentPersonas->snapshotForRun();
        $runMeta['personas_applied'] = $personasApplied;
        $run->update(['metadata' => $runMeta]);

        $this->logStep($run, -2, 'model_router', $modelsResolved['router'] ?? null, $routerMeta['provider'] ?? null, null, 'success', $agentPrompt, $routerJson, $routerJson, null, null, null, $routerMs, $routerTok, null, [
            'routing_decision' => $modelRoute,
            'models_resolved' => $modelsResolved,
            'router_meta' => $routerMeta,
            'personas_applied' => $personasApplied,
        ]);
        $tokenAcc += $routerTok;

        $this->emit($emit, $this->basePayload($run, 'personas_active', [
            'status' => 'success',
            'summary' => 'Agent personas loaded for this run.',
            'message' => 'Custom personas apply to each pipeline step.',
            'personas_applied' => $personasApplied,
        ]));

        $skippedAgents = WorkflowRouteHelper::skippedAgentsForRoute($modelRoute);
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
                'pipeline_agents' => WorkflowRouteHelper::pipelineAgentsForWorkflow((string) ($modelRoute['workflow'] ?? '')),
                'skipped_agents' => $skippedAgents,
            ],
        ]));
        if ($skippedAgents !== []) {
            $this->emit($emit, $this->basePayload($run, 'agents_skipped', [
                'status' => 'success',
                'agent' => 'orchestrator',
                'summary' => 'Skipped for this route: '.implode(', ', $skippedAgents),
                'artifacts' => [
                    'skipped_agents' => $skippedAgents,
                    'workflow' => $modelRoute['workflow'] ?? null,
                ],
            ]));
        }

        $memoryMode = (string) ($modelRoute['memory_mode'] ?? 'read_only');
        $memPayload = [];
        $memMs = 0;
        $memTokens = 0;

        if ($this->settings->memoryStorageEnabled()) {
            $t0 = microtime(true);
            $skillTag = is_string($modelRoute['skill'] ?? null) && $modelRoute['skill'] !== '' ? [$modelRoute['skill']] : [];
            $memories = $this->memory->search($agentPrompt, $this->settings->maxMemoryResults(), $skillTag);
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

        if ($this->shouldRequireClarification($run, $userPrompt, $modelRoute)) {
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

        $plan = $this->planner->plan($agentPrompt, $memPayload, $routerCtx, $modelRoute, $conversation);
        $planMs = (int) round((microtime(true) - $t0) * 1000);
        $planTokens = $this->estimateTokens(json_encode($plan) ?: '');

        if (! empty($plan['error'])) {
            $orchModel = (string) ($plan['_planner_model'] ?? $modelsResolved['orchestrator'] ?? '');
            $plannerErr = StringCoercion::toString($plan['message'] ?? null, 'Planner failed');
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
            StringCoercion::toString($plan['handoff_message'] ?? null, 'Sending execution task to Executor.'),
            ['plan' => $plan, 'checklist' => $plan['checklist'] ?? []],
            'orchestrator',
            'executor'
        ));
        $tokenAcc += $planTokens;

        $modelsResolved['orchestrator'] = $orchModel;

        $this->emit($emit, $this->events->plannerDone($run, $plan, $orchModel, $planMs, $planTokens));

        // Surface planner's clarification questions as events and persist to run
        $plannerQuestions = is_array($plan['planner_questions'] ?? null) ? $plan['planner_questions'] : [];
        $planConfidence = is_numeric($plan['confidence'] ?? null) ? (float) $plan['confidence'] : 1.0;
        if ($plannerQuestions !== []) {
            $isLowConfidence = $planConfidence < 0.50;
            $run->update(['metadata' => array_merge($run->metadata ?? [], [
                'open_questions' => $plannerQuestions,
                'low_confidence_plan' => $isLowConfidence,
            ])]);
            if ($emit !== null) {
                $this->emit($emit, $this->basePayload($run, 'clarification_suggested', [
                    'status' => $isLowConfidence ? 'warning' : 'info',
                    'agent' => 'planner',
                    'summary' => $isLowConfidence
                        ? 'Low-confidence plan ('.round($planConfidence * 100).'%) — '.count($plannerQuestions).' unresolved question(s). Results may be unreliable without clarification.'
                        : 'Planner has '.count($plannerQuestions).' question(s) for you.',
                    'questions' => $plannerQuestions,
                ]));
            }
        }

        $execProfileKey = (string) ($plan['executor_profile'] ?? $modelRoute['executor_profile'] ?? 'default');
        $plan = $this->budgetGuard->narrowPlan($plan, $execProfileKey);

        if (RepoTaskDetector::requiresRepositoryAccess($userPrompt)) {
            $auditMode = RepoTaskDetector::isFullRepositoryAudit($userPrompt) ? 'full' : 'repo';
            $workflow = $auditMode === 'full'
                ? 'orchestrator_executor_auditor_security'
                : 'orchestrator_executor_auditor';
            $modelRoute['workflow'] = $workflow;
            $modelRoute['needs_executor'] = true;
            $modelRoute['needs_auditor'] = true;
            $modelRoute['audit_mode'] = $auditMode;
            if ($auditMode === 'full') {
                $modelRoute['needs_security_auditor'] = true;
            }
            $workflow = (string) $modelRoute['workflow'];
        }

        $plan = $this->enrichPlanWithExecutionHints($plan, $modelRoute);

        $mustRunExecutor = ($modelRoute['needs_executor'] ?? true)
            || ($modelRoute['needs_security_auditor'] ?? false)
            || RepoTaskDetector::requiresRepositoryAccess($userPrompt);

        if (($workflow === 'orchestrator_only' || ! ($modelRoute['needs_executor'] ?? true)) && ! $mustRunExecutor) {
            $preflightReads = [];
            if ($workflow === 'orchestrator_only' && ($modelRoute['needs_repo_context'] ?? false)) {
                $preflightReads = $this->preflightReadTargetFiles($run, $plan, $emit, $modelRoute);
            }
            $body = StringCoercion::toString($plan['summary'] ?? null, json_encode($plan) ?: '');
            $survey = $this->formatPreflightSurveySummary($preflightReads);
            if ($survey !== '') {
                $body = trim($body."\n\n".$survey);
            }
            $userCmdBlock = $this->formatUserCommandsFromPlan($plan);
            if ($userCmdBlock !== '') {
                $body = trim($body."\n\n".$userCmdBlock);
            }
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

        $preflightReads = $this->preflightReadTargetFiles($run, $plan, $emit, $modelRoute);

        $skillName = (string) ($routerCtx['primary_skill']['name'] ?? 'cofounder');
        $step = [
            'id' => 1,
            'title' => StringCoercion::toString($plan['summary'] ?? null, 'Execute'),
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
            'message' => StringCoercion::toString($plan['handoff_message'] ?? null, 'Executor received the plan.'),
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
            null,
            $memPayload,
            $conversation,
        );
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $preflightReads);
        $execResult = $this->ensureExecutorEvidence($run, $plan, $execResult, $userPrompt, $emit);
        $modelsResolved['executor'] = (string) ($execResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');
        $approvalPipeline = $this->buildExecutorPipelineSnapshot(
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
        );
        $afterApprovals = $this->applyOrPauseForExecutorApprovals($run, $execResult, $approvalPipeline, $emit);
        if (($afterApprovals['awaiting_approvals'] ?? false) === true) {
            return $afterApprovals;
        }
        $execResult = $afterApprovals;

        $localCmdPause = $this->maybePauseForUserLocalCommands($run, $execResult, $approvalPipeline, $emit);
        if (is_array($localCmdPause) && ($localCmdPause['awaiting_clarification'] ?? false) === true) {
            return $localCmdPause;
        }
        if (is_array($localCmdPause)) {
            $execResult = $localCmdPause;
        }

        $exTok = $this->estimateTokens(json_encode($execResult) ?: '');
        $this->logStep($run, 3, 'executor', $modelsResolved['executor'], 'ollama', $skillName, ($execResult['status'] ?? '') === 'failed' ? 'failed' : 'success', json_encode($step), json_encode($execResult), json_encode($execResult), null, null, null, (int) ($execResult['latency_ms'] ?? 0), $exTok, null, $this->events->metadata(
            'executor',
            'coding',
            'Executor completed the requested changes.',
            StringCoercion::toString($execResult['handoff_message'] ?? null, 'Sending changes to Auditor.'),
            $this->events->executorArtifacts($execResult),
            'executor',
            'auditor'
        ));
        $tokenAcc += $exTok;
        $this->emit($emit, $this->events->executorDone($run, $execResult, $modelsResolved['executor'], (int) ($execResult['latency_ms'] ?? 0), $exTok));

        // Surface executor questions — persist to run and pause if needs_user_input not already set
        $executorQuestions = is_array($execResult['executor_questions'] ?? null) ? $execResult['executor_questions'] : [];
        if ($executorQuestions !== []) {
            $existingOpenQuestions = is_array($run->metadata['open_questions'] ?? null) ? $run->metadata['open_questions'] : [];
            $run->update(['metadata' => array_merge($run->metadata ?? [], [
                'open_questions' => array_values(array_merge($existingOpenQuestions, $executorQuestions)),
            ])]);
            if ($emit !== null) {
                $this->emit($emit, $this->basePayload($run, 'executor_questions_surfaced', [
                    'status' => 'warning',
                    'agent' => 'executor',
                    'summary' => 'Executor has '.count($executorQuestions).' question(s) for you before proceeding.',
                    'questions' => $executorQuestions,
                ]));
            }
            // Force needs_user_input so the existing stuck-check pauses the pipeline
            if (($execResult['needs_user_input'] ?? false) !== true) {
                $execResult['needs_user_input'] = true;
                $execResult['blockers'] = array_values(array_merge(
                    is_array($execResult['blockers'] ?? null) ? $execResult['blockers'] : [],
                    array_map(fn ($q) => StringCoercion::toString(is_array($q['question'] ?? null) ? implode(' ', $q['question']) : ($q['question'] ?? null), ''), $executorQuestions)
                ));
            }
        }

        if (! empty($execResult['tool_request'] ?? null)) {
            $this->tools->invoke($run->id, null, $execResult['tool_request'], $emit);
        }

        if ($this->shouldPauseForAgentEscalation($run, $execResult, 'executor_escalation')) {
            return $this->pauseForAgentEscalation(
                $run,
                'executor_escalation',
                'executor',
                $execResult,
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
                $emit,
            );
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
        bool $skipAuditor = false,
        array $cachedAudit = [],
    ): array {
        $lastAudit = $cachedAudit;
        $lastSecurity = null;
        $lastFinal = null;
        $revisionRoundsUsed = 0;

        $needsAuditor = ! $skipAuditor
            && ($modelRoute['needs_auditor'] ?? false)
            && $this->settings->auditEnabled()
            && WorkflowRouteHelper::workflowIncludesAuditor($workflow);

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
            if ($this->executorFailedFromLlmJson($execResult)) {
                $lastAudit = ExecutorEvidenceSupport::deterministicExecutorFailed(
                    $this->executorFailureSummary($execResult),
                );
                $auditMs = (int) round((microtime(true) - $tA) * 1000);
            }
            else {
                $lastAudit = $this->auditor->auditStep(
                    $agentPrompt,
                    $routerCtx,
                    $modelRoute,
                    $plan,
                    $step,
                    $execResult,
                    $ruleLines,
                    $chkExcerpt,
                    ($modelRoute['risk_level'] ?? '') === 'high',
                    $preflightReads,
                    $run->id,
                    $memPayload,
                    $conversation,
                );
                $auditMs = (int) round((microtime(true) - $tA) * 1000);
            }
            $auditTok = $this->estimateTokens(json_encode($lastAudit) ?: '');
            $modelsResolved['auditor'] = (string) ($lastAudit['_auditor_model'] ?? $modelsResolved['auditor'] ?? '');
            $pass = ($lastAudit['_legacy_pass'] ?? false) === true;
            $this->logStep($run, $stepNum + 100, 'auditor', $modelsResolved['auditor'], 'ollama', $skillName, $pass ? 'success' : (($lastAudit['status'] ?? '') === 'needs_revision' ? 'needs_revision' : 'failed'), json_encode($step), json_encode($execResult), json_encode($lastAudit), null, null, null, $auditMs, $auditTok, null, $this->events->metadata(
                'auditor',
                'review',
                StringCoercion::toString($lastAudit['summary'] ?? null, 'Audit completed.'),
                (($lastAudit['status'] ?? '') === 'needs_revision') ? 'Returning feedback to Executor.' : 'Sending audit result to Final Reviewer.',
                ['audit' => $lastAudit, 'audit_findings' => $lastAudit['findings'] ?? []],
                'auditor',
                (($lastAudit['status'] ?? '') === 'needs_revision') ? 'executor' : 'final-reviewer'
            ));
            $tokenAcc += $auditTok;
            $this->emit($emit, $this->events->auditorDone($run, $lastAudit, $modelsResolved['auditor'], $auditMs, $auditTok));

            // Surface memory conflicts found by the auditor
            $memConflicts = is_array($lastAudit['memory_conflicts'] ?? null) ? $lastAudit['memory_conflicts'] : [];
            if ($memConflicts !== [] && $emit !== null) {
                $this->emit($emit, $this->basePayload($run, 'memory_conflict_detected', [
                    'status' => 'warning',
                    'agent' => 'auditor',
                    'summary' => 'Auditor detected '.count($memConflicts).' memory conflict(s) — executor repeated known past mistakes.',
                    'conflicts' => $memConflicts,
                ]));
            }

            // Surface auditor's high-stakes user questions and persist them
            $auditorUserQuestions = is_array($lastAudit['user_questions'] ?? null) ? $lastAudit['user_questions'] : [];
            if ($auditorUserQuestions !== []) {
                $existingOpenQuestions = is_array($run->metadata['open_questions'] ?? null) ? $run->metadata['open_questions'] : [];
                $run->update(['metadata' => array_merge($run->metadata ?? [], [
                    'open_questions' => array_values(array_merge($existingOpenQuestions, $auditorUserQuestions)),
                ])]);
                if ($emit !== null) {
                    $this->emit($emit, $this->basePayload($run, 'auditor_questions_surfaced', [
                        'status' => 'warning',
                        'agent' => 'auditor',
                        'summary' => 'Auditor has '.count($auditorUserQuestions).' high-stakes question(s) requiring your decision.',
                        'questions' => $auditorUserQuestions,
                    ]));
                }
            }

            // Surface checklist verdict trail
            $verdictTrail = is_array($lastAudit['verdict_trail'] ?? null) ? $lastAudit['verdict_trail'] : [];
            if ($verdictTrail !== [] && $emit !== null) {
                $disputed = array_filter($verdictTrail, fn ($v) => ($v['auditor_verdict'] ?? '') !== 'verified');
                $this->emit($emit, $this->basePayload($run, 'checklist_verdict', [
                    'status' => count($disputed) > 0 ? 'warning' : 'success',
                    'agent' => 'auditor',
                    'summary' => count($verdictTrail).' checklist item(s) reviewed; '.count($disputed).' disputed or unverifiable.',
                    'verdict_trail' => $verdictTrail,
                ]));
            }

            if ($this->shouldPauseForAgentEscalation($run, $lastAudit, 'auditor_escalation')) {
                $pipeline = $this->buildExecutorPipelineSnapshot(
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
                );
                $pipeline['exec_result'] = $execResult;
                $pipeline['last_audit'] = $lastAudit;
                $pipeline['executor_outputs'] = $executorOutputs;
                $pipeline['step_num'] = $stepNum;

                return $this->pauseForAgentEscalation(
                    $run,
                    'auditor_escalation',
                    'auditor',
                    $lastAudit,
                    $pipeline,
                    $emit,
                );
            }

            if (
                ($lastAudit['status'] ?? '') === 'needs_revision'
                && $this->executorFailedFromLlmJson($execResult)
            ) {
                $this->emit($emit, $this->basePayload($run, 'executor_revision_skipped', [
                    'status' => 'warning',
                    'agent' => 'executor',
                    'summary' => 'Skipped executor revision: executor failed with JSON/model errors; re-running would not help.',
                    'message' => StringCoercion::toString($execResult['known_issues'][0] ?? null, 'Executor LLM output was invalid.'),
                ]));
            }

            if (
                ($lastAudit['status'] ?? '') === 'needs_revision'
                && ($execResult['needs_user_input'] ?? false) === true
            ) {
                $this->emit($emit, $this->basePayload($run, 'executor_revision_skipped', [
                    'status' => 'warning',
                    'agent' => 'executor',
                    'summary' => 'Skipped executor revision: executor is waiting for your input first.',
                    'message' => StringCoercion::toString($execResult['blockers'][0] ?? $execResult['known_issues'][0] ?? null, 'User input required.'),
                ]));
            }

            if (
                ($lastAudit['status'] ?? '') === 'needs_revision'
                && $this->settings->maxRevisionRounds() > 0
                && ! $this->executorFailedFromLlmJson($execResult)
                && ($execResult['needs_user_input'] ?? false) !== true
            ) {
                $this->emit($emit, $this->basePayload($run, 'executor_revision_started', [
                    'status' => 'running',
                    'agent' => 'executor',
                    'model_role' => 'coding',
                    'from_agent' => 'auditor',
                    'to_agent' => 'executor',
                    'summary' => 'Executor is applying audit feedback.',
                    'message' => StringCoercion::toString($lastAudit['summary'] ?? null, 'Audit requested a revision.'),
                ]));

                $revisionStep = array_merge($step, [
                    'id' => 2,
                    'title' => 'Fix audit feedback',
                ]);
                $auditFeedback = ExecutorEvidenceSupport::auditorPayloadForRevision(
                    $lastAudit,
                    $execResult,
                    $preflightReads,
                    $run->id,
                );
                $auditFeedback['original_prompt'] = $agentPrompt;

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
                    $auditFeedback,
                    $memPayload,
                    $conversation,
                );
                $revisionResult = ExecutorEvidenceSupport::mergePreflightReads($revisionResult, $preflightReads);
                $revisionResult = $this->ensureExecutorEvidence($run, $plan, $revisionResult, $userPrompt, $emit);
                $modelsResolved['executor'] = (string) ($revisionResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');
                $revPipeline = $this->buildExecutorPipelineSnapshot(
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
                    $revisionStep,
                    $skillRow,
                    $ruleLines,
                    $pbExcerpt,
                    $chkExcerpt,
                    $tokenAcc,
                    $tRun,
                    $auditFeedback,
                );
                $revPipeline['last_audit'] = $lastAudit;
                $revPipeline['exec_result'] = $revisionResult;
                $revAfter = $this->applyOrPauseForExecutorApprovals($run, $revisionResult, $revPipeline, $emit);
                if (($revAfter['awaiting_approvals'] ?? false) === true) {
                    return $revAfter;
                }
                $revisionResult = $revAfter;
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

        $needsSecurityPass = (
            (($modelRoute['needs_security_auditor'] ?? false) && WorkflowRouteHelper::workflowIncludesSecurityAuditor($workflow))
            || ($lastAudit['requires_security_audit'] ?? false)
        );

        if ($needsSecurityPass) {
            $execResult = $this->ensureExecutorEvidence($run, $plan, $execResult, $userPrompt, $emit);

            $this->emit($emit, $this->basePayload($run, 'security_auditor_started', ['status' => 'running']));
            $tS = microtime(true);
            if (! ExecutorEvidenceSupport::hasReadEvidence($execResult, ExecutorEvidenceSupport::toolEvidenceForRun($run->id))) {
                $lastSecurity = ExecutorEvidenceSupport::deterministicNoFilesRead();
            } else {
                $lastSecurity = $this->securityAuditor->audit($agentPrompt, $modelRoute, $plan, $execResult, $run->id, $preflightReads);
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

        if (
            ($modelRoute['needs_final_reviewer'] ?? false)
            && WorkflowRouteHelper::workflowIncludesFinalReviewer($workflow)
        ) {
            $this->emit($emit, $this->basePayload($run, 'final_reviewer_started', [
                'status' => 'running',
                'agent' => 'final-reviewer',
                'model_role' => 'reasoning',
                'from_agent' => 'auditor',
                'to_agent' => 'final-reviewer',
                'summary' => 'Final Reviewer is closing the run.',
            ]));
            $tF = microtime(true);
            $lastFinal = $this->finalReviewer->review($agentPrompt, $modelRoute, $lastAudit, $lastSecurity, $execResult, $plan, $memPayload, $conversation);
            $fMs = (int) round((microtime(true) - $tF) * 1000);
            $fTok = $this->estimateTokens(json_encode($lastFinal) ?: '');
            $this->logStep($run, $stepNum + 200, 'final_reviewer', $modelsResolved['final_reviewer'] ?? null, 'ollama', $skillName, 'success', null, null, json_encode($lastFinal), null, null, null, $fMs, $fTok, null, $this->events->metadata(
                'final-reviewer',
                'reasoning',
                StringCoercion::toString($lastFinal['reason'] ?? null, 'Final review completed.'),
                'Final reviewer closed the run.',
                ['final_review' => $lastFinal],
                'final-reviewer',
                'system'
            ));
            $tokenAcc += $fTok;
            $this->emit($emit, $this->events->finalReviewerDone($run, $lastFinal, $modelsResolved['final_reviewer'] ?? null, $fMs, $fTok));
        }
        $finalOutput = $this->composeUserOutput($lastAudit, $execResult, $lastFinal, $lastSecurity, $modelRoute, $modelsResolved, $memPayload, $userPrompt, $plan);

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
        $learningResult = $this->userSelfLearning->processAfterRun(
            $run,
            $prompt,
            [],
            $modelRoute,
            [],
            ['patch_summary' => Str::limit($body, 2000)],
            [],
        );
        if ($emit !== null) {
            $this->emit($emit, $this->basePayload($run, 'user_learning_stored', [
                'status' => 'success',
                'summary' => 'User self-learning recorded.',
                'artifacts' => $learningResult,
            ]));
        }

        $totalMs = (int) round((microtime(true) - $tRun) * 1000);
        $run->update([
            'final_output' => $final,
            'status' => 'completed',
            'total_latency_ms' => $totalMs,
            'total_token_estimate' => $tokenAcc + $tok,
            'metadata' => array_merge($run->metadata ?? [], [
                'routing_decision' => $modelRoute,
                'models_resolved' => $modelsResolved,
                'user_learning' => $learningResult,
                'short_path' => $kind,
            ]),
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
     * @param array<string, mixed> $plan
     */
    protected function composeUserOutput(
        array $lastAudit,
        array $execResult,
        ?array $lastFinal,
        ?array $lastSecurity,
        array $modelRoute,
        array $modelsResolved,
        array $memPayload,
        string $userPrompt = '',
        array $plan = [],
    ): string {
        $commandOutcome = $this->summarizeCommandExecution($execResult);
        $status = (($execResult['status'] ?? '') === 'failed' || ($lastAudit['status'] ?? '') === 'failed')
            ? 'Partially Completed'
            : 'Completed';
        if ($commandOutcome['git_restore_failed']) {
            $status = 'Partially Completed';
        }
        $files = array_values(array_filter(array_map(
            fn ($file) => is_array($file)
                ? StringCoercion::toString($file['path'] ?? null)
                : StringCoercion::toString($file),
            $execResult['files_changed'] ?? [],
        )));
        $executedCommands = $commandOutcome['executed_lines'];
        $proposedCommands = $commandOutcome['proposed_lines'];
        $risks = $execResult['known_issues'] ?? [];
        if (($lastAudit['optional_improvements'] ?? []) !== []) {
            $risks = array_merge($risks, $lastAudit['optional_improvements']);
        }
        if (is_array($lastSecurity['security_issues'] ?? null) && $lastSecurity['security_issues'] !== []) {
            $risks = array_merge($risks, $lastSecurity['security_issues']);
        }

        $auditStatus = (string) ($lastAudit['status'] ?? 'not_run');
        $nextStep = 'Review the changed files and run any missing checks before merge.';
        if ($commandOutcome['git_restore_failed']) {
            $nextStep = 'Git restore did not complete. Run manually in the project: '
                .implode('; ', array_slice($commandOutcome['failed_commands'], 0, 3));
        } elseif ($executedCommands === [] && $proposedCommands !== []) {
            $nextStep = 'Commands were proposed but not executed — run them manually in the project root.';
        } elseif ($executedCommands === []) {
            $nextStep = 'Run the relevant test suite before merge.';
        } elseif ($lastFinal !== null && ($lastFinal['required_actions'] ?? []) !== []) {
            $nextStep = implode('; ', array_map(
                fn ($action) => StringCoercion::toString($action),
                $lastFinal['required_actions'],
            ));
        }

        $planGoal = StringCoercion::toString($plan['goal'] ?? $plan['task_summary'] ?? null, '');
        $nextPrompt = $this->buildNextPrompt(
            $files,
            $commandOutcome,
            $executedCommands,
            $proposedCommands,
            $nextStep,
            $lastFinal,
            $userPrompt,
            $planGoal,
        );

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
            $commandOutcome['summary_text'],
            '',
            '## Files changed',
            ...($files !== [] ? array_map(fn ($file) => '- '.$file, $files) : ['- No files recorded']),
            '',
            '## Commands executed',
            ...($executedCommands !== [] ? $executedCommands : ['- None (git/shell commands were not run or all failed)']),
            '',
            '## Commands proposed (not run)',
            ...($proposedCommands !== [] ? $proposedCommands : ['- None']),
            '',
            '## Git status after commands',
            ...($this->formatGitStatusLines($execResult['git_status_after'] ?? null)),
            '',
            '## Audit result',
            $auditStatus,
            ...$this->auditDimensionLines($modelRoute, $lastAudit, $lastSecurity),
            '',
            '## Remaining risks',
            ...($risks !== [] ? array_map(fn ($risk) => '- '.$this->formatRiskLine($risk), $risks) : ['- Full verification status depends on the checks recorded above.']),
            '',
            '## Next recommended step',
            $nextStep,
            '',
            '## Next prompt',
            $nextPrompt,
        ];

        return implode("\n", $lines);
    }

    /**
     * Paste-ready follow-up prompt for the user to send in the next Bossku run.
     *
     * @param  list<string>  $files
     * @param  array{executed_lines: list<string>, proposed_lines: list<string>, failed_commands: list<string>, git_restore_failed: bool, summary_text: string}  $commandOutcome
     * @param  list<string>  $executedCommandLines
     * @param  list<string>  $proposedCommandLines
     * @param  array<string, mixed>|null  $lastFinal
     */
    protected function buildNextPrompt(
        array $files,
        array $commandOutcome,
        array $executedCommandLines,
        array $proposedCommandLines,
        string $nextStep,
        ?array $lastFinal,
        string $userPrompt = '',
        string $planGoal = '',
    ): string {
        // Required actions from Final Reviewer take precedence (REVISE/REJECT fix instructions).
        if ($lastFinal !== null) {
            $actions = is_array($lastFinal['required_actions'] ?? null) ? $lastFinal['required_actions'] : [];
            foreach ($actions as $action) {
                $line = trim(StringCoercion::toString($action));
                if ($line !== '') {
                    return $line;
                }
            }
        }

        // Derive a short goal hint so fallback prompts reference what the user was doing.
        $decision = strtoupper(trim(StringCoercion::toString($lastFinal['decision'] ?? null, '')));
        $goalHint = $planGoal !== '' ? $planGoal : Str::limit(trim($userPrompt), 100);

        // Final Reviewer approved (MERGE) with no required actions — task is done.
        // Suggest a goal-aware verification step rather than a generic message.
        if ($decision === 'MERGE' && $goalHint !== '') {
            if ($executedCommandLines !== []) {
                return 'The "'.$goalHint.'" implementation is complete. Run any remaining tests and confirm the feature works end-to-end before closing.';
            }

            return 'The "'.$goalHint.'" changes look complete. Run the project test suite, review each changed file, and confirm the outcome matches the intent before merging.';
        }

        if (($commandOutcome['git_restore_failed'] ?? false) === true) {
            $failed = is_array($commandOutcome['failed_commands'] ?? null) ? $commandOutcome['failed_commands'] : [];
            $cmds = array_values(array_filter(array_map(
                static fn ($cmd) => trim(StringCoercion::toString($cmd)),
                $failed,
            )));
            if ($cmds !== []) {
                return 'In the active project, run:'."\n".implode("\n", $cmds);
            }
        }

        if ($executedCommandLines === [] && $proposedCommandLines !== []) {
            $cmds = $this->stripCommandBulletLines($proposedCommandLines);
            if ($cmds !== []) {
                return "Run these commands in the active project root:\n".implode("\n", $cmds);
            }
        }

        if ($files !== [] && $executedCommandLines === []) {
            $listed = implode(', ', array_slice($files, 0, 5));
            $suffix = count($files) > 5 ? ' (and others)' : '';

            return 'Read and verify the changes in '.$listed.$suffix
                .'. Confirm each file matches the intended outcome, then run the project test suite and report pass/fail with any errors.';
        }

        $goalClause = $goalHint !== '' ? ' for the "'.$goalHint.'" task' : '';
        $normalized = strtolower(trim($nextStep));
        if (str_contains($normalized, 'test suite') || str_contains($normalized, 'run the relevant test')) {
            return 'Run the project\'s test suite'.$goalClause.' and report pass/fail with any errors.';
        }
        if (str_contains($normalized, 'review the changed files')) {
            return 'Review each changed file'.$goalClause.', note anything unexpected, and run the appropriate checks before merge.';
        }
        if (str_contains($normalized, 'commands were proposed but not executed')) {
            return 'Run the proposed project commands from the run summary in the active project root and report stdout/stderr.';
        }
        if (str_contains($normalized, 'git restore did not complete')) {
            return $nextStep;
        }

        if ($nextStep !== '') {
            return $nextStep;
        }
        $continueSuffix = $goalHint !== '' ? ' to complete: '.$goalHint : '';

        return 'Continue the task in the active project'.$continueSuffix.' and report results.';
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    protected function stripCommandBulletLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $text = trim($line);
            if (str_starts_with($text, '- ')) {
                $text = substr($text, 2);
            }
            $text = trim($text);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function applyExecutorCommands(Run $run, array $execResult, ?callable $emit): array
    {
        $commandsRun = is_array($execResult['commands_run'] ?? null) ? $execResult['commands_run'] : [];
        if ($commandsRun === []) {
            return $execResult;
        }

        $outcome = $this->projectCommands->runAllowedProjectCommands($commandsRun);
        $execResult['_commands_executed'] = $outcome['executed'];
        $execResult['git_status_after'] = $outcome['post_git_status'];

        $failed = [];
        foreach ($outcome['executed'] as $row) {
            if (($row['ok'] ?? false) !== true) {
                $cmd = StringCoercion::toString($row['command'] ?? null, 'git command');
                $err = StringCoercion::toString($row['stderr'] ?? $row['reason'] ?? null, 'failed');
                $failed[] = $cmd.': '.$err;
            }
        }

        if ($failed !== []) {
            $issues = is_array($execResult['known_issues'] ?? null) ? $execResult['known_issues'] : [];
            $execResult['known_issues'] = array_values(array_merge($issues, $failed));
            if (($execResult['status'] ?? '') !== 'failed') {
                $execResult['status'] = 'partial';
            }
        }

        if ($emit !== null && $outcome['executed'] !== []) {
            $okCount = count(array_filter($outcome['executed'], static fn ($r) => ($r['ok'] ?? false) === true));
            $this->emit($emit, $this->basePayload($run, 'commands_executed', [
                'agent' => 'executor',
                'status' => $failed === [] ? 'success' : 'warning',
                'summary' => $okCount.'/'.count($outcome['executed']).' project command(s) executed.',
                'artifacts' => [
                    'commands_executed' => $outcome['executed'],
                    'git_status_after' => $outcome['post_git_status'],
                ],
            ]));
        }

        return $execResult;
    }

    protected function applyExecutorFileChanges(Run $run, array $execResult, ?callable $emit): array
    {
        $result = $this->executorFileApplier->applyFromExecutorResult($run->id, $execResult);
        $execResult = $result['execResult'];
        $report = $result;
        unset($report['execResult']);
        $execResult['_files_applied'] = $report;

        if ($report['applied'] !== [] && $emit !== null) {
            $emit($this->basePayload($run, 'files_applied', [
                'agent' => 'executor',
                'status' => 'success',
                'summary' => 'Auto-applied '.count($report['applied']).' file(s) to the active project.',
                'artifacts' => ['files_applied' => $report['applied'], 'files_apply_skipped' => $report['skipped']],
            ]));
        }

        if ($report['skipped'] !== [] && $emit !== null) {
            $emit($this->basePayload($run, 'files_apply_skipped', [
                'agent' => 'executor',
                'status' => 'warning',
                'summary' => count($report['skipped']).' file(s) were NOT written — missing `after` content in executor output.',
                'artifacts' => ['files_apply_skipped' => $report['skipped']],
            ]));
        }

        if ($report['errors'] !== []) {
            Log::warning('Some executor files were not auto-applied', [
                'run_id' => $run->id,
                'errors' => $report['errors'],
            ]);
            if ($emit !== null) {
                $emit($this->basePayload($run, 'files_apply_failed', [
                    'agent' => 'executor',
                    'status' => 'warning',
                    'summary' => count($report['errors']).' file(s) could not be written to the active project (permissions or path).',
                    'artifacts' => ['files_apply_errors' => $report['errors']],
                ]));
            }
        }

        return $execResult;
    }

    protected function formatRiskLine(mixed $risk): string
    {
        if (! is_array($risk)) {
            return StringCoercion::toString($risk);
        }

        $issue = StringCoercion::toString($risk['issue'] ?? $risk['title'] ?? null, 'Risk');
        $severity = strtoupper(StringCoercion::toString($risk['severity'] ?? null, 'medium'));
        $location = StringCoercion::toString($risk['location'] ?? null);
        $description = StringCoercion::toString($risk['description'] ?? $risk['recommendation'] ?? null);

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
     * @param  array<string, mixed>  $execResult
     * @return array{
     *   executed_lines: list<string>,
     *   proposed_lines: list<string>,
     *   failed_commands: list<string>,
     *   git_restore_failed: bool,
     *   summary_text: string,
     * }
     */
    protected function summarizeCommandExecution(array $execResult): array
    {
        $proposed = $this->projectCommands->normalizeCommandList(
            is_array($execResult['commands_run'] ?? null) ? $execResult['commands_run'] : [],
        );
        $executed = is_array($execResult['_commands_executed'] ?? null) ? $execResult['_commands_executed'] : [];

        $executedLines = [];
        $proposedLines = [];
        $failedCommands = [];
        $gitRestoreFailed = false;

        foreach ($executed as $row) {
            if (! is_array($row)) {
                continue;
            }
            $cmd = StringCoercion::toString($row['command'] ?? null, '');
            $ok = ($row['ok'] ?? false) === true;
            $suffix = $ok ? '(exit 0)' : '(failed)';
            $line = '- '.$cmd.' '.$suffix;
            $outSnippet = $this->commandOutputSnippet($row);
            if ($outSnippet !== '') {
                $line .= "\n  ".$outSnippet;
            }
            $executedLines[] = $line;
            if (! $ok) {
                $failedCommands[] = $cmd;
                if ($this->isRestoreLikeCommand($cmd)) {
                    $gitRestoreFailed = true;
                }
            }
        }

        $executedSet = array_map(
            static fn ($row) => is_array($row) ? StringCoercion::toString($row['command'] ?? null, '') : '',
            $executed,
        );

        foreach ($proposed as $cmd) {
            if (! in_array($cmd, $executedSet, true)) {
                $proposedLines[] = '- '.$cmd;
            }
        }

        foreach ($executed as $row) {
            if (is_array($row) && ($row['skipped'] ?? false) === true) {
                $cmd = StringCoercion::toString($row['command'] ?? null, '');
                $proposedLines[] = '- '.$cmd.' (skipped: '.StringCoercion::toString($row['reason'] ?? null, 'blocked').')';
            }
        }

        $patch = StringCoercion::toString($execResult['patch_summary'] ?? null, '');
        $summaryText = $patch !== '' ? $patch : 'No change summary recorded.';
        if ($gitRestoreFailed) {
            $summaryText = 'Git restore did not complete successfully. '.$summaryText;
        } elseif ($proposed !== [] && $executedLines === []) {
            $summaryText = 'Commands were listed but not executed. '.$summaryText;
        } elseif (
            $patch !== ''
            && preg_match('/\brestored?\b/i', $patch)
            && $executedLines === []
            && $proposed !== []
        ) {
            $summaryText = 'Note: summary mentions restore but git commands were not executed. '.$summaryText;
        }

        return [
            'executed_lines' => $executedLines,
            'proposed_lines' => $proposedLines,
            'failed_commands' => $failedCommands,
            'git_restore_failed' => $gitRestoreFailed,
            'summary_text' => $summaryText,
        ];
    }

    protected function isRestoreLikeCommand(string $command): bool
    {
        $lower = strtolower(trim($command));

        return str_starts_with($lower, 'git restore') || str_starts_with($lower, 'git checkout');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function commandOutputSnippet(array $row): string
    {
        $stderr = trim(StringCoercion::toString($row['stderr'] ?? null, ''));
        $stdout = trim(StringCoercion::toString($row['stdout'] ?? null, ''));
        $text = $stderr !== '' ? $stderr : $stdout;
        if ($text === '') {
            return '';
        }

        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $snippet = implode(' ', array_slice(array_filter(array_map('trim', $lines)), 0, 3));

        return mb_strlen($snippet) > 200 ? mb_substr($snippet, 0, 197).'…' : $snippet;
    }

    /**
     * @return list<string>
     */
    protected function formatGitStatusLines(mixed $gitStatusAfter): array
    {
        if (! is_string($gitStatusAfter) || trim($gitStatusAfter) === '') {
            return ['- (not captured)'];
        }

        $lines = preg_split("/\r\n|\n|\r/", trim($gitStatusAfter)) ?: [];

        return $lines === [] ? ['- Clean working tree'] : array_map(static fn ($l) => '- '.$l, $lines);
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

        $storedConversation = is_array($run->metadata['conversation'] ?? null)
            ? $run->metadata['conversation']
            : [];
        $learningResult = $this->userSelfLearning->processAfterRun(
            $run,
            $prompt,
            $storedConversation,
            $modelRoute,
            $plan,
            $executorOutputs[0] ?? [],
            $lastAudit,
        );
        $run->update([
            'metadata' => array_merge($run->metadata ?? [], ['user_learning' => $learningResult]),
        ]);
        if ($emit !== null) {
            $this->emit($emit, $this->basePayload($run, 'user_learning_stored', [
                'status' => 'success',
                'summary' => 'User self-learning recorded to memory.',
                'artifacts' => $learningResult,
            ]));
        }

        if ($emit !== null) {
            $this->emit($emit, $this->events->memorySynced($run, $learningResult));
        }

        $tEval = microtime(true);
        $evaluation = $this->postMemoryEvaluation->evaluate(
            $finalOutput,
            $memPayload,
            $executorOutputs[0] ?? [],
            $lastAudit,
            $learningResult,
            $lastSecurity,
            $lastFinal,
            $modelRoute,
            $modelsResolved,
        );
        $evalMs = (int) round((microtime(true) - $tEval) * 1000);
        $evalTok = $this->estimateTokens(json_encode($evaluation) ?: '');
        $run->update([
            'metadata' => array_merge($run->metadata ?? [], ['post_memory_eval' => $evaluation]),
        ]);
        $this->logStep($run, 10000, 'post_memory_eval', $modelsResolved['evaluator'] ?? null, 'ollama', null, 'success', null, null, json_encode($evaluation), null, null, null, $evalMs, $evalTok, null, $this->events->metadata(
            'evaluator',
            'review',
            StringCoercion::toString($evaluation['summary'] ?? null, 'Post-memory evaluation completed.'),
            StringCoercion::toString($evaluation['recommendation'] ?? null, ''),
            [
                'evaluation' => $evaluation,
                'proof_summary' => $evaluation['proof_summary'] ?? [],
                'memory_summary' => $evaluation['memory_summary'] ?? [],
            ],
            'memory',
            'system'
        ));
        if ($emit !== null) {
            $this->emit($emit, $this->events->postMemoryEvalStarted($run));
            $this->emit($emit, $this->events->postMemoryEvalDone(
                $run,
                $evaluation,
                $modelsResolved['evaluator'] ?? null,
                $evalMs,
                $evalTok,
            ));
        }

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
            'post_memory_eval' => $evaluation,
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
     * @param  array<string, mixed>  $execResult
     * @return array<string, mixed>
     */
    protected function ensureExecutorEvidence(Run $run, array $plan, array $execResult, string $userPrompt, ?callable $emit): array
    {
        if (ExecutorEvidenceSupport::countFilesRead($execResult) > 0) {
            return $execResult;
        }

        $bootstrap = $this->bootstrapReadTargetFiles($run, $emit);
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $bootstrap);

        if (ExecutorEvidenceSupport::countFilesRead($execResult) > 0) {
            return $execResult;
        }

        $discoveryReads = $this->runPathDiscovery($run, $plan, $userPrompt, $emit);
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $discoveryReads);

        if (ExecutorEvidenceSupport::countFilesRead($execResult) > 0) {
            return $execResult;
        }

        if (RepoTaskDetector::requiresRepositoryAccess($userPrompt) || ($plan['allow_broad_repo_scan'] ?? false)) {
            $searchReads = $this->auditFileSearchReads($run, $userPrompt, $emit);
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
    protected function auditFileSearchReads(Run $run, string $userPrompt, ?callable $emit): array
    {
        $reads = [];
        $terms = $this->discovery->extractSymbolsFromText($userPrompt);
        if ($terms === []) {
            $terms = ['class', 'function'];
        }

        foreach (array_slice($terms, 0, 8) as $term) {
            $inv = $this->tools->invoke($run->id, null, [
                'tool' => 'file_search',
                'payload' => ['q' => $term, 'glob' => '*.php'],
            ], $emit);

            if (($inv['status'] ?? '') !== 'ok') {
                continue;
            }

            $result = is_array($inv['result'] ?? null) ? $inv['result'] : [];
            $matches = is_array($result['matches'] ?? null) ? $result['matches'] : [];
            foreach (array_slice($matches, 0, 10) as $match) {
                if (! is_array($match)) {
                    continue;
                }
                $path = (string) ($match['path'] ?? '');
                if ($path === '') {
                    continue;
                }
                $reads = array_merge($reads, $this->readTargetPath($run, $path, 'audit file_search: '.$term, $emit));
            }
        }

        if ($reads !== []) {
            $this->emit($emit, $this->basePayload($run, 'audit_file_search_done', [
                'status' => 'success',
                'summary' => count(array_filter($reads, static fn ($r) => is_array($r) && ($r['found'] ?? false))) .' file(s) read from audit file_search.',
                'artifacts' => ['file_search_reads' => $reads],
            ]));
        }

        return $reads;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array<string, mixed>>
     */
    protected function runPathDiscovery(Run $run, array $plan, string $userPrompt, ?callable $emit): array
    {
        $resolved = [];
        $text = $userPrompt.' '.json_encode($plan);
        foreach ($this->discovery->extractSymbolsFromText($text) as $symbol) {
            $path = $this->discovery->resolvePathHint($symbol);
            if ($path !== null) {
                $resolved[$path] = 'symbol: '.$symbol;
            }
        }

        if ($plan['allow_broad_repo_scan'] ?? false) {
            foreach ($this->discovery->globPaths('app/Http/Controllers/*.php', 15) as $path) {
                $resolved[$path] = 'broad repo scan';
            }
        }

        $reads = [];
        foreach (array_slice($resolved, 0, 15, true) as $path => $reason) {
            $reads = array_merge($reads, $this->readTargetPath($run, $path, (string) $reason, $emit));
        }

        foreach (array_slice($this->discovery->extractSymbolsFromText($text), 0, 5) as $symbol) {
            $inv = $this->tools->invoke($run->id, null, [
                'tool' => 'file_glob',
                'payload' => ['pattern' => '**/*'.$symbol.'*'],
            ], $emit);
            if (($inv['status'] ?? '') !== 'ok') {
                continue;
            }
            $result = is_array($inv['result'] ?? null) ? $inv['result'] : [];
            $matches = is_array($result['matches'] ?? null) ? $result['matches'] : [];
            foreach (array_slice($matches, 0, 5) as $match) {
                $path = is_array($match) ? (string) ($match['path'] ?? '') : '';
                if ($path !== '') {
                    $reads = array_merge($reads, $this->readTargetPath($run, $path, 'file_glob: '.$symbol, $emit));
                }
            }
        }

        if ($reads !== [] || $resolved !== []) {
            $this->emit($emit, $this->basePayload($run, 'path_discovery', [
                'status' => 'success',
                'summary' => count($resolved).' path(s) resolved; '.ExecutorEvidenceSupport::countFilesRead(['files_read' => $reads]).' file(s) read.',
                'artifacts' => [
                    'resolved_paths' => array_keys($resolved),
                    'discovery_reads' => $reads,
                ],
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
            'preview' => isset($result['preview']) ? Str::limit((string) $result['preview'], 2000) : null,
            'reason' => $reason,
            'tool_status' => $inv['status'] ?? 'error',
        ]];
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $lastAudit
     * @param  array<string, mixed>|null  $lastSecurity
     * @return list<string>
     */
    protected function auditDimensionLines(array $modelRoute, array $lastAudit, ?array $lastSecurity): array
    {
        if (($modelRoute['audit_mode'] ?? '') !== 'full') {
            return [];
        }

        return app(AuditReportFormatter::class)->formatDimensionSections($lastAudit, $lastSecurity);
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     */
    protected function preflightReadTargetFiles(Run $run, array $plan, ?callable $emit, array $modelRoute = []): array
    {
        $reads = [];
        $targets = is_array($plan['target_file_list'] ?? null) ? $plan['target_file_list'] : [];

        if ($targets === []) {
            $bootstrap = ['composer.json', 'package.json', 'README.md', 'routes/web.php', 'routes/api.php', '.env.example'];
            if (($modelRoute['audit_mode'] ?? '') === 'full') {
                $bootstrap = array_merge($bootstrap, [
                    'docker-compose.yml',
                    'compose.yaml',
                    'phpunit.xml',
                    'nuxt.config.ts',
                    'vite.config.ts',
                    'app/config/bossku.php',
                    'config/bossku.php',
                    'bootstrap/app.php',
                ]);
            }
            foreach ($bootstrap as $path) {
                $targets[] = ['path' => $path, 'reason' => 'bootstrap scan'];
            }
        }

        $targetLimit = ($modelRoute['audit_mode'] ?? '') === 'full' ? 20 : 10;
        foreach (array_slice($targets, 0, $targetLimit) as $target) {
            if (! is_array($target)) {
                continue;
            }
            $hint = (string) ($target['path'] ?? '');
            $reason = (string) ($target['reason'] ?? 'planner target');
            $resolved = $this->discovery->resolvePathHint($hint);
            $path = $resolved ?? $hint;
            $reads = array_merge($reads, $this->readTargetPath($run, $path, $reason, $emit));
        }

        $routesRead = false;
        foreach ($reads as $item) {
            if (is_array($item) && ($item['path'] ?? '') === 'routes/web.php' && ($item['found'] ?? false)) {
                $routesRead = true;
                break;
            }
        }

        if ($routesRead) {
            foreach (array_slice($this->discovery->controllersFromRoutesFile(), 0, 12) as $controllerPath) {
                $reads = array_merge($reads, $this->readTargetPath($run, $controllerPath, 'routes/web.php controller', $emit));
            }
        }

        $foundCount = count(array_filter($reads, static fn ($r) => is_array($r) && ($r['found'] ?? false)));

        if ($reads !== []) {
            $this->emit($emit, $this->basePayload($run, 'preflight_reads_done', [
                'status' => 'success',
                'summary' => $foundCount.' file(s) read from the active project ('.count($reads).' probed).',
                'artifacts' => ['preflight_reads' => $reads],
            ]));
        }

        return $reads;
    }

    protected function promptMentionsRepo(string $prompt): bool
    {
        return RepoTaskDetector::requiresRepositoryAccess($prompt);
    }

    /**
     * @param  array<string, mixed>  $execResult
     */
    protected function executorFailedFromLlmJson(array $execResult): bool
    {
        if (($execResult['status'] ?? '') !== 'failed') {
            return false;
        }

        $issues = is_array($execResult['known_issues'] ?? null) ? $execResult['known_issues'] : [];
        foreach ($issues as $issue) {
            $text = is_string($issue) ? $issue : StringCoercion::toString($issue);
            if (
                stripos($text, 'invalid_json') !== false
                || stripos($text, 'All models failed') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $execResult
     */
    protected function executorFailureSummary(array $execResult): string
    {
        $issues = is_array($execResult['known_issues'] ?? null) ? $execResult['known_issues'] : [];
        $first = is_string($issues[0] ?? null) ? $issues[0] : StringCoercion::toString($issues[0] ?? null, '');

        if ($first !== '') {
            return 'Executor failed: '.$first.' Preflight reads are included as read_previews for context only.';
        }

        return 'Executor failed before producing valid JSON output. Preflight reads are included as read_previews for context only.';
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    protected function enrichPlanWithExecutionHints(array $plan, array $modelRoute): array
    {
        $commands = $this->collectPlanCommands($plan);
        $needsDocker = $commands !== [] && $this->planCommandsNeedDocker($commands);

        if ($needsDocker && ! $this->projectCommands->dockerComposeEnabled()) {
            $plan['execution_mode'] = 'user_must_run_commands';
            $plan['user_commands'] = $commands;
        } elseif ($modelRoute['needs_executor'] ?? false) {
            $plan['execution_mode'] = 'delegate_executor';
        } else {
            $plan['execution_mode'] = 'answer_only';
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    protected function collectPlanCommands(array $plan): array
    {
        $out = [];
        foreach (['suggested_tests', 'user_commands', 'commands_run'] as $key) {
            $items = is_array($plan[$key] ?? null) ? $plan[$key] : [];
            foreach ($items as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $out[] = trim($item);
                }
            }
        }
        foreach (is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [] as $step) {
            if (! is_array($step)) {
                continue;
            }
            $cmd = $step['command'] ?? $step['test_command'] ?? null;
            if (is_string($cmd) && trim($cmd) !== '') {
                $out[] = trim($cmd);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $commands
     */
    protected function planCommandsNeedDocker(array $commands): bool
    {
        foreach ($commands as $command) {
            if (preg_match('/\bdocker\s+compose\b/i', $command) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $preflightReads
     */
    protected function formatPreflightSurveySummary(array $preflightReads): string
    {
        if ($preflightReads === []) {
            return '';
        }

        $lines = ['## Repository survey (preflight)'];
        foreach (array_slice($preflightReads, 0, 12) as $read) {
            if (! is_array($read)) {
                continue;
            }
            $path = (string) ($read['path'] ?? 'unknown');
            if (! ($read['found'] ?? false)) {
                $lines[] = '- `'.$path.'`: not found';

                continue;
            }
            $preview = StringCoercion::toString($read['preview'] ?? $read['excerpt'] ?? null, '');
            $preview = strlen($preview) > 200 ? substr($preview, 0, 200).'…' : $preview;
            $lines[] = '- `'.$path.'`: '.($preview !== '' ? $preview : 'read ok');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    protected function formatUserCommandsFromPlan(array $plan): string
    {
        if (($plan['execution_mode'] ?? '') !== 'user_must_run_commands') {
            return '';
        }

        $commands = is_array($plan['user_commands'] ?? null) ? $plan['user_commands'] : $this->collectPlanCommands($plan);
        if ($commands === []) {
            return '';
        }

        $lines = [
            '## Commands to run locally',
            'Bossku runs in Docker and cannot execute these inside the backend container.',
            'Run each command on your host, then start a new prompt and paste the full terminal output so the agent can analyze it and continue.',
        ];
        foreach ($commands as $command) {
            $lines[] = '```bash'."\n".$command."\n".'```';
        }

        return implode("\n\n", $lines);
    }
}
