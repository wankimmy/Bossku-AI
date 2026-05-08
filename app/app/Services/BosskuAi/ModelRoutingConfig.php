<?php

namespace App\Services\BosskuAi;

class ModelRoutingConfig
{
    /** @return array<string, mixed> */
    public function router(): array
    {
        return config('bossku_models.router', []);
    }

    /** @return array<string, mixed> */
    public function orchestrator(): array
    {
        return config('bossku_models.orchestrator', []);
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

        return $profiles[$profile] ?? $profiles['default'] ?? [];
    }

    /** @return list<string> */
    public function executorRules(): array
    {
        return config('bossku_models.executor.rules', []);
    }

    /** @return array<string, mixed> */
    public function auditor(): array
    {
        return config('bossku_models.auditor', []);
    }

    /** @return array<string, mixed> */
    public function securityAuditor(): array
    {
        return config('bossku_models.security_auditor', []);
    }

    /** @return array<string, mixed> */
    public function finalReviewer(): array
    {
        return config('bossku_models.final_reviewer', []);
    }

    /** @return array<string, mixed> */
    public function writer(): array
    {
        return config('bossku_models.writer', []);
    }

    /** @return array<string, mixed> */
    public function directAnswer(): array
    {
        return config('bossku_models.direct_answer', []);
    }
}
