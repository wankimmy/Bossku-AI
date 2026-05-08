<?php

namespace App\Services\BosskuAi;

use Illuminate\Support\Facades\Log;

class ModelFallbackService
{
    public function __construct(
        protected LlmGateway $gateway
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  list<string>  $models  primary first, then fallbacks
     * @param  callable(string): bool  $isValidJson  optional validator for decoded array
     * @return array{text: string, model_used: string, provider_used: string, fallback_used: bool, fallback_reason: string|null, input_tokens: int|null, output_tokens: int|null, parsed: mixed}
     */
    public function chatWithFallbacks(
        array $models,
        array $messages,
        float $temperature,
        int $retryCount,
        string $role,
        ?callable $isValidJson = null,
        ?int $maxTokensAnthropic = null
    ): array {
        $lastError = null;
        $models = array_values(array_filter($models));

        foreach ($models as $idx => $model) {
            for ($attempt = 0; $attempt <= $retryCount; $attempt++) {
                try {
                    $out = $this->gateway->chat($model, $messages, $temperature, $maxTokensAnthropic);
                    $text = trim($out['text']);
                    if ($text === '') {
                        throw new \RuntimeException('empty_response');
                    }
                    if ($isValidJson !== null) {
                        $decoded = $this->tryJson($text);
                        if ($decoded === null || ! $isValidJson($decoded)) {
                            throw new \RuntimeException('invalid_json_schema');
                        }
                    }
                    Log::info('bosskuai.llm.success', [
                        'role' => $role,
                        'model' => $model,
                        'provider' => $out['provider'],
                        'fallback_used' => $idx > 0,
                        'fallback_model' => $idx > 0 ? $model : null,
                        'fallback_reason' => $idx > 0 ? $lastError : null,
                        'input_tokens' => $out['input_tokens'],
                        'output_tokens' => $out['output_tokens'],
                    ]);

                    return [
                        'text' => $text,
                        'model_used' => $model,
                        'provider_used' => $out['provider'],
                        'fallback_used' => $idx > 0,
                        'fallback_reason' => $idx > 0 ? $lastError : null,
                        'input_tokens' => $out['input_tokens'],
                        'output_tokens' => $out['output_tokens'],
                        'parsed' => $isValidJson ? $this->tryJson($text) : null,
                    ];
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    Log::warning('bosskuai.llm.retry', [
                        'role' => $role,
                        'model' => $model,
                        'attempt' => $attempt,
                        'error' => $lastError,
                    ]);
                }
            }
        }

        throw new \RuntimeException('All models failed for role '.$role.': '.($lastError ?? 'unknown'));
    }

    /** @return mixed */
    protected function tryJson(string $raw): mixed
    {
        $clean = trim($raw);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        try {
            return json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }
}
