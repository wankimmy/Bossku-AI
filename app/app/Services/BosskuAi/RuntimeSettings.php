<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\Setting;

class RuntimeSettings
{
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
        return Setting::getValue($key) ?? $default;
    }

    public function plannerProvider(): string
    {
        return 'ollama';
    }

    public function plannerModel(): string
    {
        return $this->getString(
            'planner_model',
            env('PLANNER_MODEL', (string) config('bossku_models.orchestrator.primary', 'kimi-k2.6'))
        );
    }

    public function auditorProvider(): string
    {
        return 'ollama';
    }

    public function auditorModel(): string
    {
        return $this->getString(
            'auditor_model',
            env('AUDITOR_MODEL', (string) config('bossku_models.auditor.primary', 'deepseek-v4-pro'))
        );
    }

    public function embeddingModel(): string
    {
        return $this->getString('embedding_model', (string) config('bossku.ollama_embedding_model', 'nomic-embed-text'));
    }

    /**
     * Embedding model passed to Ollama /api/embed (alias-expanded when applicable).
     */
    public function ollamaEmbeddingPhysicalModel(): string
    {
        $logical = strtolower(trim($this->embeddingModel()));
        /** @var array<string, string> $aliases */
        $aliases = config('bossku_models.aliases', []);

        if (isset($aliases[$logical])) {
            return trim($aliases[$logical]);
        }

        return trim($this->embeddingModel());
    }

    public function memoryHumanizeLogicalModel(): string
    {
        return $this->getString(
            'memory_humanize_model',
            env('BOSSKU_MEMORY_HUMANIZE_MODEL', (string) config('bossku_models.writer.primary', 'kimi-k2.6'))
        );
    }

    public function ollamaBaseUrl(): string
    {
        return $this->getString('ollama_base_url', config('bossku.ollama_base_url'));
    }

    public function ollamaExecutorModel(): string
    {
        return $this->getString('executor_model', config('bossku.ollama_executor_model'));
    }

    public function maxMemoryResults(): int
    {
        return $this->getInt('max_memory_results', (int) env('MAX_MEMORY_RESULTS', 5));
    }

    public function auditEnabled(): bool
    {
        return $this->getBool('audit_enabled', true);
    }

    public function memoryStorageEnabled(): bool
    {
        return $this->getBool('memory_storage_enabled', true);
    }

    public function routingLlmEnabled(): bool
    {
        return $this->getBool('routing_llm_enabled', filter_var(config('bossku_models.router.enabled', true), FILTER_VALIDATE_BOOL));
    }

    public function orchestratorModelOverride(): ?string
    {
        $v = Setting::getValue('orchestrator_model');

        return $v !== null && $v !== '' ? $v : null;
    }

    /** @return array<string, string|null> */
    public function allPublic(): array
    {
        return [
            'planner_provider' => $this->plannerProvider(),
            'planner_model' => $this->plannerModel(),
            'auditor_provider' => $this->auditorProvider(),
            'auditor_model' => $this->auditorModel(),
            'executor_provider' => 'ollama',
            'executor_model' => $this->ollamaExecutorModel(),
            'ollama_base_url' => $this->ollamaBaseUrl(),
            'max_memory_results' => (string) $this->maxMemoryResults(),
            'audit_enabled' => $this->auditEnabled() ? '1' : '0',
            'memory_storage_enabled' => $this->memoryStorageEnabled() ? '1' : '0',
            'embedding_model' => $this->embeddingModel(),
            'routing_llm_enabled' => $this->routingEnabledString(),
            'orchestrator_model' => $this->orchestratorModelOverride() ?? config('bossku_models.orchestrator.primary', $this->plannerModel()),
        ];
    }

    protected function routingEnabledString(): string
    {
        return $this->routingLlmEnabled() ? '1' : '0';
    }
}
