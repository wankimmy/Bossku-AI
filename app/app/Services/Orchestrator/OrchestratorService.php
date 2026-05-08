<?php

namespace App\Services\Orchestrator;

use App\Models\BosskuAi\Memory;
use App\Models\BosskuAi\MemoryRunLink;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunStep;
use App\Models\BosskuAi\Skill;
use App\Services\BosskuAi\BosskuResponseIndicator;
use App\Services\BosskuAi\ContextBudgetGuard;
use App\Services\BosskuAi\MemoryService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\BosskuAi\PromptRouteClassifier;
use App\Services\BosskuAi\RuntimeSettings;
use App\Services\BosskuAi\SkillRouterService;
use App\Services\Tools\ToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrchestratorService
{
    public function __construct(
        protected MemoryService $memory,
        protected SkillRouterService $router,
        protected PlannerService $planner,
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
        protected ModelRoutingConfig $modelConfig
    ) {}

    /**
     * @param  callable(array<string,mixed>): void|null  $emit
     * @return array<string,mixed>
     */
    public function run(string $prompt, ?callable $emit = null): array
    {
        $tRun = microtime(true);
        $tokenAcc = 0;

        $run = Run::query()->create([
            'prompt' => $prompt,
            'status' => 'running',
            'metadata' => [],
        ]);

        $this->emit($emit, $this->basePayload($run, 'run_started', [
            'status' => 'success',
        ]));

        $t0 = microtime(true);
        $classified = $this->promptRouteClassifier->classify($prompt);
        /** @var array<string, mixed> $modelRoute */
        $modelRoute = $classified['route'];
        $modelsResolved = $classified['models_resolved'];
        $routerMeta = $classified['router_meta'];
        $routerMs = (int) round((microtime(true) - $t0) * 1000);
        $routerJson = json_encode($modelRoute) ?: '';
        $routerTok = $this->estimateTokens($routerJson);

        $this->logStep($run, -2, 'model_router', $modelsResolved['router'] ?? null, $routerMeta['provider'] ?? null, null, 'success', $prompt, $routerJson, $routerJson, null, null, null, $routerMs, $routerTok, null, [
            'routing_decision' => $modelRoute,
            'models_resolved' => $modelsResolved,
            'router_meta' => $routerMeta,
        ]);
        $tokenAcc += $routerTok;

        $this->emit($emit, $this->basePayload($run, 'model_router_done', [
            'status' => 'success',
            'latency_ms' => $routerMs,
            'routing' => $modelRoute,
            'models' => $modelsResolved,
            'router_meta' => $routerMeta,
        ]));

        $memoryMode = (string) ($modelRoute['memory_mode'] ?? 'read_only');
        $memPayload = [];
        $memMs = 0;
        $memTokens = 0;

        if ($memoryMode !== 'none') {
            $t0 = microtime(true);
            $memories = $this->memory->search($prompt, $this->settings->maxMemoryResults());
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
                'memory_used' => $memPayload,
                'latency_ms' => $memMs,
                'token_estimate' => $memTokens,
            ]));
        }

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
        $routerCtx = $this->router->route($prompt, collect([]));
        $routerMs2 = (int) round((microtime(true) - $t0) * 1000);
        $routerTokens2 = $this->estimateTokens(json_encode($routerCtx) ?: '');

        $this->logStep($run, 1, 'skill_router', null, null, null, 'success', null, $prompt, json_encode($routerCtx), null, null, null, $routerMs2, $routerTokens2, null, [
            'memory_used' => $memPayload,
        ]);
        $tokenAcc += $routerTokens2;

        $this->emit($emit, $this->basePayload($run, 'skill_router_done', [
            'status' => 'success',
            'latency_ms' => $routerMs2,
            'token_estimate' => $routerTokens2,
            'input' => $prompt,
            'output' => json_encode($routerCtx),
        ]));

        $this->emit($emit, $this->basePayload($run, 'planner_started', ['status' => 'running']));
        $t0 = microtime(true);
        $plan = $this->planner->plan($prompt, $memPayload, $routerCtx, $modelRoute);
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
        $this->logStep($run, 2, 'planner', $orchModel, null, null, 'success', $prompt, json_encode(['router' => $routerCtx, 'route' => $modelRoute]), json_encode($plan), null, null, null, $planMs, $planTokens, null, null);
        $tokenAcc += $planTokens;

        $modelsResolved['orchestrator'] = $orchModel;

        $this->emit($emit, $this->basePayload($run, 'planner_done', [
            'status' => 'success',
            'latency_ms' => $planMs,
            'token_estimate' => $planTokens,
            'output' => json_encode($plan),
        ]));

        $execProfileKey = (string) ($plan['executor_profile'] ?? $modelRoute['executor_profile'] ?? 'default');
        $plan = $this->budgetGuard->narrowPlan($plan, $execProfileKey);

        if ($workflow === 'orchestrator_only' || ! ($modelRoute['needs_executor'] ?? true)) {
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
                null,
                $emit,
                $tokenAcc,
                $tRun
            );
        }

        $skillName = (string) ($routerCtx['primary_skill']['name'] ?? 'cofounder');
        $step = [
            'id' => 1,
            'title' => (string) ($plan['summary'] ?? 'Execute'),
            'task' => $prompt,
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
            'step_number' => 3,
            'skill' => $skillName,
            'model' => $modelsResolved['executor'] ?? '',
            'provider' => 'routed',
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
            $execProfileKey
        );
        $modelsResolved['executor'] = (string) ($execResult['_executor_model'] ?? $modelsResolved['executor'] ?? '');

        $exTok = $this->estimateTokens(json_encode($execResult) ?: '');
        $this->logStep($run, 3, 'executor', $modelsResolved['executor'], 'routed', $skillName, ($execResult['status'] ?? '') === 'failed' ? 'failed' : 'success', json_encode($step), json_encode($execResult), json_encode($execResult), null, null, null, (int) ($execResult['latency_ms'] ?? 0), $exTok, null, null);
        $tokenAcc += $exTok;

        if (! empty($execResult['tool_request'] ?? null)) {
            $this->tools->invoke($run->id, null, $execResult['tool_request'], $emit);
        }

        $lastAudit = [];
        $lastSecurity = null;
        $lastFinal = null;
        $stepNum = 3;

        $needsAuditor = ($modelRoute['needs_auditor'] ?? true)
            && $this->settings->auditEnabled()
            && (str_contains($workflow, 'auditor'));

        if ($needsAuditor && ($execResult['needs_audit'] ?? true)) {
            $this->emit($emit, $this->basePayload($run, 'auditor_started', ['status' => 'running', 'step_number' => $stepNum]));
            $tA = microtime(true);
            $lastAudit = $this->auditor->auditStep(
                $prompt,
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
            $this->logStep($run, $stepNum + 100, 'auditor', $modelsResolved['auditor'], null, $skillName, $pass ? 'success' : 'failed', json_encode($step), json_encode($execResult), json_encode($lastAudit), null, null, null, $auditMs, $auditTok, null, null);
            $tokenAcc += $auditTok;
            $this->emit($emit, $this->basePayload($run, 'auditor_done', [
                'status' => $pass ? 'success' : 'fail',
                'step_number' => $stepNum,
                'latency_ms' => $auditMs,
                'output' => json_encode($lastAudit),
            ]));
        }

        if (($modelRoute['needs_security_auditor'] ?? false)) {
            $this->emit($emit, $this->basePayload($run, 'security_auditor_started', ['status' => 'running']));
            $tS = microtime(true);
            $lastSecurity = $this->securityAuditor->audit($prompt, $modelRoute, $plan, $execResult);
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
            $this->emit($emit, $this->basePayload($run, 'final_reviewer_started', ['status' => 'running']));
            $tF = microtime(true);
            $lastFinal = $this->finalReviewer->review($prompt, $modelRoute, $lastAudit, $lastSecurity, $execResult);
            $fMs = (int) round((microtime(true) - $tF) * 1000);
            $fTok = $this->estimateTokens(json_encode($lastFinal) ?: '');
            $this->logStep($run, $stepNum + 200, 'final_reviewer', $modelsResolved['final_reviewer'] ?? null, null, $skillName, 'success', null, null, json_encode($lastFinal), null, null, null, $fMs, $fTok, null, null);
            $tokenAcc += $fTok;
            $this->emit($emit, $this->basePayload($run, 'final_reviewer_done', [
                'status' => 'success',
                'latency_ms' => $fMs,
                'output' => json_encode($lastFinal),
            ]));
        }

        $this->emit($emit, $this->basePayload($run, 'executor_step_done', [
            'status' => ($execResult['status'] ?? '') !== 'failed' ? 'success' : 'fail',
            'step_number' => $stepNum,
            'skill' => $skillName,
            'model' => $modelsResolved['executor'],
            'provider' => 'routed',
            'latency_ms' => (int) ($execResult['latency_ms'] ?? 0),
            'output' => $execResult['patch_summary'] ?? '',
        ]));

        $finalOutput = $this->composeUserOutput($lastAudit, $execResult, $lastFinal);
        $indicator = BosskuResponseIndicator::line($modelRoute, array_filter($modelsResolved));
        $finalOutput = BosskuResponseIndicator::prepend($finalOutput, $indicator);

        return $this->completeRun(
            $run,
            $prompt,
            $finalOutput,
            $modelRoute,
            $modelsResolved,
            $memPayload,
            $routerCtx,
            $plan,
            [$execResult],
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

        $this->emit($emit, $this->basePayload($run, 'run_completed', [
            'status' => 'success',
            'total_latency_ms' => $totalMs,
            'output' => $final,
        ]));

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

    /** @param array<string, mixed>|null $lastFinal */
    protected function composeUserOutput(array $lastAudit, array $execResult, ?array $lastFinal): string
    {
        if ($lastFinal !== null && $lastFinal !== []) {
            return trim((string) ($lastFinal['decision'] ?? '')."\n\n".(string) ($lastFinal['reason'] ?? ''));
        }
        if ($lastAudit !== []) {
            return (string) ($lastAudit['final_output'] ?? $execResult['patch_summary'] ?? '');
        }

        return (string) ($execResult['patch_summary'] ?? json_encode($execResult));
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

        $this->logStep($run, 9999, 'final', null, null, null, 'success', $prompt, $finalOutput, $finalOutput, null, null, null, null, null, null, [
            'memory_used' => $memPayload,
        ]);

        $this->emit($emit, $this->basePayload($run, 'run_completed', [
            'status' => 'success',
            'total_latency_ms' => $totalMs,
            'output' => $finalOutput,
        ]));

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
        return array_merge([
            'run_id' => $run->id,
            'type' => $type,
            'rules_used' => [],
            'playbooks_used' => [],
            'checklists_used' => [],
            'memory_used' => [],
        ], $extras);
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
}
