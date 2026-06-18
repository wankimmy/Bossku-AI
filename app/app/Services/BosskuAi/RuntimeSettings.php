<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Setting;
use Illuminate\Support\Facades\Crypt;

class RuntimeSettings
{
    /** @var array<string, string>|null */
    protected ?array $aliasesCache = null;

    public function getInt(string $key, int $default): int
    {
        $raw = Setting::getValue($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        return (int) $raw;
    }

    public function getBool(string $key, bool $default): bool
    {
        $raw = Setting::getValue($key);
        if ($raw === null || $raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOL);
    }

    public function getString(string $key, string $default): string
    {
        $v = Setting::getValue($key);

        return ($v !== null && $v !== '') ? $v : $default;
    }

    public function plannerProvider(): string
    {
        return 'ollama';
    }

    public function plannerModel(): string
    {
        return $this->reasoningModel();
    }

    public function auditorProvider(): string
    {
        return 'ollama';
    }

    public function auditorModel(): string
    {
        return $this->getString('auditor_model', (string) config('bossku_models.defaults.auditor', 'deepseek-v4-pro'));
    }

    public function securityAuditorModel(): string
    {
        return $this->getString('security_auditor_model', $this->reviewModel());
    }

    public function finalReviewerModel(): string
    {
        return $this->getString('final_reviewer_model', $this->reasoningModel());
    }

    public function writerModel(): string
    {
        return $this->getString('writer_model', $this->reasoningModel());
    }

    public function directAnswerModel(): string
    {
        return $this->getString('direct_answer_model', $this->routerModel());
    }

    public function routerModel(): string
    {
        return $this->getString('router_model', (string) config('bossku_models.defaults.router', 'kimi-k2.6'));
    }

    public function reasoningModel(): string
    {
        return $this->getString('reasoning_model', $this->getString('planner_model', (string) config('bossku_models.defaults.orchestrator', 'kimi-k2.6')));
    }

    public function codingModel(): string
    {
        return $this->getString('coding_model', $this->getString('executor_model', (string) config('bossku_models.defaults.executor_default', 'qwen3-coder-next')));
    }

    public function reviewModel(): string
    {
        return $this->getString('review_model', $this->auditorModel());
    }

    public function executorProfileModel(string $profile): string
    {
        $key = match ($profile) {
            'frontend_ui' => 'executor_frontend_model',
            'backend' => 'executor_backend_model',
            'devops' => 'executor_devops_model',
            'high_risk' => 'executor_high_risk_model',
            default => 'executor_default_model',
        };

        $default = match ($profile) {
            'devops' => (string) config('bossku_models.defaults.executor_devops', 'glm-5.1'),
            'high_risk' => (string) config('bossku_models.defaults.executor_high_risk', 'deepseek-v4-pro'),
            default => (string) config('bossku_models.defaults.executor_default', 'qwen3-coder-next'),
        };

        return $this->getString($key, $default);
    }

    public function embeddingModel(): string
    {
        return $this->getString('embedding_model', (string) config('bossku_models.defaults.embedding', 'nomic-embed-text'));
    }

    public function ollamaEmbeddingPhysicalModel(): string
    {
        $logical = strtolower(trim($this->embeddingModel()));
        $aliases = $this->modelAliases();

        if (isset($aliases[$logical])) {
            return trim($aliases[$logical]);
        }

        return trim($this->embeddingModel());
    }

    public function memoryHumanizeLogicalModel(): string
    {
        return $this->getString('memory_humanize_model', $this->writerModel());
    }

    public function ollamaBaseUrl(): string
    {
        return $this->getString('ollama_base_url', (string) config('bossku.ollama_base_url', 'https://ollama.com'));
    }

    public function ollamaApiKey(): ?string
    {
        $encrypted = Setting::getValue('ollama_api_key_encrypted');
        if ($encrypted !== null && $encrypted !== '') {
            try {
                return Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                // fall through to env
            }
        }

        $env = config('bossku.ollama_api_key');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function ollamaApiKeyMasked(): ?string
    {
        $key = $this->ollamaApiKey();
        if ($key === null || $key === '') {
            return null;
        }

        if (strlen($key) <= 8) {
            return '••••••••';
        }

        return substr($key, 0, 4).'…'.substr($key, -4);
    }

    public function setOllamaApiKey(?string $key): void
    {
        if ($key === null || $key === '') {
            Setting::setValue('ollama_api_key_encrypted', null);

            return;
        }

        Setting::setValue('ollama_api_key_encrypted', Crypt::encryptString($key));
    }

    public function anthropicApiKey(): ?string
    {
        $encrypted = Setting::getValue('anthropic_api_key_encrypted');
        if ($encrypted !== null && $encrypted !== '') {
            try {
                return Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                //
            }
        }

        $env = env('ANTHROPIC_API_KEY');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function anthropicApiKeyMasked(): ?string
    {
        $key = $this->anthropicApiKey();
        if ($key === null || $key === '') {
            return null;
        }

        if (strlen($key) <= 8) {
            return '••••••••';
        }

        return substr($key, 0, 4).'…'.substr($key, -4);
    }

    public function anthropicConfigured(): bool
    {
        return $this->anthropicApiKey() !== null && $this->anthropicApiKey() !== '';
    }

    public function setAnthropicApiKey(?string $key): void
    {
        if ($key === null || $key === '') {
            Setting::setValue('anthropic_api_key_encrypted', null);

            return;
        }

        Setting::setValue('anthropic_api_key_encrypted', Crypt::encryptString($key));
    }

    public function ollamaExecutorModel(): string
    {
        return $this->codingModel();
    }

    public function orchestratorModelForRouting(): string
    {
        $override = Setting::getValue('orchestrator_model');

        return ($override !== null && $override !== '') ? $override : $this->reasoningModel();
    }

    public function maxMemoryResults(): int
    {
        return $this->getInt('max_memory_results', 5);
    }

    public function auditEnabled(): bool
    {
        return $this->getBool('audit_enabled', true);
    }

    public function maxRevisionRounds(): int
    {
        return max(0, $this->getInt(
            'max_revision_rounds',
            (int) config('bossku.max_revision_rounds', 1),
        ));
    }

    /**
     * Strict executor output gate: reject "success" responses that claim file
     * changes without diff/after content, touch nothing on a write-intent task,
     * or contain placeholder-elided file content. On by default; disable via
     * Settings.
     */
    public function executorStrictValidation(): bool
    {
        return $this->getBool('executor_strict_validation', true);
    }

    /**
     * Fold file auto-apply errors/skips back into the executor result
     * (known_issues + status downgrade) so the auditor sees them. On by
     * default; disable via Settings.
     */
    public function executorApplyFeedback(): bool
    {
        return $this->getBool('executor_apply_feedback', true);
    }

    /**
     * Escalate the first audit-failed revision round to the high_risk model
     * profile instead of waiting for round 2+. On by default; disable via
     * Settings.
     */
    public function executorRevisionEscalation(): bool
    {
        return $this->getBool('executor_revision_escalation', true);
    }

    /**
     * Risk-aware first-pass executor profile: high route risk, a router
     * high_risk decision, or a low-confidence plan run the FIRST executor pass
     * on the high_risk profile instead of escalating reactively. On by
     * default; disable via Settings.
     */
    public function executorRiskAwareProfile(): bool
    {
        return $this->getBool('executor_risk_aware_profile', true);
    }

    /**
     * Deterministic patch pre-check between executor and auditor: missing
     * content, placeholder elisions, conflict markers, and diffs that do not
     * apply dry-run cleanly become an immediate needs_revision verdict without
     * spending an LLM audit call. On by default; disable via Settings.
     */
    public function executorPatchPrecheck(): bool
    {
        return $this->getBool('executor_patch_precheck', true);
    }

    /**
     * On a truncated structured response (invalid JSON parse), retry the same
     * model with doubled max_tokens before moving to a fallback model. On by
     * default; disable via Settings.
     */
    public function llmTruncationRetryBoost(): bool
    {
        return $this->getBool('llm_truncation_retry_boost', true);
    }

    public function maxApprovalReviewRounds(): int
    {
        return max(0, $this->getInt(
            'max_approval_review_rounds',
            (int) config('bossku.max_approval_review_rounds', 3),
        ));
    }

    public function councilPlanReviewEnabled(): bool
    {
        return $this->getBool(
            'council_plan_review_enabled',
            (bool) config('bossku.council_plan_review_enabled', true),
        );
    }

    public function companyStaffEnabled(): bool
    {
        return $this->getBool(
            'company_staff_enabled',
            (bool) config('bossku.company_staff_enabled', true),
        );
    }

    public function staffCouncilEnabled(): bool
    {
        return $this->getBool(
            'staff_council_enabled',
            (bool) config('bossku.staff_council_enabled', true),
        );
    }

    public function staffAutoIssueGenerationEnabled(): bool
    {
        return $this->getBool(
            'staff_auto_issue_generation_enabled',
            (bool) config('bossku.staff_auto_issue_generation_enabled', true),
        );
    }

    public function memoryStorageEnabled(): bool
    {
        return $this->getBool('memory_storage_enabled', true);
    }

    public function memoryOllamaEnabled(): bool
    {
        return $this->getBool('memory_ollama_enabled', (bool) config('bossku.memory_ollama_enabled', true));
    }

    public function learningAutoPromoteEnabled(): bool
    {
        return $this->getBool(
            'learning_auto_promote_enabled',
            (bool) config('bossku.learning_auto_promote_enabled', true),
        );
    }

    public function learningAutoPromoteMinConfidence(): float
    {
        $raw = Setting::getValue('learning_auto_promote_min_confidence');
        if ($raw !== null && $raw !== '') {
            return (float) $raw;
        }

        return (float) config('bossku.learning_auto_promote_min_confidence', 0.85);
    }

    /** @return list<string> */
    public function learningAutoPromoteTypes(): array
    {
        $raw = Setting::getValue('learning_auto_promote_types');
        if ($raw !== null && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }
        }

        /** @var list<string> $defaults */
        $defaults = config('bossku.learning_auto_promote_types', ['pattern', 'preference']);

        return $defaults;
    }

    public function learningBatchSize(): int
    {
        return max(1, $this->getInt(
            'learning_batch_size',
            (int) config('bossku.learning_batch_size', 50),
        ));
    }

    public function routingLlmEnabled(): bool
    {
        return $this->getBool('routing_llm_enabled', (bool) config('bossku_models.defaults.router_llm_enabled', true));
    }

    /** @return 'smart'|'always'|'off' */
    public function orchestratorClarificationMode(): string
    {
        $default = strtolower((string) config('bossku.orchestrator_clarification_mode', 'smart'));
        $mode = strtolower($this->getString('orchestrator_clarification_mode', $default));

        return match ($mode) {
            'off', 'always' => $mode,
            default => 'smart',
        };
    }

    /** @return 'always'|'questions'|'off' */
    public function orchestratorPlanConfirmationMode(): string
    {
        $default = strtolower((string) config('bossku.orchestrator_plan_confirmation_mode', 'always'));
        $mode = strtolower($this->getString('orchestrator_plan_confirmation_mode', $default));

        return match ($mode) {
            'questions', 'off' => $mode,
            default => 'always',
        };
    }

    /** @deprecated Use orchestratorModelForRouting() */
    public function orchestratorModelOverride(): ?string
    {
        $v = Setting::getValue('orchestrator_model');

        return $v !== null && $v !== '' ? $v : null;
    }

    /**
     * Logical model id → Ollama physical id (DB overrides merge on top of config defaults).
     *
     * @return array<string, string>
     */
    public function modelAliases(): array
    {
        if ($this->aliasesCache !== null) {
            return $this->aliasesCache;
        }

        /** @var array<string, string> $defaults */
        $defaults = config('bossku_models.aliases', []);
        $raw = Setting::getValue('model_aliases');
        if ($raw !== null && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $logical => $physical) {
                    if (is_string($logical) && is_string($physical) && $physical !== '') {
                        $defaults[$logical] = $physical;
                    }
                }
            }
        }

        $this->aliasesCache = $defaults;

        return $defaults;
    }

