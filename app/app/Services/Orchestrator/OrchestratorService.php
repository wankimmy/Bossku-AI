<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Project;
use App\Services\BosskuAi\CodebaseIndexService;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Skill;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\BosskuAi\AgentPersonaService;
use App\Support\LlmTelemetry;
use App\Support\StringCoercion;
use App\Services\BosskuAi\BosskuResponseIndicator;
use App\Services\BosskuAi\ContextBudgetGuard;
use App\Services\BosskuAi\RepoTaskDetector;
use App\Services\BosskuAi\WorkflowRouteHelper;
use App\Services\Company\StaffCouncilService;
use App\Services\Company\WorkIssueService;
use App\Services\Council\AiCouncilService;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\PromptRouteClassifier;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\BosskuAi\SkillRouterService;
use App\Services\Graph\KnowledgeGraphBuilder;
use App\Services\Governance\ExecutorApprovalService;
use App\Services\Learning\UserSelfLearningService;
use App\Services\Project\ChangedFileDiagnostics;
use App\Services\Project\ProjectCommandRunner;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\ProjectService;
use App\Services\Project\RunExecutionContext;
use App\Services\Workspace\WorktreeManager;
use App\Services\Specialists\SpecialistAgentDraftingService;
use App\Services\Specialists\SpecialistAgentRouter;
use App\Services\Specialists\SpecialistAgentRunner;
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
        protected DesignerService $designer,
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
        protected PlanCouncilService $planCouncil,
        protected StaffCouncilService $staffCouncil,
        protected WorkIssueService $workIssues,
        protected ProjectPathResolver $paths,
        protected ProjectFileDiscovery $discovery,
        protected ProjectService $projects,
        protected KnowledgeGraphBuilder $knowledgeGraph,
        protected ExecutorFileChangeApplier $executorFileApplier,
        protected ProjectCommandRunner $projectCommands,
        protected ExecutorApprovalService $executorApprovals,
        protected AgentPersonaService $agentPersonas,
        protected UserSelfLearningService $userSelfLearning,
        protected ObsidianSyncService $obsidianSync,
        protected SpecialistAgentRouter $specialistRouter,
        protected SpecialistAgentRunner $specialistRunner,
        protected SpecialistAgentDraftingService $specialistDrafting,
        protected AiCouncilService $aiCouncil,
        protected ResumeIntentClassifier $resumeIntentClassifier,
        protected CodebaseIndexService $codeIndex,
        protected RunExecutionContext $runExecution,
        protected WorktreeManager $worktrees,
        protected ExecutorPatchPreflight $patchPreflight,
        protected ?\App\Services\Kernel\Pipeline\KernelPipelineCoordinator $kernelCoordinator = null,
    ) {}

    /**
     * BOSSKU_KERNEL=graph dispatch. Runs the pipeline through the graph kernel
     * (durable checkpoints, resume, interrupts) instead of the legacy in-method
     * pipeline. Builds a minimal PipelineContext from the classifier; richer
     * context assembly (skills, rules, preflight) is the eval-gated finalization.
     * Default-off: only reachable when the flag is set and the coordinator is wired.
     *
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function dispatchToKernel(string $userPrompt, ?callable $emit, array $conversation, array $options): array
    {
        $run = Run::query()->create([
            'prompt' => $userPrompt,
            'status' => 'running',
            'run_kind' => is_string($options['run_kind'] ?? null) ? $options['run_kind'] : 'standard',
            'metadata' => ['engine' => 'graph', 'conversation_turns' => count($conversation)],
        ]);

        $classified = $this->promptRouteClassifier->classify($userPrompt);
        $route = is_array($classified['route'] ?? null) ? $classified['route'] : [];
        $workflow = (string) ($route['workflow'] ?? config('bossku.default_workflow', 'orchestrator_executor'));

        $context = new \App\Services\Kernel\Pipeline\PipelineContext(
            prompt: $userPrompt,
            workflow: $workflow,
            modelRoute: $route,
            routerContext: is_array($classified['router_meta'] ?? null) ? $classified['router_meta'] : [],
            conversation: $conversation,
            runId: (string) $run->id,
        );

        return $this->kernelCoordinator->run($run, $context, $emit);
    }

    protected function fusionFeaturesRequireLegacyPipeline(string $userPrompt): bool
    {
        if (! $this->settings->aiCouncilEnabled() && ! $this->settings->companyStaffEnabled()) {
            return false;
        }

        $classified = $this->promptRouteClassifier->classify($userPrompt);
        $route = is_array($classified['route'] ?? null) ? $classified['route'] : [];
        $workflow = (string) ($route['workflow'] ?? '');
        $intent = (string) ($route['specialist_intent'] ?? '');

        return in_array($workflow, ['direct_answer', 'writer_only'], true)
            || ! empty($route['specialist_agent'])
            || ($intent !== '' && $intent !== 'generic');
    }

    /**
     * @param  callable(array<string,mixed>): void|null  $emit
     * @return array<string,mixed>
     */
    /**
     * @param  list<array{role: string, content: string}>  $conversation
     * @param  array{routing_prompt?: string, long_prompt_attachment?: bool, metadata?: array<string, mixed>}  $options
     * @return array<string,mixed>
     */
    public function run(string $prompt, ?callable $emit = null, array $conversation = [], array $options = []): array
    {
        $tRun = microtime(true);
        $tokenAcc = 0;
        $userPrompt = $prompt;

        // BOSSKU_KERNEL=graph: route through the durable graph kernel instead of
        // the legacy in-method pipeline. Default-off (flag legacy); opt-out per
        // call with options['force_legacy'] = true.
        if ($this->kernelCoordinator !== null
            && \App\Services\Kernel\KernelMode::graph()
            && ($options['force_legacy'] ?? false) !== true
            && ! $this->fusionFeaturesRequireLegacyPipeline($userPrompt)) {
            return $this->dispatchToKernel($userPrompt, $emit, $conversation, $options);
        }

        $prompt = $this->effectivePrompt($userPrompt, $conversation);
        $routingSeed = trim((string) ($options['routing_prompt'] ?? $userPrompt));
        $routingPrompt = $this->effectivePrompt($routingSeed !== '' ? $routingSeed : $userPrompt, $conversation);
        $agentPrompt = trim($prompt."\n\n".$this->projects->agentWorkspaceContext());

        $runMeta = [
            'conversation_turns' => count($conversation),
            'conversation' => $conversation,
        ];
        if (is_array($options['metadata'] ?? null)) {
            $runMeta = array_merge($runMeta, $options['metadata']);
        }
        $activeProject = $this->paths->activeProject();
        if ($activeProject !== null) {
            $runMeta['active_project_id'] = $activeProject->id;
            $runMeta['active_project_name'] = $activeProject->name;
        }

        $runKind = is_string($options['run_kind'] ?? null) ? (string) $options['run_kind'] : 'standard';
        $parentRunId = is_string($options['parent_run_id'] ?? null) ? (string) $options['parent_run_id'] : null;
        $supervisorSlot = isset($options['supervisor_slot']) ? (int) $options['supervisor_slot'] : null;
        $existingRunId = is_string($options['existing_run_id'] ?? null) ? (string) $options['existing_run_id'] : null;

        if ($existingRunId !== null && $existingRunId !== '') {
            $run = Run::query()->findOrFail($existingRunId);
            $run->update([
                'prompt' => $userPrompt,
                'status' => 'running',
                'metadata' => array_merge(is_array($run->metadata) ? $run->metadata : [], $runMeta),
                'run_kind' => $runKind,
                'parent_run_id' => $parentRunId ?: $run->parent_run_id,
                'supervisor_slot' => $supervisorSlot ?? $run->supervisor_slot,
            ]);
        } else {
            $run = Run::query()->create([
                'prompt' => $userPrompt,
                'status' => 'running',
                'metadata' => $runMeta,
                'run_kind' => $runKind,
                'parent_run_id' => $parentRunId,
                'supervisor_slot' => $supervisorSlot,
            ]);
        }

        $this->runExecution->bind((string) $run->getKey());

        try {

        if ($this->shouldProvisionWorktree($run, $options)) {
            try {
                $workspace = $this->worktrees->provisionForRun($run, $activeProject, is_array($options['workspace_intent'] ?? null) ? $options['workspace_intent'] : []);
                $this->emit($emit, $this->basePayload($run, 'workspace_ready', [
                    'status' => 'success',
                    'summary' => 'Isolated worktree ready.',
                    'artifacts' => [
                        'branch_name' => $workspace->branch_name,
                        'worktree_path' => $workspace->worktree_path,
                    ],
                ]));
            } catch (\Throwable $e) {
                if ($this->mustFailOnWorktreeError($runKind, $options)) {
                    $run->update(['status' => 'failed']);
                    $this->emit($emit, $this->basePayload($run, 'workspace_failed', [
                        'status' => 'fail',
                        'summary' => 'Worktree provisioning failed.',
                        'message' => $e->getMessage(),
                    ]));
                    throw new \RuntimeException('Worktree provisioning failed: '.$e->getMessage(), 0, $e);
                }
                $this->emit($emit, $this->basePayload($run, 'workspace_failed', [
                    'status' => 'warning',
                    'summary' => 'Worktree provisioning failed; using project root.',
                    'message' => $e->getMessage(),
                ]));
            }
        }

        $this->emit($emit, $this->basePayload($run, 'run_started', [
            'status' => 'success',
            'summary' => 'Run started.',
            'message' => 'BosskuAI is preparing the Ollama agent workflow.',
        ]));

        $t0 = microtime(true);
        $classified = $this->promptRouteClassifier->classify($userPrompt);
        /** @var array<string, mixed> $modelRoute */
        $modelRoute = $classified['route'];
        $modelsResolved = $classified['models_resolved'];
        if (($options['long_prompt_attachment'] ?? false) === true) {
            $modelRoute = $this->ensureExecutorCanReadLongPrompt($modelRoute);
            if (($modelsResolved['executor'] ?? '') === '') {
                $modelsResolved['executor'] = (string) ($this->modelConfig->executorProfile('default')['primary'] ?? 'glm-5.1');
            }
        }
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
            $memories = $this->memory->searchForRun($run, $agentPrompt, $this->settings->maxMemoryResults(), $skillTag);
            $memPayload = $memories->map(fn (Memory $m) => [
                'id' => $m->id,
                'summary' => $m->human_summary ?: Str::limit($m->content, 200),
                'type' => $m->type,
            ])->values()->all();

            // "Know your user": always surface the active user profile, even when
            // it is not the closest semantic match, so every response is grounded
            // in who the operator is.
            $profile = Memory::query()
                ->where('type', 'user')
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->first();
            if ($profile && ! collect($memPayload)->contains(fn ($m) => ($m['id'] ?? null) === $profile->id)) {
                array_unshift($memPayload, [
                    'id' => $profile->id,
                    'summary' => $profile->human_summary ?: Str::limit($profile->content, 400),
                    'type' => 'user',
                ]);
            }

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
            $clarification = $this->clarification->ask($userPrompt, $conversation, $modelRoute, 'pre_execution', [], $run->id);
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
        } finally {
            $this->runExecution->clear();
        }
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $routerCtx
     * @param  list<array<string, mixed>>  $memPayload
     * @param  array<string, mixed>  $agentPayload
     * @return array<string, mixed>
     */
    protected function runSpecialistAgentStep(
        Run $run,
        SpecialistAgent $agent,
        string $userPrompt,
        string $agentPrompt,
        array $plan,
        array $routerCtx,
        array $memPayload,
        array $agentPayload,
        ?callable $emit,
    ): array {
        $agent->loadMissing('linkedSkill');
        $this->emit($emit, $this->basePayload($run, 'specialist_agent_started', [
            'status' => 'running',
            'agent' => $agent->role_slug,
            'model_role' => 'reasoning',
            'from_agent' => 'orchestrator',
            'to_agent' => $agent->role_slug,
            'summary' => $agent->display_name.' is preparing executor handoff.',
            'message' => 'Specialist is reading the planner output, project context, linked skill, and memory notes.',
            'step_number' => 3,
            'artifacts' => [
                'specialist_agent' => $agentPayload,
            ],
        ]));

        $skillContent = $agent->linkedSkill?->content;
        $handoff = $this->specialistRunner->run(
            $agent,
            $userPrompt,
            $this->projects->agentWorkspaceContext(),
            $plan,
            $routerCtx,
            $memPayload,
            $skillContent !== null ? Str::limit($skillContent, 6000) : null,
            $run->id,
        );
        $tokenEstimate = $this->estimateTokens(json_encode($handoff) ?: '');
        $model = StringCoercion::toString($handoff['_specialist_model'] ?? null);
        $provider = LlmTelemetry::resolveStepProvider($handoff);
        $status = isset($handoff['_specialist_error']) ? 'warning' : 'success';
        $message = StringCoercion::toString($handoff['handoff_to_executor'] ?? null, 'Specialist handoff is ready.');
        $artifacts = [
            'specialist_agent' => $agentPayload,
            'specialist_handoff' => $handoff,
            'handoff' => $handoff,
        ];

        $this->logStep(
            $run,
            3,
            'specialist_agent',
            $model !== '' ? $model : null,
            $provider,
            $agent->linkedSkill?->name,
            $status === 'success' ? 'success' : 'failed',
            $agentPrompt,
            json_encode(['plan' => $plan, 'router' => $routerCtx, 'specialist_agent' => $agentPayload]),
            json_encode($handoff),
            null,
            null,
            null,
            (int) ($handoff['latency_ms'] ?? 0),
            $tokenEstimate,
            StringCoercion::toString($handoff['_specialist_error'] ?? null) ?: null,
            $this->events->metadata(
                $agent->role_slug,
                'reasoning',
                StringCoercion::toString($handoff['summary'] ?? null, $agent->display_name.' completed handoff.'),
                $message,
                $artifacts,
                $agent->role_slug,
                'executor',
            )
        );

        $agent->recordUsage();
        $agentPayload = $this->specialistRouter->payloadForAgent($agent->refresh());
        $artifacts['specialist_agent'] = $agentPayload;

        $this->emit($emit, $this->basePayload($run, 'specialist_agent_done', [
            'status' => $status,
            'agent' => $agent->role_slug,
            'model_role' => 'reasoning',
            'model' => $model,
            'from_agent' => $agent->role_slug,
            'to_agent' => 'executor',
            'summary' => StringCoercion::toString($handoff['summary'] ?? null, $agent->display_name.' completed handoff.'),
            'message' => $message,
            'latency_ms' => (int) ($handoff['latency_ms'] ?? 0),
            'token_estimate' => $tokenEstimate,
            'output' => json_encode($handoff) ?: '',
            'artifacts' => $artifacts,
        ]));

        return [
            'agent' => $agentPayload,
            'handoff' => $handoff,
            'summary' => StringCoercion::toString($handoff['summary'] ?? null),
            'handoff_to_executor' => $message,
            'model' => $model,
            'provider' => $provider,
            'latency_ms' => (int) ($handoff['latency_ms'] ?? 0),
            'token_estimate' => $tokenEstimate,
        ];
    }

    /**
     * Dynamically spawn a project-scoped specialist mid-run when no approved one
     * matched. Opt-in (Setting `dynamic_specialist_spawn`, default off) so existing
     * behaviour is unchanged. The specialist is drafted from the current plan and
     * run immediately; the draft is persisted for later human approval (it does not
     * auto-approve). Failures are swallowed so a spawn never breaks the run.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $routerCtx
     */
    protected function maybeSpawnDynamicSpecialist(
        Run $run,
        string $agentPrompt,
        ?\App\Models\BosskuAi\Project $activeProject,
        array $plan,
        array $routerCtx,
        ?callable $emit,
    ): ?SpecialistAgent {
        if ($activeProject === null) {
            return null;
        }
        if (! $this->settings->getBool('dynamic_specialist_spawn', false)) {
            return null;
        }

        // Only worth specialising when there is a concrete plan to specialise on.
        $hasPlan = ($plan['target_file_list'] ?? []) !== [] || ($plan['checklist'] ?? []) !== [];
        if (! $hasPlan) {
            return null;
        }

        $skillName = StringCoercion::toString(
            $routerCtx['primary_skill']['name'] ?? $run->selected_skill_name ?? null
        );

        try {
            $agent = $this->specialistDrafting->draftFromRun($run, [
                'skill_name' => $skillName,
                'router_context' => $routerCtx,
                'planner_output' => $plan,
            ], force: true);
        } catch (\Throwable $e) {
            return null;
        }

        $this->emit($emit, $this->basePayload($run, 'specialist_agent_spawned', [
            'status' => 'success',
            'agent' => $agent->role_slug,
            'model_role' => 'reasoning',
            'from_agent' => 'orchestrator',
            'to_agent' => $agent->role_slug,
            'summary' => $agent->display_name.' was spawned on demand for this task.',
            'message' => 'No approved specialist matched, so the orchestrator drafted one from the plan. Review and approve it under Agents to reuse it next time.',
            'artifacts' => [
                'specialist_agent' => $this->specialistRouter->payloadForAgent($agent),
            ],
        ]));

        return $agent;
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
        ?array $approvedPlan = null,
        ?array $approvedRouterCtx = null,
        bool $skipPlanReview = false,
    ): array {
        $activeProject = $this->paths->activeProject();
        $workflow = (string) ($modelRoute['workflow'] ?? 'orchestrator_executor_auditor');

        if ($workflow === 'direct_answer') {
            return $this->completeShortPathWorkflow(
                $run,
                $userPrompt,
                $agentPrompt,
                $modelRoute,
                $modelsResolved,
                $routerMeta,
                $memPayload,
                $conversation,
                $emit,
                $tokenAcc,
                $tRun,
                'direct_answer',
                fn () => $this->directAnswer->answer($userPrompt, $modelRoute, $run->id),
            );
        }

        if ($workflow === 'writer_only') {
            return $this->completeShortPathWorkflow(
                $run,
                $userPrompt,
                $agentPrompt,
                $modelRoute,
                $modelsResolved,
                $routerMeta,
                $memPayload,
                $conversation,
                $emit,
                $tokenAcc,
                $tRun,
                'writer_only',
                fn () => $this->writer->write($userPrompt, $modelRoute, $run->id),
            );
        }

        if ($approvedRouterCtx !== null) {
            $routerCtx = $approvedRouterCtx;
        } else {
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
        }

        $specialistMatch = $this->specialistRouter->matchDetailed($agentPrompt, $activeProject, $modelRoute);
        $matchedSpecialist = $specialistMatch->agent;
        $specialistAgentPayload = null;
        if ($matchedSpecialist !== null) {
            $specialistAgentPayload = $specialistMatch->toPayload();
            $routerCtx['specialist_agent'] = $specialistAgentPayload;
            $modelRoute['specialist_agent'] = $specialistAgentPayload;

            $this->emit($emit, $this->events->specialistAgentSelected($run, $matchedSpecialist, $specialistAgentPayload));
        }

        $repoAvailable = true;
        $repoError = '';
        $repoRoot = '';
        $rootAssessment = null;
        try {
            $repoRoot = $this->paths->repoRoot();
            $rootAssessment = $this->discovery->assessActiveRoot();
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

        if ($approvedPlan === null) {
            $this->emit($emit, $this->basePayload($run, 'planner_started', [
                'status' => 'running',
                'agent' => 'orchestrator',
                'model_role' => 'reasoning',
                'summary' => 'Orchestrator is planning the task.',
            ]));
        } else {
            $this->emit($emit, $this->basePayload($run, 'planner_review_approved', [
                'status' => 'success',
                'agent' => 'planner',
                'model_role' => 'reasoning',
                'summary' => 'Master plan approved; executor can start.',
            ]));
        }
        $t0 = microtime(true);
        try {
            $this->emit($emit, $this->basePayload($run, 'active_project', [
                'status' => $repoAvailable ? 'success' : 'fail',
                'summary' => 'Active project: '.($activeProject?->name ?? 'default /repo'),
                'message' => $this->projects->agentWorkspaceContext(),
                'repo_root' => $repoRoot,
                'repo_mounted' => $repoAvailable,
                'repo_error' => $repoAvailable ? null : $repoError,
                'manifest_total' => is_array($rootAssessment) ? ($rootAssessment['manifest_total'] ?? null) : null,
                'appears_empty' => is_array($rootAssessment) ? (bool) ($rootAssessment['appears_empty'] ?? false) : false,
                'empty_project_warning' => is_array($rootAssessment) ? ($rootAssessment['message'] ?? null) : null,
                'active_project' => $activeProject?->only(['id', 'name', 'host_path', 'container_path']),
            ]));
            if (is_array($rootAssessment) && ($rootAssessment['appears_empty'] ?? false) && ($rootAssessment['message'] ?? null) !== null) {
                $this->emit($emit, $this->basePayload($run, 'active_project_empty_warning', [
                    'status' => 'warning',
                    'summary' => (string) $rootAssessment['message'],
                    'repo_root' => $repoRoot,
                    'manifest_total' => $rootAssessment['manifest_total'] ?? 0,
                    'top_level' => $rootAssessment['top_level'] ?? [],
                ]));
            }
        } catch (\Throwable) {
            //
        }

        if ($approvedPlan !== null) {
            $plan = $approvedPlan;
            $orchModel = (string) ($modelsResolved['orchestrator'] ?? $this->settings->plannerModel());
        } else {
            $plan = $this->planner->plan($agentPrompt, $memPayload, $routerCtx, $modelRoute, $conversation, $run->id);
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
            $this->logStep($run, 2, 'planner', $orchModel, LlmTelemetry::resolveStepProvider($plan), null, 'success', $prompt, json_encode(['router' => $routerCtx, 'route' => $modelRoute]), json_encode($plan), null, null, null, $planMs, $planTokens, null, $this->events->metadata(
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
            $plan = $this->applyPlanCouncilReview(
                $run,
                $plan,
                $modelRoute,
                $routerCtx,
                $tokenAcc,
                is_array($specialistAgentPayload) ? $specialistAgentPayload : [],
                $emit,
            );
            $plan = $this->applyStaffCouncilReview(
                $run,
                $plan,
                $modelRoute,
                $routerCtx,
                $tokenAcc,
                $activeProject,
                $emit,
            );
        }

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

            $clarificationMode = $this->settings->orchestratorClarificationMode();
            $shouldPauseForPlannerQuestions = $clarificationMode !== 'off'
                && ($isLowConfidence || $clarificationMode === 'always');
            if ($shouldPauseForPlannerQuestions) {
                $plannerSummary = $isLowConfidence
                    ? 'Low-confidence plan — please answer before BosskuAI continues.'
                    : 'Planner needs your input before execution continues.';
                $plannerClarification = $this->plannerQuestionsToClarification($plannerQuestions, $plannerSummary);
                if ($plannerClarification['questions'] !== []) {
                    return $this->pauseForClarification(
                        $run,
                        $plannerClarification,
                        'planner_questions',
                        [
                            'user_prompt' => $userPrompt,
                            'effective_prompt' => $prompt,
                            'agent_prompt' => $agentPrompt,
                            'conversation' => $conversation,
                            'model_route' => $modelRoute,
                            'models_resolved' => $modelsResolved,
                            'router_meta' => $routerCtx,
                            'router_ctx' => $routerCtx,
                            'mem_payload' => $memPayload,
                            'token_acc' => $tokenAcc,
                            't_run' => $tRun,
                            'plan' => $plan,
                        ],
                        $emit,
                        'planner',
                        'planner_questions',
                    );
                }
            }
        }

        if ($this->shouldPauseForPlannerReview($plan, $modelRoute, $workflow, $skipPlanReview)) {
            return $this->pauseForClarification(
                $run,
                $this->buildPlannerReviewClarification($plan, $plannerQuestions),
                'planner_review',
                [
                    'user_prompt' => $userPrompt,
                    'effective_prompt' => $prompt,
                    'agent_prompt' => $agentPrompt,
                    'conversation' => $conversation,
                    'model_route' => $modelRoute,
                    'models_resolved' => $modelsResolved,
                    'router_meta' => $routerMeta,
                    'router_ctx' => $routerCtx,
                    'mem_payload' => $memPayload,
                    'token_acc' => $tokenAcc,
                    't_run' => $tRun,
                    'plan' => $plan,
                ],
                $emit,
                'planner',
                'planner_review',
            );
        }

        $execProfileKey = (string) ($plan['executor_profile'] ?? $modelRoute['executor_profile'] ?? 'default');
        if ($this->settings->executorRiskAwareProfile()) {
            $riskAwareKey = self::firstPassProfileKey($execProfileKey, $modelRoute, $plan);
            if ($riskAwareKey !== $execProfileKey) {
                $this->emit($emit, $this->basePayload($run, 'model_escalated', [
                    'status' => 'info',
                    'agent' => 'executor',
                    'summary' => 'Risk-aware routing: first executor pass uses the '.$riskAwareKey.' profile.',
                    'from_profile' => $execProfileKey,
                    'to_profile' => $riskAwareKey,
                ]));
                $execProfileKey = $riskAwareKey;
            }
        }
        $plan = $this->budgetGuard->narrowPlan($plan, $execProfileKey);

        if ($this->designer->shouldRun($plan, $execProfileKey)) {
            $designStart = microtime(true);
            $this->emit($emit, $this->basePayload($run, 'designer_step_started', [
                'status' => 'running',
                'agent' => 'designer',
                'model_role' => 'reasoning',
                'from_agent' => 'orchestrator',
                'to_agent' => 'designer',
                'summary' => 'Designer is producing UI/UX spec before implementation.',
                'message' => 'Design phase started for frontend work.',
            ]));
            $designResult = $this->designer->design($agentPrompt, $plan, $modelRoute, $run->id);
            $designMs = (int) round((microtime(true) - $designStart) * 1000);
            $designTokens = $this->estimateTokens(json_encode($designResult) ?: '');
            $designModel = (string) ($designResult['_designer_model'] ?? $modelsResolved['orchestrator'] ?? '');
            if (($designResult['error'] ?? false) !== true) {
                $plan['design_spec'] = $designResult;
            }
            $this->logStep(
                $run,
                2,
                'designer',
                $designModel,
                LlmTelemetry::resolveStepProvider($designResult),
                null,
                ($designResult['error'] ?? false) === true ? 'failed' : 'success',
                $agentPrompt,
                json_encode(['plan_summary' => $plan['summary'] ?? '']),
                json_encode($designResult),
                null,
                null,
                null,
                $designMs,
                $designTokens,
                ($designResult['error'] ?? false) === true ? (string) ($designResult['message'] ?? 'design failed') : null,
                $this->events->metadata(
                    'designer',
                    'reasoning',
                    StringCoercion::toString($designResult['design_summary'] ?? null, 'Design spec ready.'),
                    StringCoercion::toString($designResult['handoff_message'] ?? null, 'Handing off to Executor.'),
                    ['design_spec' => $designResult],
                    'orchestrator',
                    'executor'
                )
            );
            $tokenAcc += $designTokens;
            $modelsResolved['designer'] = $designModel;
            $this->emit($emit, $this->basePayload($run, 'designer_step_done', [
                'status' => ($designResult['error'] ?? false) === true ? 'failed' : 'success',
                'agent' => 'designer',
                'model' => $designModel,
                'latency_ms' => $designMs,
                'summary' => StringCoercion::toString($designResult['design_summary'] ?? null, 'Design phase complete.'),
            ]));
        }

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

        // No approved specialist matched? When dynamic spawning is enabled, draft one
        // on demand from the plan so this run still gets specialist guidance (and the
        // draft is surfaced under Agents for review).
        if ($matchedSpecialist === null) {
            $matchedSpecialist = $this->maybeSpawnDynamicSpecialist($run, $agentPrompt, $activeProject, $plan, $routerCtx, $emit);
            if ($matchedSpecialist !== null) {
                $specialistAgentPayload = $this->specialistRouter->payloadForAgent($matchedSpecialist);
            }
        }

        $specialistContext = [];
        if ($matchedSpecialist !== null) {
            $specialistContext = $this->runSpecialistAgentStep(
                $run,
                $matchedSpecialist,
                $userPrompt,
                $agentPrompt,
                $plan,
                $routerCtx,
                $memPayload,
                $specialistAgentPayload ?? $this->specialistRouter->payloadForAgent($matchedSpecialist),
                $emit,
            );
            $plan['specialist_agent'] = $specialistContext;
            $routerCtx['specialist_agent_handoff'] = $specialistContext;
            $modelRoute['specialist_agent_handoff'] = $specialistContext;
            $modelsResolved['specialist_agent'] = (string) ($specialistContext['model'] ?? '');
            $tokenAcc += (int) ($specialistContext['token_estimate'] ?? 0);
        }

        $preflightReads = $this->preflightReadTargetFiles($run, $plan, $emit, $modelRoute);
        $preflightReads = $this->mergeSemanticPreflightReads($preflightReads, $agentPrompt, $activeProject);

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

        $executorStepNumber = $specialistContext !== [] ? 4 : 3;

        $this->emit($emit, $this->basePayload($run, 'executor_step_started', [
            'status' => 'running',
            'agent' => 'executor',
            'model_role' => 'coding',
            'from_agent' => 'orchestrator',
            'to_agent' => 'executor',
            'summary' => 'Executor is applying the plan.',
            'message' => StringCoercion::toString($plan['handoff_message'] ?? null, 'Executor received the plan.'),
            'step_number' => $executorStepNumber,
            'skill' => $skillName,
            'model' => $modelsResolved['executor'] ?? '',
        ]));

        if ($this->useAgenticExecutor()) {
            $execResult = app(\App\Services\Agents\AgenticExecutorAdapter::class)->execute(
                $step,
                $plan,
                $modelRoute,
                $execProfileKey,
                $run->id,
                $emit,
                $preflightReads,
            );
        } else {
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
                $run->id,
                $specialistContext,
            );
        }
        $execResult = ExecutorEvidenceSupport::mergePreflightReads($execResult, $preflightReads);
        $execResult = $this->applyExecutorCommands($run, $execResult, $emit);
        $this->maybeReindexAfterWrites($execResult, $activeProject);
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
        $this->logStep($run, $executorStepNumber, 'executor', $modelsResolved['executor'], LlmTelemetry::resolveStepProvider($execResult), $skillName, ($execResult['status'] ?? '') === 'failed' ? 'failed' : 'success', json_encode($step), json_encode($execResult), json_encode($execResult), null, null, null, (int) ($execResult['latency_ms'] ?? 0), $exTok, null, $this->events->metadata(
            'executor',
            'coding',
            'Executor completed the requested changes.',
            StringCoercion::toString($execResult['handoff_message'] ?? null, 'Sending changes to Auditor.'),
            $this->events->executorArtifacts($execResult),
            'executor',
            'auditor'
        ));
        $this->maybeEmitBudgetWarning($run, $tokenAcc, $tokenAcc + $exTok, $emit);
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
            $executorStepNumber,
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
        $checklistVerdictEmitted = false;

        $needsAuditor = ! $skipAuditor
            && ($modelRoute['needs_auditor'] ?? false)
            && $this->settings->auditEnabled()
            && WorkflowRouteHelper::workflowIncludesAuditor($workflow);

        if ($needsAuditor && ($execResult['needs_audit'] ?? true)) {
            $maxRevisionRounds = $this->settings->maxRevisionRounds();

            // Audit → revise loop: keep re-auditing after each revision until the auditor
            // passes or the revision-round budget is spent. Re-auditing means the final
            // verdict reflects the *revised* code, and any escalation to the user is based
            // on a fresh audit rather than a stale pre-revision one.
            while (true) {
                $execResult = $this->ensureExecutorEvidence($run, $plan, $execResult, $userPrompt, $emit);

                $this->emit($emit, $this->basePayload($run, 'auditor_started', [
                    'status' => 'running',
                    'agent' => 'auditor',
                    'model_role' => 'review',
                    'from_agent' => 'executor',
                    'to_agent' => 'auditor',
                    'summary' => $revisionRoundsUsed > 0
                        ? 'Auditor is re-reviewing the revised output (round '.($revisionRoundsUsed + 1).').'
                        : 'Auditor is reviewing executor output.',
                    'step_number' => $stepNum,
                ]));
                $tA = microtime(true);
                if ($this->executorFailedFromLlmJson($execResult)) {
                    $lastAudit = ExecutorEvidenceSupport::deterministicExecutorFailed(
                        $this->executorFailureSummary($execResult),
                    );
                    $auditMs = (int) round((microtime(true) - $tA) * 1000);
                }
                elseif ($this->settings->executorPatchPrecheck()
                    && ($precheckProblems = $this->patchPreflight->problems($execResult)) !== []) {
                    // Malformed/inapplicable patches bounce straight back to the
                    // executor with per-file feedback — no LLM audit call spent.
                    $lastAudit = ExecutorEvidenceSupport::deterministicPatchPrecheckFailed($precheckProblems);
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
                $this->logStep($run, $stepNum + 100 + $revisionRoundsUsed, 'auditor', $modelsResolved['auditor'], LlmTelemetry::resolveStepProvider($lastAudit), $skillName, $pass ? 'success' : (($lastAudit['status'] ?? '') === 'needs_revision' ? 'needs_revision' : 'failed'), json_encode($step), json_encode($execResult), json_encode($lastAudit), null, null, null, $auditMs, $auditTok, null, $this->events->metadata(
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

                // Reconcile checklist with auditor verdict + evidence; surface to UI
                $reconciledChecklist = $this->emitReconciledChecklistVerdict(
                    $run,
                    $plan,
                    $execResult,
                    $lastAudit,
                    $emit,
                );
                $plan = $reconciledChecklist['plan'];
                $checklistVerdictEmitted = $reconciledChecklist['emitted'] || $checklistVerdictEmitted;

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

                // Auditor is satisfied — leave the loop and continue to security/final review.
                if (($lastAudit['status'] ?? '') !== 'needs_revision') {
                    break;
                }

                // Auditor wants changes, but another revision can't help when the executor
                // produced unusable JSON or is explicitly waiting on the user. Explain and stop.
                if ($this->executorFailedFromLlmJson($execResult)) {
                    $this->emit($emit, $this->basePayload($run, 'executor_revision_skipped', [
                        'status' => 'warning',
                        'agent' => 'executor',
                        'summary' => 'Skipped executor revision: executor failed with JSON/model errors; re-running would not help.',
                        'message' => StringCoercion::toString($execResult['known_issues'][0] ?? null, 'Executor LLM output was invalid.'),
                    ]));
                    break;
                }
                if (($execResult['needs_user_input'] ?? false) === true) {
                    $this->emit($emit, $this->basePayload($run, 'executor_revision_skipped', [
                        'status' => 'warning',
                        'agent' => 'executor',
                        'summary' => 'Skipped executor revision: executor is waiting for your input first.',
                        'message' => StringCoercion::toString($execResult['blockers'][0] ?? $execResult['known_issues'][0] ?? null, 'User input required.'),
                    ]));
                    break;
                }

                // Hard blocker, or the revision-round budget is spent while the auditor still
                // wants changes → escalate to the user instead of silently shipping it.
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
                if ($maxRevisionRounds <= 0 || $revisionRoundsUsed >= $maxRevisionRounds) {
                    break;
                }

                // --- Run one revision round, then loop back to re-audit the result. ---
                $this->emit($emit, $this->basePayload($run, 'executor_revision_started', [
                    'status' => 'running',
                    'agent' => 'executor',
                    'model_role' => 'coding',
                    'from_agent' => 'auditor',
                    'to_agent' => 'executor',
                    'summary' => 'Executor is applying audit feedback (round '.($revisionRoundsUsed + 1).' of '.$maxRevisionRounds.').',
                    'message' => StringCoercion::toString($lastAudit['summary'] ?? null, 'Audit requested a revision.'),
                ]));

                $revisionStep = array_merge($step, [
                    'id' => 2 + $revisionRoundsUsed,
                    'title' => 'Fix audit feedback',
                ]);
                $auditFeedback = ExecutorEvidenceSupport::auditorPayloadForRevision(
                    $lastAudit,
                    $execResult,
                    $preflightReads,
                    $run->id,
                );
                $auditFeedback['original_prompt'] = $agentPrompt;

                $revProfileKey = self::revisionProfileKey(
                    $execProfileKey,
                    $revisionRoundsUsed,
                    $this->settings->executorRevisionEscalation(),
                );
                if ($revProfileKey !== $execProfileKey) {
                    $this->emit($emit, $this->basePayload($run, 'model_escalated', [
                        'status' => 'info',
                        'agent' => 'executor',
                        'summary' => 'Escalating to high_risk model profile for revision round '.($revisionRoundsUsed + 1).'.',
                        'from_profile' => $execProfileKey,
                        'to_profile' => $revProfileKey,
                    ]));
                }

                $revisionResult = $this->executor->execute(
                    $revisionStep,
                    $skillRow,
                    $ruleLines,
                    $pbExcerpt,
                    $chkExcerpt,
                    null,
                    $plan,
                    $modelRoute,
                    $revProfileKey,
                    $this->projects->agentWorkspaceContext(),
                    $preflightReads,
                    $auditFeedback,
                    $memPayload,
                    $conversation,
                    $run->id,
                );
                $revisionResult = ExecutorEvidenceSupport::mergePreflightReads($revisionResult, $preflightReads);
                $revisionResult = $this->applyExecutorCommands($run, $revisionResult, $emit);
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
                $this->logStep($run, $stepNum + 1 + $revisionRoundsUsed, 'executor_revision', $modelsResolved['executor'], LlmTelemetry::resolveStepProvider($revisionResult), $skillName, ($revisionResult['status'] ?? '') === 'failed' ? 'failed' : 'success', json_encode($revisionStep), json_encode(['audit' => $lastAudit, 'previous_executor' => $execResult]), json_encode($revisionResult), null, null, null, (int) ($revisionResult['latency_ms'] ?? 0), $revTok, null, $this->events->metadata(
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
                $revisionRoundsUsed++;
            }
        }

        $needsSecurityPass = (
            (($modelRoute['needs_security_auditor'] ?? false) && WorkflowRouteHelper::workflowIncludesSecurityAuditor($workflow))
            || ($lastAudit['requires_security_audit'] ?? false)
            || self::execResultHasCodeChanges($execResult)
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
            $this->logStep($run, $stepNum + 150, 'security_auditor', $modelsResolved['security_auditor'] ?? null, LlmTelemetry::resolveStepProvider($lastSecurity), $skillName, 'success', null, null, json_encode($lastSecurity), null, null, null, $sMs, $sTok, null, null);
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
            $lastFinal = $this->finalReviewer->review($agentPrompt, $modelRoute, $lastAudit, $lastSecurity, $execResult, $plan, $memPayload, $conversation, $run->id);
            $fMs = (int) round((microtime(true) - $tF) * 1000);
            $fTok = $this->estimateTokens(json_encode($lastFinal) ?: '');
            $this->logStep($run, $stepNum + 200, 'final_reviewer', $modelsResolved['final_reviewer'] ?? null, LlmTelemetry::resolveStepProvider($lastFinal), $skillName, 'success', null, null, json_encode($lastFinal), null, null, null, $fMs, $fTok, null, $this->events->metadata(
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
        if (! $checklistVerdictEmitted) {
            $reconciledChecklist = $this->emitReconciledChecklistVerdict(
                $run,
                $plan,
                $execResult,
                $lastAudit,
                $emit,
            );
            $plan = $reconciledChecklist['plan'];
        } else {
            $plan = $this->reconcilePlanChecklist($plan, $execResult, $lastAudit);
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
     * @param  list<array{role?: string, content?: string}>  $conversation
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, string>  $modelsResolved
     * @param  array<string, mixed>  $routerMeta
     * @param  list<array<string, mixed>>  $memPayload
     * @param  callable(): string  $draftGenerator
     * @return array<string, mixed>
     */
    protected function completeShortPathWorkflow(
        Run $run,
        string $userPrompt,
        string $agentPrompt,
        array $modelRoute,
        array $modelsResolved,
        array $routerMeta,
        array $memPayload,
        array $conversation,
        ?callable $emit,
        int $tokenAcc,
        float $tRun,
        string $kind,
        callable $draftGenerator,
    ): array {
        $activeProject = $this->paths->activeProject();

        $clarify = $this->councilClarificationIfNeeded(
            $run,
            $userPrompt,
            $modelRoute,
            $conversation,
            $modelsResolved,
            $routerMeta,
            $memPayload,
            $tokenAcc,
            $tRun,
            $agentPrompt,
            $emit,
        );
        if ($clarify !== null) {
            return $clarify;
        }

        $match = $this->specialistRouter->matchDetailed($userPrompt, $activeProject, $modelRoute);
        if ($match->agent !== null) {
            $modelRoute['specialist_agent'] = $match->toPayload();
            $this->emit($emit, $this->events->specialistAgentSelected($run, $match->agent, $match->toPayload()));
        }

        $councilOutcome = $this->shortPathBodyWithCouncil(
            $run,
            $userPrompt,
            $modelRoute,
            $activeProject,
            $conversation,
            $emit,
            $draftGenerator,
            true,
        );

        if (($councilOutcome['status'] ?? '') === 'needs_clarification') {
            return $this->pauseForClarification(
                $run,
                [
                    'questions' => is_array($councilOutcome['questions'] ?? null) ? $councilOutcome['questions'] : [],
                    'assumptions' => [],
                    'ready_to_proceed' => false,
                    'summary' => 'The AI council needs a little more context before it can answer accurately.',
                ],
                'council_postdraft',
                [
                    'user_prompt' => $userPrompt,
                    'effective_prompt' => $agentPrompt,
                    'model_route' => $modelRoute,
                    'models_resolved' => $modelsResolved,
                    'router_meta' => $routerMeta,
                    'mem_payload' => $memPayload,
                    'token_acc' => $tokenAcc,
                    't_run' => $tRun,
                ],
                $emit,
                'orchestrator',
                'ai_council',
            );
        }

        $body = (string) ($councilOutcome['body'] ?? '');

        return $this->finishShortPath(
            $run,
            $userPrompt,
            $modelRoute,
            $modelsResolved,
            $memPayload,
            $emit,
            $tokenAcc,
            $tRun,
            $kind,
            fn () => $body,
        );
    }

    /**
     * @param  list<array{role?: string, content?: string}>  $conversation
     * @param  callable(): string  $draftGenerator
     * @return array{status: string, body: string, questions?: list<array<string, mixed>>}
     */
    protected function shortPathBodyWithCouncil(
        Run $run,
        string $userPrompt,
        array $modelRoute,
        ?\App\Models\BosskuAi\Project $activeProject,
        array $conversation,
        ?callable $emit,
        callable $draftGenerator,
        bool $precheckDone = false,
    ): array {
        $draft = $draftGenerator();
        $this->emit($emit, $this->events->aiCouncilStarted($run, $modelRoute));

        $council = $this->aiCouncil->deliberate(
            $run,
            $userPrompt,
            $draft,
            $modelRoute,
            $activeProject,
            $conversation,
            $precheckDone,
        );

        $meta = is_array($run->metadata) ? $run->metadata : [];
        $meta['ai_council'] = $council;
        $run->update(['metadata' => $meta]);

        if (($council['status'] ?? '') === 'completed') {
            $this->emit($emit, $this->events->aiCouncilDone($run, $council));
        } elseif (($council['status'] ?? '') === 'needs_clarification') {
            $this->emit($emit, $this->events->aiCouncilNeedsClarification($run, $council));
        } else {
            $this->emit($emit, $this->events->aiCouncilSkipped($run, $council));
        }

        if (($council['status'] ?? '') === 'needs_clarification') {
            return [
                'status' => 'needs_clarification',
                'body' => $draft,
                'questions' => is_array($council['questions'] ?? null) ? $council['questions'] : [],
            ];
        }

        return [
            'status' => (string) ($council['status'] ?? 'skipped'),
            'body' => trim((string) ($council['final_output'] ?? $draft)),
        ];
    }

    /**
     * @param  array<string, mixed>  $modelRoute
     * @param  list<array{role?: string, content?: string}>  $conversation
     * @return array<string, mixed>|null
     */
    protected function councilClarificationIfNeeded(
        Run $run,
        string $userPrompt,
        array $modelRoute,
        array $conversation,
        array $modelsResolved,
        array $routerMeta,
        array $memPayload,
        int $tokenAcc,
        float $tRun,
        string $agentPrompt,
        ?callable $emit,
    ): ?array {
        if (($modelRoute['_council_precheck_done'] ?? false) === true) {
            return null;
        }

        $precheck = app(\App\Services\Council\CouncilQuestionService::class)
            ->analyze($userPrompt, $modelRoute, $conversation);
        if (! $precheck['needs_questions'] || $precheck['already_answered']) {
            return null;
        }

        return $this->pauseForClarification(
            $run,
            [
                'questions' => $precheck['questions'],
                'assumptions' => [],
                'ready_to_proceed' => false,
                'summary' => 'The AI council needs a little more context before it can answer accurately.',
            ],
            'council_precheck',
            [
                'user_prompt' => $userPrompt,
                'effective_prompt' => $agentPrompt,
                'model_route' => $modelRoute,
                'models_resolved' => $modelsResolved,
                'router_meta' => $routerMeta,
                'mem_payload' => $memPayload,
                'token_acc' => $tokenAcc,
                't_run' => $tRun,
            ],
            $emit,
            'orchestrator',
            'ai_council',
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

        $final = $body;
        $runMeta = is_array($run->metadata) ? $run->metadata : [];
        $aiCouncil = is_array($runMeta['ai_council'] ?? null) ? $runMeta['ai_council'] : null;
        $aiCouncilRan = is_array($aiCouncil);

        if ($kind === 'writer_only' && ! $aiCouncilRan) {
            $review = $this->staffCouncil->reviewContentDeliverable(
                $run,
                $prompt,
                $body,
                $modelRoute,
                $this->paths->activeProject(),
            );

            if (($review['status'] ?? '') === 'completed') {
                $reviewText = trim($this->formatStaffCouncilForFinal($review));
                if ($reviewText !== '') {
                    $final = trim($body)."\n\n## Staff council review\n".$reviewText;
                }
                $this->emit($emit, $this->events->staffCouncilDone($run, $review));
            } elseif (($review['reason'] ?? '') !== 'short_direct_answer') {
                $this->emit($emit, $this->events->staffCouncilSkipped($run, $review));
            }
            $tok = $this->estimateTokens($final);
        }

        $this->logStep($run, 5, $kind, $modelsResolved['direct_answer'] ?? $modelsResolved['writer'] ?? null, null, null, 'success', $prompt, $prompt, $final, null, null, null, $ms, $tok, null, [
            'routing_decision' => $modelRoute,
            'models_resolved' => $modelsResolved,
        ]);

        $memoryMode = (string) ($modelRoute['memory_mode'] ?? 'read_only');
        $this->writeMemoryIfNeeded($memoryMode, $prompt, $modelRoute, $modelsResolved, ['patch_summary' => Str::limit($body, 2000)], [], null, null, $run);
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

        $this->obsidianSync->sync($run, $modelRoute, $final);

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
     * @param  array<string, mixed>  $modelRoute
     * @return array<string, mixed>
     */
    protected function ensureExecutorCanReadLongPrompt(array $modelRoute): array
    {
        if (($modelRoute['needs_executor'] ?? false) === true) {
            return $modelRoute;
        }

        $modelRoute['needs_repo_context'] = true;
        $modelRoute['needs_executor'] = true;
        $modelRoute['needs_file_edit'] = false;
        $modelRoute['needs_auditor'] = false;
        $modelRoute['needs_security_auditor'] = false;
        $modelRoute['needs_final_reviewer'] = false;
        $modelRoute['workflow'] = 'orchestrator_executor';
        $modelRoute['executor_profile'] = 'default';
        $modelRoute['audit_mode'] = $modelRoute['audit_mode'] ?? 'standard';
        $reason = trim((string) ($modelRoute['reason'] ?? ''));
        $modelRoute['reason'] = trim($reason.' Long prompt attachment requires executor file reads.');

        return $modelRoute;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $execResult
     * @param  array<string, mixed>  $lastAudit
     * @return array<string, mixed>
     */
    /**
     * @param  list<array<string, mixed>>  $plannerQuestions
     * @return array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}
     */
    protected function plannerQuestionsToClarification(array $plannerQuestions, string $summary): array
    {
        $questions = [];
        foreach ($plannerQuestions as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $prompt = trim((string) ($item['question'] ?? $item['prompt'] ?? ''));
            if ($prompt === '') {
                continue;
            }
            $recommended = trim((string) ($item['recommended'] ?? ''));
            $questions[] = [
                'id' => (string) ($item['id'] ?? 'pq-'.($idx + 1)),
                'prompt' => $prompt,
                'why_it_matters' => trim((string) ($item['why'] ?? $item['why_it_matters'] ?? '')),
                'recommended' => $recommended !== '' ? $recommended : 'a',
                'options' => $this->clarification->normalizeOptionsToThree([], 'planner_questions'),
                'allow_free_text' => true,
            ];
            if (count($questions) >= 3) {
                break;
            }
        }

        return [
            'questions' => $questions,
            'assumptions' => [],
            'ready_to_proceed' => false,
            'summary' => $summary !== '' ? $summary : 'Planner needs your input before execution continues.',
        ];
    }

    protected function shouldPauseForPlannerReview(array $plan, array $modelRoute, string $workflow, bool $skipPlanReview): bool
    {
        if ($skipPlanReview) {
            return false;
        }

        $mode = $this->settings->orchestratorPlanConfirmationMode();
        if ($mode === 'off') {
            return false;
        }

        if (! in_array('executor', WorkflowRouteHelper::pipelineAgentsForWorkflow($workflow), true)) {
            return false;
        }

        if (($modelRoute['needs_executor'] ?? true) === false) {
            return false;
        }

        if (($plan['execution_mode'] ?? '') === 'answer_only') {
            return false;
        }

        if ($mode === 'questions') {
            return is_array($plan['planner_questions'] ?? null) && $plan['planner_questions'] !== [];
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $plannerQuestions
     * @return array{questions: list<array<string, mixed>>, assumptions: list<string>, ready_to_proceed: bool, summary: string}
     */
    protected function buildPlannerReviewClarification(array $plan, array $plannerQuestions): array
    {
        if ($plannerQuestions !== []) {
            $clarification = $this->plannerQuestionsToClarification(
                $plannerQuestions,
                'Review the master plan before execution.',
            );
            if ($clarification['questions'] !== []) {
                return $clarification;
            }
        }

        return [
            'questions' => [[
                'id' => 'planner-review',
                'prompt' => 'Review the master plan. Approve it to start execution, or request changes with feedback.',
                'why_it_matters' => 'The executor will use this plan as the source of truth.',
                'options' => [
                    ['id' => 'approve', 'label' => 'Approve plan', 'recommendation' => true],
                    ['id' => 'revise', 'label' => 'Revise plan'],
                    ['id' => 'hold', 'label' => 'Hold execution'],
                ],
                'allow_free_text' => true,
            ]],
            'assumptions' => $this->plannerReviewAssumptions($plan),
            'ready_to_proceed' => false,
            'summary' => 'Review the master plan before execution.',
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $routerCtx
     * @param  array<string, mixed>  $specialistAgentPayload
     * @return array<string, mixed>
     */
    protected function applyPlanCouncilReview(
        Run $run,
        array $plan,
        array $modelRoute,
        array $routerCtx,
        int $tokenAcc,
        array $specialistAgentPayload,
        ?callable $emit,
    ): array {
        $this->emit($emit, $this->events->councilReviewStarted($run));

        $review = $this->planCouncil->review(
            $plan,
            $modelRoute,
            $routerCtx,
            $tokenAcc,
            $specialistAgentPayload,
        );
        $plan['council_review'] = $review;

        if (($review['status'] ?? '') === 'skipped') {
            $this->emit($emit, $this->events->councilReviewSkipped($run, $review, $plan));
        } else {
            $this->emit($emit, $this->events->councilReviewDone($run, $review, $plan));
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $routerCtx
     * @return array<string, mixed>
     */
    protected function applyStaffCouncilReview(
        Run $run,
        array $plan,
        array $modelRoute,
        array $routerCtx,
        int $tokenAcc,
        ?Project $project,
        ?callable $emit,
    ): array {
        $this->emit($emit, $this->events->staffCouncilStarted($run));

        $review = $this->staffCouncil->reviewPlan(
            $run,
            $plan,
            $modelRoute,
            $routerCtx,
            $tokenAcc,
            $project,
        );
        $plan['staff_council'] = $review;

        if (($review['status'] ?? '') === 'skipped') {
            $this->emit($emit, $this->events->staffCouncilSkipped($run, $review, $plan));
        } else {
            $this->emit($emit, $this->events->staffCouncilDone($run, $review, $plan));
        }

        return $plan;
    }

    /** @param array<string, mixed> $review */
    protected function formatStaffCouncilForFinal(array $review): string
    {
        $lines = [];
        $consensus = trim(StringCoercion::toString($review['consensus'] ?? null));
        if ($consensus !== '') {
            $lines[] = 'Consensus: '.$consensus;
        }

        $recommendations = is_array($review['staff_recommendations'] ?? null) ? $review['staff_recommendations'] : [];
        if ($recommendations !== []) {
            $lines[] = 'Recommendations:';
            foreach (array_slice($recommendations, 0, 5) as $recommendation) {
                $text = trim(StringCoercion::toString($recommendation));
                if ($text !== '') {
                    $lines[] = '- '.$text;
                }
            }
        }

        $stopConditions = is_array($review['stop_conditions'] ?? null) ? $review['stop_conditions'] : [];
        if ($stopConditions !== []) {
            $lines[] = 'Stop conditions:';
            foreach (array_slice($stopConditions, 0, 5) as $condition) {
                $text = trim(StringCoercion::toString($condition));
                if ($text !== '') {
                    $lines[] = '- '.$text;
                }
            }
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    protected function plannerReviewAssumptions(array $plan): array
    {
        $items = [];
        foreach (['goal', 'summary', 'handoff_message'] as $key) {
            $value = trim(StringCoercion::toString($plan[$key] ?? null));
            if ($value !== '') {
                $items[] = $value;
            }
        }

        return array_values(array_unique(array_slice($items, 0, 3)));
    }

    protected function reconcilePlanChecklist(array $plan, array $execResult, array $lastAudit): array
    {
        $planChecklist = is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [];
        if ($planChecklist === []) {
            return $plan;
        }

        $verdictTrail = is_array($lastAudit['verdict_trail'] ?? null) ? $lastAudit['verdict_trail'] : [];
        $checklistStatus = is_array($execResult['checklist_status'] ?? null) ? $execResult['checklist_status'] : [];
        $evidence = ChecklistReconciler::evidenceFromExecutorResult($execResult);
        $plan['checklist'] = ChecklistReconciler::reconcile(
            $planChecklist,
            $checklistStatus,
            $verdictTrail,
            $evidence,
        );

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $lastAudit
     * @return array{plan: array<string, mixed>, emitted: bool}
     */
    protected function emitReconciledChecklistVerdict(
        Run $run,
        array $plan,
        array $execResult,
        array $lastAudit,
        ?callable $emit,
    ): array {
        $plan = $this->reconcilePlanChecklist($plan, $execResult, $lastAudit);
        $planChecklist = is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [];
        if ($planChecklist === [] || $emit === null) {
            return ['plan' => $plan, 'emitted' => false];
        }

        $verdictTrail = is_array($lastAudit['verdict_trail'] ?? null) ? $lastAudit['verdict_trail'] : [];
        $stats = ChecklistReconciler::summarizeChecklist($planChecklist);

        $this->emit($emit, $this->basePayload($run, 'checklist_verdict', [
            'status' => $stats['has_issues'] ? 'warning' : 'success',
            'agent' => 'auditor',
            'summary' => ChecklistReconciler::formatVerdictSummary($planChecklist, $verdictTrail),
            'verdict_trail' => $verdictTrail,
            'artifacts' => [
                'checklist' => $planChecklist,
            ],
        ]));

        return ['plan' => $plan, 'emitted' => true];
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
        $checklistStats = ChecklistReconciler::summarizeChecklist(
            is_array($plan['checklist'] ?? null) ? $plan['checklist'] : [],
        );
        $status = (($execResult['status'] ?? '') === 'failed' || ($lastAudit['status'] ?? '') === 'failed')
            ? 'Partially Completed'
            : 'Completed';
        if ($checklistStats['has_issues']) {
            $status = 'Partially Completed';
        }
        if ($commandOutcome['git_restore_failed']) {
            $status = 'Partially Completed';
        }
        $fileChanges = is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : [];
        $blockedFileChanges = array_values(array_filter($fileChanges, static function ($file): bool {
            if (! is_array($file)) {
                return false;
            }

            $approvalStatus = StringCoercion::toString($file['approval_status'] ?? null, '');

            return isset($file['approval_error'])
                || ($file['approval_skipped'] ?? false) === true
                || in_array($approvalStatus, ['pending', 'rejected'], true);
        }));
        $appliedFileChanges = array_values(array_filter($fileChanges, static function ($file): bool {
            if (! is_array($file)) {
                return true;
            }

            $approvalStatus = StringCoercion::toString($file['approval_status'] ?? null, '');

            return ! isset($file['approval_error'])
                && ($file['approval_skipped'] ?? false) !== true
                && ($approvalStatus === '' || in_array($approvalStatus, ['approved', 'auto_approved'], true));
        }));
        if ($blockedFileChanges !== []) {
            $status = 'Partially Completed';
        }
        $files = array_values(array_filter(array_map(
            fn ($file) => is_array($file)
                ? StringCoercion::toString($file['path'] ?? null)
                : StringCoercion::toString($file),
            $appliedFileChanges,
        )));
        $executedCommands = $commandOutcome['executed_lines'];
        $proposedCommands = $commandOutcome['proposed_lines'];
        $risks = $execResult['known_issues'] ?? [];
        foreach (array_slice($blockedFileChanges, 0, 10) as $file) {
            $path = StringCoercion::toString($file['path'] ?? null, 'unknown path');
            $reason = StringCoercion::toString(
                $file['approval_error'] ?? $file['approval_skip_reason'] ?? $file['approval_status'] ?? null,
                'not applied',
            );
            $risks[] = "File change not applied: {$path} ({$reason})";
        }
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
            ...($checklistStats['total'] > 0
                ? [
                    '',
                    '## Checklist verification',
                    'Verified '.$checklistStats['verified'].'/'.$checklistStats['total'].' plan item(s).'
                        .($checklistStats['disputed'] > 0 ? ' '.$checklistStats['disputed'].' disputed.' : '')
                        .($checklistStats['unverifiable'] > 0 ? ' '.$checklistStats['unverifiable'].' unverifiable.' : '')
                        .($checklistStats['needs_revision'] > 0 ? ' '.$checklistStats['needs_revision'].' need revision.' : '')
                        .($checklistStats['failed'] > 0 ? ' '.$checklistStats['failed'].' failed.' : ''),
                ]
                : []),
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
    /**
     * Risk-aware first-pass profile (`executor_risk_aware_profile`). Two rules:
     * the planner may not silently downgrade the router's high_risk decision,
     * and high route risk or a low-confidence plan sends the FIRST pass to the
     * high_risk profile instead of paying a cheap-model failure + audit round
     * to escalate reactively. Devops/none/high_risk profiles pass through.
     *
     * @param  array<string, mixed>  $modelRoute
     * @param  array<string, mixed>  $plan
     */
    public static function firstPassProfileKey(string $planProfileKey, array $modelRoute, array $plan): string
    {
        if (! in_array($planProfileKey, ['default', 'backend', 'frontend_ui'], true)) {
            return $planProfileKey;
        }

        if ((string) ($modelRoute['executor_profile'] ?? '') === 'high_risk') {
            return 'high_risk';
        }

        if ((string) ($modelRoute['risk_level'] ?? '') === 'high') {
            return 'high_risk';
        }

        $confidence = is_numeric($plan['confidence'] ?? null) ? (float) $plan['confidence'] : null;
        if ($confidence !== null && $confidence < 0.50) {
            return 'high_risk';
        }

        return $planProfileKey;
    }

    /**
     * Which executor profile a revision round should use. Cheap profiles escalate
     * to high_risk so a stronger model gets a crack at what the cheaper one
     * couldn't fix. Historically this waited for round 2+ — which never runs at
     * the default max_revision_rounds of 1, so escalation was dead code. With
     * early escalation enabled (`executor_revision_escalation`), the first
     * audit-failed revision already runs on the high_risk profile.
     */
    public static function revisionProfileKey(string $execProfileKey, int $revisionRoundsUsed, bool $escalateEarly): string
    {
        if (! in_array($execProfileKey, ['default', 'backend', 'frontend_ui'], true)) {
            return $execProfileKey;
        }

        return $revisionRoundsUsed >= ($escalateEarly ? 0 : 1) ? 'high_risk' : $execProfileKey;
    }

    /**
     * Whether the main executor step should run the agentic tool-use loop
     * instead of the single-shot executor. Requires executor_mode=agentic AND
     * auto-apply (the loop applies during its run; per-change user approval is
     * incompatible), otherwise the pipeline falls back to single-shot.
     */
    protected function useAgenticExecutor(): bool
    {
        if (strtolower((string) config('bossku.executor_mode', 'single_shot')) !== 'agentic') {
            return false;
        }

        return ! $this->executorApprovals->requireUserApproval();
    }

    protected function applyExecutorCommands(Run $run, array $execResult, ?callable $emit): array
    {
        // Agentic mode already ran commands through the governed runner during
        // its loop; re-running here would double-execute them.
        if (($execResult['_commands_already_run'] ?? false) === true) {
            return $execResult;
        }

        $commandsRun = is_array($execResult['commands_run'] ?? null) ? $execResult['commands_run'] : [];
        if ($commandsRun === []) {
            return $execResult;
        }

        $outcome = $this->projectCommands->runAllowedProjectCommands(
            $commandsRun,
            $this->paths->executionContext(),
        );
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
        // Agentic mode already applied (and diagnosed) its file changes during
        // the loop; skip to avoid re-applying the same writes.
        if (($execResult['_files_already_applied'] ?? false) === true) {
            return $execResult;
        }

        $result = $this->executorFileApplier->applyFromExecutorResult($run->id, $execResult);
        $execResult = $result['execResult'];
        $report = $result;
        unset($report['execResult']);

        // Post-edit diagnostics: run cheap syntax/validity checks on every file
        // we just wrote, so a broken edit is caught here (and folded into the
        // revise loop via mergeApplyReport) instead of shipping a file that
        // does not parse. Mirrors opencode's read-diagnostics-after-edit step.
        if ($report['applied'] !== []) {
            $diagnostics = (new ChangedFileDiagnostics($this->paths))->check($report['applied']);
            $report['diagnostics'] = $diagnostics;
            $failed = array_values(array_filter(
                $diagnostics,
                static fn (array $d): bool => ($d['ok'] ?? true) === false,
            ));
            if ($failed !== [] && $emit !== null) {
                $emit($this->basePayload($run, 'files_diagnostics', [
                    'agent' => 'executor',
                    'status' => 'warning',
                    'summary' => count($failed).' applied file(s) failed diagnostics (syntax/validity).',
                    'artifacts' => ['diagnostics' => $failed],
                ]));
            }
        }

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

        if ($this->settings->executorApplyFeedback()) {
            $execResult = ExecutorEvidenceSupport::mergeApplyReport($execResult, $report);
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
        ?array $lastFinal,
        ?Run $run = null,
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

        $scopePath = $run !== null
            ? app(\App\Services\BosskuAi\MemoryWorktreeScope::class)->pathForRun($run)
            : null;

        try {
            $this->memory->store(
                json_encode($summary, JSON_THROW_ON_ERROR),
                'routing_run',
                ['routing' => true],
                Str::limit($prompt, 200),
                ['bosskuai', 'routing'],
                'orchestrator',
                scopeWorktreePath: $scopePath,
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
            'metadata' => array_merge($run->metadata ?? [], [
                'plan' => $plan,
                'router' => $routerCtx,
                'routing_decision' => $modelRoute,
                'models_resolved' => $modelsResolved,
                'security_audit' => $lastSecurity,
                'final_reviewer' => $lastFinal,
            ]),
        ]);

        try {
            $draft = $this->specialistDrafting->maybeDraftFromRun($run->refresh(), [
                'skill_name' => (string) ($routerCtx['primary_skill']['name'] ?? $modelRoute['skill'] ?? ''),
                'planner_output' => $plan,
                'router_context' => $routerCtx,
                'executor_result' => $executorOutputs[0] ?? [],
                'audit_result' => $lastAudit,
                'memory_signals' => $memPayload,
            ]);
            if ($draft !== null) {
                $this->emit($emit, $this->basePayload($run, 'specialist_agent_candidate_drafted', [
                    'status' => 'success',
                    'agent' => 'orchestrator',
                    'model_role' => 'fast',
                    'from_agent' => 'orchestrator',
                    'to_agent' => $draft->role_slug,
                    'summary' => 'Draft specialist agent created for review: '.$draft->display_name,
                    'message' => 'Approve this specialist before it can affect future runs.',
                    'artifacts' => [
                        'specialist_agent' => $draft->toOfficePayload(),
                    ],
                ]));
            }
        } catch (\Throwable $e) {
            Log::warning('bosskuai.specialist_agent.draft_failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }

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
            $lastFinal,
            $run,
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
        $this->logStep($run, 10000, 'post_memory_eval', null, 'system', null, 'success', null, null, json_encode($evaluation), null, null, null, $evalMs, $evalTok, null, $this->events->metadata(
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

        $this->obsidianSync->sync($run, $modelRoute, $finalOutput);

        if ($run->run_kind === 'child' || $this->runExecution->workspaceForRun((string) $run->getKey()) !== null) {
            \App\Jobs\CleanupRunWorktreeJob::dispatch((string) $run->getKey());
        }

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

        $this->runExecution->clear();

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

            if ($foundCount === 0) {
                try {
                    $assessment = $this->discovery->assessActiveRoot();
                    if (($assessment['appears_empty'] ?? false) && ($assessment['message'] ?? null) !== null) {
                        $this->emit($emit, $this->basePayload($run, 'preflight_empty_project_warning', [
                            'status' => 'warning',
                            'summary' => (string) $assessment['message'],
                            'repo_root' => $assessment['repo_root'] ?? '',
                            'manifest_total' => $assessment['manifest_total'] ?? 0,
                            'probed_files' => count($reads),
                        ]));
                    }
                } catch (\Throwable) {
                    //
                }
            }
        }

        return $reads;
    }

    protected function promptMentionsRepo(string $prompt): bool
    {
        return RepoTaskDetector::requiresRepositoryAccess($prompt);
    }

    /**
     * Returns true when the executor changed at least one source-code file, so the
     * security auditor runs unconditionally (not just when the router flagged it).
     * Covers PHP, JS/TS/Vue, Python, Ruby, Go, Java, C#, Rust, and C/C++.
     *
     * @param  array<string, mixed>  $execResult
     */
    private static function execResultHasCodeChanges(array $execResult): bool
    {
        static $codeExts = ['php', 'js', 'ts', 'tsx', 'jsx', 'vue', 'py', 'rb', 'go', 'java', 'cs', 'rs', 'cpp', 'c', 'h'];
        $files = is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : [];
        foreach ($files as $path) {
            if (! is_string($path)) {
                continue;
            }
            if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $codeExts, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Emit a warning event if the accumulated token count has crossed the soft budget.
     * Only fires once per run (caller passes the previous token count so we can detect
     * the crossing edge rather than emitting on every call).
     */
    protected function maybeEmitBudgetWarning(
        Run $run,
        int $tokenAccBefore,
        int $tokenAccAfter,
        ?callable $emit,
    ): void {
        $budget = (int) config('bossku.token_budget_per_run', 0);
        if ($budget <= 0 || $emit === null) {
            return;
        }
        if ($tokenAccBefore < $budget && $tokenAccAfter >= $budget) {
            $this->emit($emit, $this->basePayload($run, 'token_budget_warning', [
                'status' => 'warning',
                'agent' => 'orchestrator',
                'summary' => 'This run has used ~'.number_format($tokenAccAfter).' estimated tokens (budget: '.number_format($budget).'). Further pipeline stages will use additional tokens.',
                'token_count' => $tokenAccAfter,
                'budget' => $budget,
            ]));
        }
    }

    /**
     * Retrieve semantically-relevant code chunks and convert them to the standard preflight-read
     * format so the Executor sees them in `preflightReads` alongside file-system reads.
     * Deduplicates against paths already present in $existing.
     *
     * @param  list<array<string, mixed>>  $existing
     * @return list<array<string, mixed>>
     */
    protected function mergeSemanticPreflightReads(
        array $existing,
        string $query,
        ?\App\Models\BosskuAi\Project $activeProject,
    ): array {
        try {
            $existingPaths = array_flip(
                array_filter(array_map(static fn ($r) => is_array($r) ? ($r['path'] ?? '') : '', $existing))
            );

            $chunks = $this->codeIndex->retrieve($query, 5, $activeProject?->id);
            foreach ($chunks as $chunk) {
                $path = (string) ($chunk['path'] ?? '');
                if ($path === '' || isset($existingPaths[$path])) {
                    continue;
                }
                $score = isset($chunk['similarity']) ? round((float) $chunk['similarity'], 2) : null;
                $existing[] = [
                    'path' => $path,
                    'found' => true,
                    'preview' => mb_substr((string) ($chunk['content'] ?? ''), 0, 2000),
                    'reason' => 'semantic search'.($score !== null ? " (score=$score)" : ''),
                    'tool_status' => 'success',
                    'start_line' => $chunk['start_line'] ?? null,
                    'end_line' => $chunk['end_line'] ?? null,
                ];
                $existingPaths[$path] = true;
            }
        } catch (\Throwable) {
            // never block the pipeline on index errors
        }

        return $existing;
    }

    /**
     * Fire-and-forget: re-index any files the Executor just wrote so the next run's Planner
     * sees up-to-date code rather than stale chunks. Errors are silently swallowed.
     *
     * @param  array<string, mixed>  $execResult
     */
    protected function maybeReindexAfterWrites(
        array $execResult,
        ?\App\Models\BosskuAi\Project $activeProject,
    ): void {
        try {
            $changed = is_array($execResult['files_changed'] ?? null) ? $execResult['files_changed'] : [];
            if ($changed === [] || $activeProject === null) {
                return;
            }
            $root = $activeProject->container_path ?: (string) config('bossku.repo_root');
            if (! is_dir((string) $root)) {
                return;
            }
            $this->codeIndex->indexDirectory((string) $root, $activeProject->id);
        } catch (\Throwable) {
            // best-effort — never let index errors surface to the pipeline
        }
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

    /**
     * @param  array<string, mixed>  $options
     */
    protected function shouldProvisionWorktree(Run $run, array $options): bool
    {
        if ($this->runExecution->workspaceForRun((string) $run->getKey()) !== null) {
            return false;
        }

        if (($options['use_worktree'] ?? null) === false) {
            return false;
        }

        if (($options['use_worktree'] ?? null) === true) {
            return true;
        }

        if ($run->run_kind === 'child') {
            return true;
        }

        return (bool) config('bossku.worktree_auto_provision', false);
    }

    protected function mustFailOnWorktreeError(string $runKind, array $options): bool
    {
        if (($options['use_worktree'] ?? null) === false) {
            return false;
        }

        if ($runKind === 'child') {
            return true;
        }

        if (($options['use_worktree'] ?? null) === true) {
            return (bool) config('bossku.worktree_fail_closed', true);
        }

        return false;
    }
}
