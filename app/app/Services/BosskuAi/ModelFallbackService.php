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

        $structuredOutput = $isValidJson !== null;
        if ($structuredOutput) {
            $messages = $this->withStructuredOutputGuard($messages);
        }

        $modelCount = count($models);
        foreach ($models as $idx => $model) {
            $maxAttempts = $structuredOutput ? min($retryCount, 1) : $retryCount;
            $repairInstruction = null;
            for ($attempt = 0; $attempt <= $maxAttempts; $attempt++) {
                $responseText = '';
                try {
                    $callMetadata = array_merge($metadata, [
                        'fallback_model_index' => $idx,
                        'fallback_attempt' => $attempt,
                    ]);
                    if ($failedAttempts !== []) {
                        $callMetadata['prior_failures'] = $failedAttempts;
                    }

                    $attemptMessages = $messages;
                    if ($repairInstruction !== null) {
                        // A parse failure almost always means the JSON was truncated because
                        // the prompt left too little room in the context window. Shrink the
                        // oversized context on this retry instead of growing it.
                        if ($lastError === 'invalid_json_parse') {
                            $attemptMessages = self::compactMessagesForRetry($attemptMessages);
                        }
                        $attemptMessages = array_merge($attemptMessages, [['role' => 'user', 'content' => $repairInstruction]]);
                    }

                    $out = $this->gateway->chat(
                        $model,
                        $attemptMessages,
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
                    $parsedData = null;
                    if ($isValidJson !== null) {
                        $parsed = LlmJsonParser::parseObject($text);
                        if (! $parsed['ok'] || ! is_array($parsed['data'])) {
                            throw new \RuntimeException('invalid_json_parse');
                        }
                        $parsedData = $parsed['data'];
                        if (! $isValidJson($parsedData)) {
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
                        'parsed' => $isValidJson ? $parsedData : null,
                    ];
                } catch (\Throwable $e) {
                    if ($e instanceof QueryException && RunExistenceGuard::isIntegrityViolation($e)) {
                        throw $e;
                    }

                    $lastError = $e->getMessage();
                    $failedAttempts[] = ['model' => $model, 'error' => $lastError];
                    if ($structuredOutput && in_array($lastError, ['invalid_json_parse', 'invalid_json_schema'], true)) {
                        $repairInstruction = self::structuredRepairInstruction($lastError, $responseText);
                    }
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
                    if (in_array($lastError, ['invalid_json_parse', 'invalid_json_schema'], true) && $responseText !== '') {
                        $retryContext['response_length'] = strlen($responseText);
                        $retryContext['response_preview'] = self::sanitizeResponsePreview($responseText);
                    }
                    $this->safeLog('warning', 'bosskuai.llm.retry', $retryContext);
                    if ($structuredOutput && $lastError === 'empty_response' && $idx < $modelCount - 1) {
                        break;
                    }
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

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected function withStructuredOutputGuard(array $messages): array
    {
        $messages[] = [
            'role' => 'system',
            'content' => 'Structured machine-output mode is active. Return exactly one JSON object that satisfies the requested schema. Use no markdown fences, no prose, no commentary, and no [BOSSKUAI] header.',
        ];

        return $messages;
    }

    protected static function structuredRepairInstruction(string $error, string $responseText): string
    {
        $preview = $responseText !== '' ? self::sanitizeResponsePreview($responseText) : '(empty)';

        return 'Repair the previous response. It failed structured validation with error: '.$error.'. Return exactly one JSON object only: no markdown fences, no prose, no commentary, and no [BOSSKUAI] header. Previous response preview: '.$preview;
    }

    /**
     * Trim the middle of oversized non-system message contents so a retry has room to
     * emit a complete JSON object. System messages (which carry the schema/rules) are
     * left intact; head and tail of large user payloads are kept so the task and the
     * output contract both survive.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected static function compactMessagesForRetry(array $messages, int $perMessageCap = 12000): array
    {
        foreach ($messages as $i => $message) {
            if (($message['role'] ?? '') === 'system') {
                continue;
            }
            $content = (string) ($message['content'] ?? '');
            if (mb_strlen($content) <= $perMessageCap) {
                continue;
            }

            $headLen = (int) round($perMessageCap * 0.6);
            $tailLen = $perMessageCap - $headLen;
            $removed = mb_strlen($content) - $perMessageCap;
            $messages[$i]['content'] = mb_substr($content, 0, $headLen)
                ."\n…[".$removed.' chars trimmed for retry to fit the context window]…'."\n"
                .mb_substr($content, -$tailLen);
        }

        return $messages;
    }

}