    public function clearAliasesCache(): void
    {
        $this->aliasesCache = null;
    }

    /** @return array<string, string|null> */
    public function allPublic(): array
    {
        return [
            'planner_provider' => $this->plannerProvider(),
            'planner_model' => $this->plannerModel(),
            'reasoning_model' => $this->reasoningModel(),
            'auditor_provider' => $this->auditorProvider(),
            'auditor_model' => $this->auditorModel(),
            'review_model' => $this->reviewModel(),
            'executor_provider' => 'ollama',
            'executor_model' => $this->ollamaExecutorModel(),
            'coding_model' => $this->codingModel(),
            'executor_default_model' => $this->executorProfileModel('default'),
            'executor_frontend_model' => $this->executorProfileModel('frontend_ui'),
            'executor_backend_model' => $this->executorProfileModel('backend'),
            'executor_devops_model' => $this->executorProfileModel('devops'),
            'executor_high_risk_model' => $this->executorProfileModel('high_risk'),
            'security_auditor_model' => $this->securityAuditorModel(),
            'final_reviewer_model' => $this->finalReviewerModel(),
            'writer_model' => $this->writerModel(),
            'direct_answer_model' => $this->directAnswerModel(),
            'router_model' => $this->routerModel(),
            'ollama_base_url' => $this->ollamaBaseUrl(),
            'ollama_api_key_masked' => $this->ollamaApiKeyMasked(),
            'anthropic_api_key_masked' => $this->anthropicApiKeyMasked(),
            'anthropic_configured' => $this->anthropicConfigured() ? '1' : '0',
            'max_memory_results' => (string) $this->maxMemoryResults(),
            'max_revision_rounds' => (string) $this->maxRevisionRounds(),
            'audit_enabled' => $this->auditEnabled() ? '1' : '0',
            'executor_strict_validation' => $this->executorStrictValidation() ? '1' : '0',
            'executor_apply_feedback' => $this->executorApplyFeedback() ? '1' : '0',
            'executor_revision_escalation' => $this->executorRevisionEscalation() ? '1' : '0',
            'executor_risk_aware_profile' => $this->executorRiskAwareProfile() ? '1' : '0',
            'executor_patch_precheck' => $this->executorPatchPrecheck() ? '1' : '0',
            'llm_truncation_retry_boost' => $this->llmTruncationRetryBoost() ? '1' : '0',
            'council_plan_review_enabled' => $this->councilPlanReviewEnabled() ? '1' : '0',
            'company_staff_enabled' => $this->companyStaffEnabled() ? '1' : '0',
            'staff_council_enabled' => $this->staffCouncilEnabled() ? '1' : '0',
            'staff_auto_issue_generation_enabled' => $this->staffAutoIssueGenerationEnabled() ? '1' : '0',
            'memory_storage_enabled' => $this->memoryStorageEnabled() ? '1' : '0',
            'memory_ollama_enabled' => $this->memoryOllamaEnabled() ? '1' : '0',
            'embedding_model' => $this->embeddingModel(),
            'routing_llm_enabled' => $this->routingLlmEnabled() ? '1' : '0',
            'orchestrator_clarification_mode' => $this->orchestratorClarificationMode(),
            'orchestrator_plan_confirmation_mode' => $this->orchestratorPlanConfirmationMode(),
            'orchestrator_model' => $this->orchestratorModelForRouting(),
            'model_aliases' => json_encode($this->modelAliases(), JSON_THROW_ON_ERROR),
            'allowed_cloud_models' => json_encode(config('bossku_models.allowed_cloud_models', []), JSON_THROW_ON_ERROR),
        ];
    }
}
