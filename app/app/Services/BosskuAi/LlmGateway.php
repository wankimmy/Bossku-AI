<?php

namespace App\Services\BosskuAi;

use App\Services\BosskuAi\RuntimeSettings;
use App\Services\Llm\AnthropicClient;
use App\Services\Llm\OllamaClient;
use App\Services\Llm\OpenAiClient;

class LlmGateway
{
    public function __construct(
        protected OpenAiClient $openAi,
        protected AnthropicClient $anthropic,
        protected OllamaClient $ollama,
        protected RuntimeSettings $settings
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, provider: string, input_tokens: int|null, output_tokens: int|null}
     */
    public function chat(
        string $model,
        array $messages,
        ?float $temperature = 0.2,
        ?int $maxTokensAnthropic = null,
        ?string $forceProvider = null
    ): array {
        $provider = $forceProvider ?? $this->resolveProvider($model);

        if ($provider === 'anthropic') {
            $out = $this->anthropic->chatWithUsage(
                $this->toAnthropicMessages($messages),
                $model,
                $maxTokensAnthropic ?? 8192
            );

            return [
                'text' => $out['text'],
                'provider' => 'anthropic',
                'input_tokens' => $out['input_tokens'] !== null ? (int) $out['input_tokens'] : null,
                'output_tokens' => $out['output_tokens'] !== null ? (int) $out['output_tokens'] : null,
            ];
        }

        if ($provider === 'ollama') {
            $out = $this->ollama->chatWithUsage($model, $messages, $temperature);

            return [
                'text' => $out['text'],
                'provider' => 'ollama',
                'input_tokens' => $out['input_tokens'] !== null ? (int) $out['input_tokens'] : null,
                'output_tokens' => $out['output_tokens'] !== null ? (int) $out['output_tokens'] : null,
            ];
        }

        $out = $this->openAi->chatWithUsage($messages, $model, $temperature);

        return [
            'text' => $out['text'],
            'provider' => 'openai',
            'input_tokens' => $out['input_tokens'] !== null ? (int) $out['input_tokens'] : null,
            'output_tokens' => $out['output_tokens'] !== null ? (int) $out['output_tokens'] : null,
        ];
    }

    public function resolveProvider(string $model): string
    {
        $m = strtolower($model);
        if (str_contains($m, 'claude')) {
            return 'anthropic';
        }

        $ollamaModel = strtolower($this->settings->ollamaExecutorModel());
        if ($m === $ollamaModel) {
            return 'ollama';
        }

        foreach (config('bossku_models.model_providers.ollama_patterns', []) as $pat) {
            $pat = strtolower(trim((string) $pat));
            if ($pat !== '' && str_contains($m, $pat)) {
                return 'ollama';
            }
        }

        return 'openai';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected function toAnthropicMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $out[] = [
                'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $m['content'],
            ];
        }

        return $out;
    }
}
