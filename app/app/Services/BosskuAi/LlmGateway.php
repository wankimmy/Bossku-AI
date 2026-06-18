<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\ModelRoute;
use App\Services\Llm\RoleAliasHelper;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\DTO\LlmResponse;
use App\Services\Llm\ModelRegistry;
use App\Services\Llm\ModelRouter;
use App\Services\Llm\OllamaClient;
use App\Services\Llm\UsageTracker;
use App\Services\OAuth\CodexOAuthService;

class LlmGateway
{
    public function __construct(
        protected OllamaClient $ollama,
        protected RuntimeSettings $settings,
        protected ?ModelRouter $router = null,
        protected ?CodexOAuthService $codexOAuth = null,
        protected ?UsageTracker $usageTracker = null,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, provider: string, input_tokens: int|null, output_tokens: int|null, model_logical: string, model_resolved: string}
     */
    public function chat(
        string $model,
        array $messages,
        ?float $temperature = 0.2,
        ?int $maxTokensAnthropic = null,
        ?string $forceProvider = null,
        string $role = 'coder',
        ?string $runId = null,
        ?string $runStepId = null,
        array $metadata = [],
        bool $jsonMode = false,
    ): array {
        $responseFormat = $jsonMode ? 'json' : null;
        $inferredProvider = $this->resolveProviderForModel($model);
        $effectiveProvider = $forceProvider ?? ($inferredProvider !== 'ollama' ? $inferredProvider : null);

        if ($this->router !== null && ($effectiveProvider !== null || $this->shouldDelegate($role, $forceProvider))) {
            $request = LlmRequest::make($model, $messages, [
                'role'            => $role,
                'temperature'     => $temperature,
                'max_tokens'      => $maxTokensAnthropic,
                'force_provider'  => $effectiveProvider,
                'run_id'          => $runId,
                'run_step_id'     => $runStepId,
                'metadata'        => $metadata,
                'response_format' => $responseFormat,
            ]);

            return $this->router->complete($request)->toArray();
        }

        $logicalModel = trim($model);
        $resolved = $this->resolveAlias($logicalModel);
        $this->assertOllamaModel($resolved);

        $out = $this->ollama->chatWithUsage($resolved, $messages, $temperature, $maxTokensAnthropic, $responseFormat);

        $inputTokens = $out['input_tokens'] !== null ? (int) $out['input_tokens'] : null;
        $outputTokens = $out['output_tokens'] !== null ? (int) $out['output_tokens'] : null;

        $response = new LlmResponse(
            text: $out['text'],
            provider: 'ollama',
            modelLogical: $logicalModel,
            modelResolved: $resolved,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costUsd: ModelRegistry::estimateCost($resolved, $inputTokens ?? 0, $outputTokens ?? 0),
        );

        if ($this->usageTracker !== null) {
            $this->usageTracker->track(
                LlmRequest::make($logicalModel, $messages, [
                    'role' => $role,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokensAnthropic,
                    'run_id' => $runId,
                    'run_step_id' => $runStepId,
                    'metadata' => $metadata,
                ]),
                $response,
            );
        }

        return [
            'text' => $out['text'],
            'provider' => 'ollama',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'model_logical' => $logicalModel,
            'model_resolved' => $resolved,
        ];
    }

    protected function shouldDelegate(string $role, ?string $forceProvider): bool
    {
        if ($forceProvider !== null) {
            return true;
        }

        foreach (RoleAliasHelper::variants($role) as $variant) {
            if (ModelRoute::where('role', $variant)->where('is_active', true)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function resolveProvider(string $model): string
    {
        return $this->resolveProviderForModel($model);
    }

    public function resolveProviderForModel(string $model): string
    {
        $resolved = strtolower(trim($this->resolveAlias($model)));

        if (str_starts_with($resolved, 'claude-')) {
            if (! $this->settings->anthropicConfigured()) {
                throw new \RuntimeException(
                    'Anthropic API key is required for Claude models. Add it in Settings → Models.'
                );
            }

            return 'anthropic';
        }

        if ($this->isCodexModelId($resolved)) {
            if ($this->codexOAuth === null || ! $this->codexOAuth->isConnected()) {
                throw new \RuntimeException(
                    'Codex is not connected. Connect with ChatGPT in Settings → Models.'
                );
            }

            return 'codex';
        }

        $this->assertOllamaModel($resolved);

        return 'ollama';
    }

    protected function isCodexModelId(string $modelId): bool
    {
        if (preg_match('/^(gpt-|o\d|o4-)/', $modelId)) {
            return true;
        }

        foreach (config('bossku_oauth.codex_models', []) as $entry) {
            if (strtolower((string) ($entry['id'] ?? '')) === $modelId) {
                return true;
            }
        }

        return false;
    }

    /** @throws \RuntimeException */
    protected function assertOllamaModel(string $physicalModelId): void
    {
        $m = strtolower(trim($physicalModelId));
        if ($m === '') {
            throw new \RuntimeException('Unknown provider for empty model');
        }

        // Ollama Cloud / library tags typically include ":" (e.g. glm-5.1:cloud).
        if (str_contains($m, ':')) {
            return;
        }

        foreach (config('bossku_models.model_providers.ollama_patterns', []) as $pat) {
            $pat = strtolower(trim((string) $pat));
            if ($pat !== '' && str_contains($m, $pat)) {
                return;
            }
        }

        $aliases = $this->settings->modelAliases();
        foreach ($aliases as $target) {
            if ($m === strtolower(trim($target))) {
                return;
            }
        }

        throw new \RuntimeException('Unknown provider for model '.$physicalModelId);
    }

    public function resolveAlias(string $model): string
    {
        $logical = strtolower(trim($model));

        $aliases = $this->settings->modelAliases();

        foreach ($this->logicalAliasVariants($logical) as $candidate) {
            if (isset($aliases[$candidate])) {
                return trim($aliases[$candidate]);
            }
        }

        return trim($model);
    }

    /** @return list<string> */
    protected function logicalAliasVariants(string $logical): array
    {
        $variants = [$logical];

        // Allow claude-opus-4.7 vs claude-opus-4-7 style keys without mangling other hyphens.
        if (preg_match('/^(.*[._-])(\d+)\.(\d+)$/', $logical, $m)) {
            $variants[] = $m[1].$m[2].'-'.$m[3];
        }
        if (preg_match('/^(.*[._-])(\d+)-(\d+)$/', $logical, $m)) {
            $variants[] = $m[1].$m[2].'.'.$m[3];
        }

        return array_values(array_unique(array_filter($variants, fn ($v): bool => $v !== '')));
    }
}
