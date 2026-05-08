<?php

namespace App\Services\BosskuAi;

use App\Services\Llm\OllamaClient;

class LlmGateway
{
    public function __construct(
        protected OllamaClient $ollama,
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
        ?string $forceProvider = null
    ): array {
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

        /** @var array<string, string> $aliases */
        $aliases = config('bossku_models.aliases', []);
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

        /** @var array<string, string> $aliases */
        $aliases = config('bossku_models.aliases', []);

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
