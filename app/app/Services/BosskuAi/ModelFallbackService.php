<?php

namespace App\Services\BosskuAi;

use App\Services\Runs\RunExistenceGuard;
use App\Support\LlmJsonParser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ModelFallbackService
{
    public function __construct(
        protected LlmGateway $gateway,
        protected AgentPersonaService $personas
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  list<string>  $models  primary first, then fallbacks
     * @param  callable(string): bool  $isValidJson  optional validator for decoded array
     * @return array{text: string, model_used: string, model_resolved: string, provider_used: string, fallback_used: bool, fallback_reason: string|null, input_tokens: int|null, output_tokens: int|null, parsed: mixed}
     */
    public function chatWithFallbacks(
        array $models,
        array $messages,
        float $temperature,
        int $retryCount,
        string $role,
        ?callable $isValidJson = null,
        ?int $maxTokensAnthropic = null,
        ?string $runId = null,
        ?string $runStepId = null,
        array $metadata = [],
    ): array {
        $lastError = null;
        $models = array_values(array_filter($models));
        /** @var list<array{model: string, error: string}> $failedAttempts */
        $failedAttempts = [];

        if ($this->personas->shouldApplyPersona($role)) {
            $messages = $this->personas->applyToMessages($role, $messages);
        }

        foreach ($models as $idx => $model) {
            for ($attempt = 0; $attempt <= $retryCount; $attempt++) {
                $responseText = '';
                try {
                    $callMetadata = array_merge($metadata, [
                        'fallback_model_index' => $idx,
                        'fallback_attempt' => $attempt,
                    ]);
                    if ($failedAttempts !== []) {
                        $callMetadata['prior_failures'] = $failedAttempts;
                    }

                    $out = $this->gateway->chat(
                        $model,
                        $messages,
                        $temperature,
                        $maxTokensAnthropic,
                        null,
                        $role,
                        $runId,
                        $runStepId,
                        $callMetadata,
                    );
                    $text = trim($out['text']);
                    $responseText = $text;
                    $modelResolved = (string) ($out['model_resolved'] ?? trim($model));
                    if ($text === '') {
                        throw new \RuntimeException('empty_response');
                    }
                    if ($isValidJson !== null) {
                        $parsed = LlmJsonParser::parseObject($text);
                        if (! $parsed['ok'] || ! is_array($parsed['data'])) {
                            throw new \RuntimeException('invalid_json_parse');
                        }
                        if (! $isValidJson($parsed['data'])) {
                            throw new \RuntimeException('invalid_json_schema');
                        }
                    }
                    $this->safeLog('info', 'bosskuai.llm.success', [
                        'role' => $role,
                        'model' => $model,
                        'model_resolved' => $modelResolved,
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
                        'model_resolved' => $modelResolved,
                        'provider_used' => $out['provider'],
                        'fallback_used' => $idx > 0,
                        'fallback_reason' => $idx > 0 ? $lastError : null,
                        'input_tokens' => $out['input_tokens'],
                        'output_tokens' => $out['output_tokens'],
                        'parsed' => $isValidJson
                            ? (LlmJsonParser::parseObject($text)['data'] ?? null)
                            : null,
                    ];
                } catch (\Throwable $e) {
                    if ($e instanceof QueryException && RunExistenceGuard::isIntegrityViolation($e)) {
                        throw $e;
                    }

                    $lastError = $e->getMessage();
                    $failedAttempts[] = ['model' => $model, 'error' => $lastError];
                    try {
                        $resolvedPreview = $this->gateway->resolveAlias($model);
                        $previewProvider = $this->gateway->resolveProvider($model);
                    } catch (\Throwable) {
                        $resolvedPreview = '';
                        $previewProvider = '';
                    }
                    $retryContext = [
                        'role' => $role,
                        'model' => $model,
                        'model_resolved' => $resolvedPreview ?: null,
                        'provider_preview' => $previewProvider ?: null,
                        'attempt' => $attempt,
                        'error' => $lastError,
                    ];
                    if ($lastError === 'invalid_json_parse' && $responseText !== '') {
                        $retryContext['response_length'] = strlen($responseText);
                        $retryContext['response_preview'] = self::sanitizeResponsePreview($responseText);
                    }
                    $this->safeLog('warning', 'bosskuai.llm.retry', $retryContext);
                }
            }
        }

        throw new \RuntimeException('All models failed for role '.$role.': '.($lastError ?? 'unknown'));
    }

    protected function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::log($level, $message, $context);
        } catch (\Throwable) {
            // Never block LLM fallback / streaming when handlers throw (Docker log file perms, etc.)
        }
    }

    protected static function sanitizeResponsePreview(string $text, int $maxLen = 240): string
    {
        $preview = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        if (strlen($preview) > $maxLen) {
            $preview = substr($preview, 0, $maxLen).'…';
        }

        return $preview;
    }

}
