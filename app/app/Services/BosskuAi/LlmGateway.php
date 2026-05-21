<?php

namespace App\Services\BosskuAi;

use App\Models\BosskuAi\ModelRoute;
use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\ModelRouter;
use App\Services\Llm\OllamaClient;

class LlmGateway
{
    public function __construct(
        protected OllamaClient $ollama,
        protected RuntimeSettings $settings,
        protected ?ModelRouter $router = null,
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
    ): array {
        if ($this->router !== null && $this->shouldDelegate($role, $forceProvider)) {
            $request = LlmRequest::make($model, $messages, [
                'role'           => $role,
                'temperature'    => $temperature,
                'max_tokens'     => $maxTokensAnthropic,
                'force_provider' => $forceProvider,
                'run_id'         => $runId,
                'run_step_id'    => $runStepId,
            ]);

            return $this->router->complete($request)->toArray();
        }

        $logicalModel = trim($model);
        $resolved = $this->resolveAlias($logicalModel);
        $this->assertOllamaModel($resolved);

        $out = $this->ollama->chatWithUsage($resolved, $messages, $temperature);

        return [
            'text' => $out['text'],
            'provider' => 'ollama',
            'input_tokens' => $out['input_tokens'] !== null ? (int) $out['input_tokens'] : null,
            'output_tokens' => $out['output_tokens'] !== null ? (int) $out['output_tokens'] : null,
            'model_logical' => $logicalModel,
            'model_resolved' => $resolved,
        ];
    }

    protected function shouldDelegate(string $role, ?string $forceProvider): bool
    {
        if ($forceProvider !== null) {
            return true;
        }

        return ModelRoute::where('role', $role)->where('is_active', true)->exists();
    }

    public function resolveProvider(string $model): string
    {
        $resolved = $this->resolveAlias(trim($model));
        $this->assertOllamaModel($resolved);

        return 'ollama';
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
