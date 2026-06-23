<?php

namespace App\Services\BosskuAi;

class ModelRoutingConfig
{
    public function __construct(
        protected RuntimeSettings $settings,
    ) {}

    /** @return array<string, mixed> */
    public function router(): array
    {
        $cfg = config('bossku_models.router', []);
        $cfg['primary'] = $this->settings->routerModel();
        $cfg['enabled'] = $this->settings->routingLlmEnabled();

        return $cfg;
    }

    /** @return array<string, mixed> */
    public function orchestrator(): array
    {
        $cfg = config('bossku_models.orchestrator', []);
        $cfg['primary'] = $this->settings->orchestratorModelForRouting();

        return $cfg;
    }

    /** @return array<string, mixed> */
    public function executorProfile(string $profile): array
    {
        /** @var array<string, array<string, mixed>> $profiles */
        $profiles = config('bossku_models.executor', []);
        unset($profiles['rules']);

        if ($profile === 'none') {
            return ['primary' => '', 'fallback' => [], 'max_context_files' => 0, 'max_tokens' => 0, 'temperature' => 0.2, 'timeout_seconds' => 0, 'retry_count' => 0];
        }

        $cfg = $profiles[$profile] ?? $profiles['default'] ?? [];
        $cfg['primary'] = $this->settings->executorProfileModel($profile);

        return $cfg;
    }

    /** @return list<string> */
    public function executorRules(): array
    {
        return config('bossku_models.executor.rules', []);
    }

    /** @return array<string, mixed> */
    public function auditor(): array
    {
        $cfg = config('bossku_models.auditor', []);
        $cfg['primary'] = $this->settings->auditorModel();

        return $cfg;
    }

    /** @return array<string, mixed> */
    public function securityAuditor(): array
    {
        $cfg = config('bossku_models.security_auditor', []);
        $cfg['primary'] = $this->settings->securityAuditorModel();

        return $cfg;
    }

    /** @return array<string, mixed> */
    public function finalReviewer(): array
    {
        $cfg = config('bossku_models.final_reviewer', []);
        $cfg['primary'] = $this->settings->finalReviewerModel();

        return $cfg;
    }

    /** True when the user explicitly pinned this reviewer role's model in Settings. */
    public function roleModelIsPinned(string $role): bool
    {
        $key = match ($role) {
            'auditor' => 'auditor_model',
            'security_auditor' => 'security_auditor_model',
            'final_reviewer' => 'final_reviewer_model',
            default => null,
        };

        return $key !== null && $this->settings->isExplicit($key);
    }

    /** @return array<string, mixed> */
    public function writer(): array
    {
        $cfg = config('bossku_models.writer', []);
        $cfg['primary'] = $this->settings->writerModel();

        return $cfg;
    }

    /** @return array<string, mixed> */
    public function directAnswer(): array
    {
        $cfg = config('bossku_models.direct_answer', []);
        $cfg['primary'] = $this->settings->directAnswerModel();

        return $cfg;
    }
}
