<?php

namespace App\Services\BosskuAi;

class PromptRouteClassifier
{
    public function __construct(
        protected ModelRoutingConfig $config,
        protected RiskRuleEngine $riskEngine,
        protected DeterministicTaskClassifier $heuristic,
        protected ModelFallbackService $fallback,
        protected RuntimeSettings $settings
    ) {}

    /**
     * Returns routing JSON plus resolved models for logging/UI.
     *
     * @return array{route: array<string, mixed>, models_resolved: array<string, string>, router_meta: array<string, mixed>}
     */
    public function classify(string $prompt): array
    {
        $base = $this->heuristic->classify($prompt);
        $detRisk = $this->riskEngine->deterministicRisk($prompt);

        $routerCfg = $this->config->router();
        $primary = (string) ($routerCfg['primary'] ?? 'gpt-4o-mini');
        $fallbacks = $routerCfg['fallback'] ?? [];
        $retry = (int) ($routerCfg['retry_count'] ?? 1);

        $llmRoute = null;
        $routerModelUsed = $primary;
        $routerProvider = '';
        $routerMeta = [
            'fallback_used' => false,
            'fallback_reason' => null,
            'input_tokens' => null,
            'output_tokens' => null,
        ];

        if ($this->settings->routingLlmEnabled() && ($routerCfg['enabled'] ?? true)) {
            try {
                $system = <<<'SYS'
You are BosskuAI task router. Reply ONLY with valid JSON (no markdown) keys:
task_type (one of: question, code_generation, code_edit, bug_fix, refactor, ui_ux, backend, frontend, devops, documentation, test_generation, security, database, payment, authentication, authorization, deployment, seo, marketing, unknown),
risk_level (low|medium|high),
skill (one of: laravel, php, nuxt, vue, react, docker, nginx, mysql, mariadb, postgresql, redis, security, seo, uiux, devops, testing, documentation, marketing, generic),
workflow (one of: direct_answer, writer_only, orchestrator_only, orchestrator_executor, orchestrator_executor_auditor, orchestrator_executor_auditor_security, orchestrator_executor_auditor_security_final_reviewer),
needs_repo_context (boolean),
needs_file_edit (boolean),
needs_test_run (boolean),
needs_executor (boolean),
needs_auditor (boolean),
needs_security_auditor (boolean),
needs_final_reviewer (boolean),
executor_profile (none|default|frontend_ui|backend|devops|high_risk),
memory_mode (none|read_only|write_after_task|read_and_write),
estimated_token_level (low|medium|high|very_high),
reason (string).
SYS;
                $user = json_encode(['prompt' => $prompt], JSON_THROW_ON_ERROR);
                $messages = [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ];
                $models = array_merge([$primary], is_array($fallbacks) ? $fallbacks : []);
                $out = $this->fallback->chatWithFallbacks(
                    $models,
                    $messages,
                    (float) ($routerCfg['temperature'] ?? 0.1),
                    $retry,
                    'router',
                    function (mixed $j): bool {
                        return is_array($j)
                            && isset($j['task_type'], $j['risk_level'], $j['workflow'], $j['skill']);
                    }
                );
                /** @var array<string, mixed> $llmRoute */
                $llmRoute = $out['parsed'];
                $routerModelUsed = $out['model_used'];
                $routerProvider = $out['provider_used'];
                $routerMeta['fallback_used'] = $out['fallback_used'];
                $routerMeta['fallback_reason'] = $out['fallback_reason'];
                $routerMeta['input_tokens'] = $out['input_tokens'];
                $routerMeta['output_tokens'] = $out['output_tokens'];
                $routerMeta['router_model_resolved'] = $out['model_resolved'] ?? null;
            } catch (\Throwable) {
                $llmRoute = null;
            }
        }

        $route = $this->mergeRoutes($base, $llmRoute, $detRisk);
        $route = RepoTaskDetector::enforceExecutorForRepo($route, $prompt);

        $modelsResolved = [
            'router' => $routerModelUsed,
            'orchestrator' => $this->settings->orchestratorModelForRouting(),
            'executor' => (string) ($this->config->executorProfile((string) $route['executor_profile'])['primary'] ?? 'glm-5.1'),
            'auditor' => (string) ($this->config->auditor()['primary'] ?? 'deepseek-v4-pro'),
            'security_auditor' => (string) ($this->config->securityAuditor()['primary'] ?? 'deepseek-v4-pro'),
            'final_reviewer' => (string) ($this->config->finalReviewer()['primary'] ?? 'deepseek-v4-pro'),
            'direct_answer' => (string) ($this->config->directAnswer()['primary'] ?? $primary),
            'writer' => (string) ($this->config->writer()['primary'] ?? 'kimi-k2.6'),
        ];

        $routerMeta['provider'] = $routerProvider;

        return [
            'route' => $route,
            'models_resolved' => $modelsResolved,
            'router_meta' => $routerMeta,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>|null  $llm
     * @param  array{risk: string, reasons: list<string>, upgraded: bool}  $detRisk
     * @return array<string, mixed>
     */
    protected function mergeRoutes(array $base, ?array $llm, array $detRisk): array
    {
        if ($llm === null) {
            $route = $base;
        } else {
            $route = array_merge($base, $llm);
        }

        // Heuristic short paths (smoke test, simple Q&A) must not be overridden by LLM router.
        $shortWorkflow = (string) ($base['workflow'] ?? '');
        if (in_array($shortWorkflow, ['direct_answer', 'writer_only', 'orchestrator_only'], true)) {
            $route['workflow'] = $shortWorkflow;
            $route['needs_executor'] = (bool) ($base['needs_executor'] ?? false);
            $route['needs_auditor'] = (bool) ($base['needs_auditor'] ?? false);
            $route['needs_security_auditor'] = (bool) ($base['needs_security_auditor'] ?? false);
            $route['needs_final_reviewer'] = (bool) ($base['needs_final_reviewer'] ?? false);
            $route['needs_file_edit'] = (bool) ($base['needs_file_edit'] ?? false);
            $route['memory_mode'] = (string) ($base['memory_mode'] ?? 'read_only');
        }

        $llmRisk = (string) ($route['risk_level'] ?? 'low');
        $merged = $this->riskEngine->mergeRisk($llmRisk, $detRisk['risk']);
        $route['risk_level'] = $merged['risk'];

        if ($merged['upgraded_note']) {
            $reason = (string) ($route['reason'] ?? '');
            $route['reason'] = trim($reason.' '.$merged['upgraded_note']);
        }

        return $this->applyRiskPolicy($route);
    }

    /** @param  array<string, mixed>  $route */
    protected function applyRiskPolicy(array $route): array
    {
        $risk = trim((string) ($route['risk_level'] ?? 'low'));

        if ($risk === 'high') {
            $ep = (string) ($route['executor_profile'] ?? 'default');
            $route['executor_profile'] = $ep === 'devops' ? 'devops' : 'high_risk';
            $route['needs_security_auditor'] = true;
            $route['needs_final_reviewer'] = true;
            $route['workflow'] = 'orchestrator_executor_auditor_security_final_reviewer';

            return $route;
        }

        $route['needs_final_reviewer'] = false;

        $tags = [];
        $tt = (string) ($route['task_type'] ?? '');
        if (in_array($tt, ['payment', 'authentication', 'authorization', 'database', 'deployment', 'security'], true)) {
            $tags[] = $tt;
        }

        if (! ($route['needs_security_auditor'] ?? false)) {
            $route['needs_security_auditor'] = $this->riskEngine->needsSecurityAudit($risk, $tags);
        }

        if ($route['needs_security_auditor'] ?? false) {
            if (! in_array((string) ($route['workflow'] ?? ''), ['orchestrator_executor_auditor_security', 'orchestrator_executor_auditor_security_final_reviewer'], true)) {
                $route['workflow'] = 'orchestrator_executor_auditor_security';
            }
        } else {
            $w = (string) ($route['workflow'] ?? 'orchestrator_executor_auditor');
            if (str_contains($w, 'final_reviewer')) {
                $w = 'orchestrator_executor_auditor';
            }
            if (str_contains($w, 'security')) {
                $w = 'orchestrator_executor_auditor';
            }
            if (in_array($w, ['direct_answer', 'writer_only', 'orchestrator_only', 'orchestrator_executor'], true)) {
                // keep short workflows from router/heuristic
            } elseif (! str_starts_with($w, 'orchestrator_')) {
                $w = 'orchestrator_executor_auditor';
            }
            $route['workflow'] = $w;
        }

        return $route;
    }
}
