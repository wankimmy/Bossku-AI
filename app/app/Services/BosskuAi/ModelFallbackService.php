<?php

namespace App\Services\BosskuAi;

use App\Services\Runs\RunExistenceGuard;
use App\Support\LlmJsonParser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ModelFallbackService
{
    /** Hard ceiling for the truncation-retry token boost. */
    private const MAX_BOOSTED_TOKENS = 32000;

    public function __construct(
        protected LlmGateway $gateway,
        protected AgentPersonaService $personas,
        protected ?RuntimeSettings $settings = null,
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
            $messages = self::compactMessagesForRetry($messages);
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
                    $attemptMaxTokens = $maxTokensAnthropic;
                    if ($repairInstruction !== null) {
                        // A parse failure almost always means the JSON was truncated because
                        // the prompt left too little room in the context window. Shrink the
                        // oversized context on this retry instead of growing it.
                        if ($lastError === 'invalid_json_parse') {
                            $attemptMessages = self::compactMessagesForRetry($attemptMessages);
                            // Give the SAME model more output room before falling back to a
                            // (often weaker) sibling — truncation is a budget problem, not a
                            // capability problem.
                            if ($maxTokensAnthropic !== null
                                && ($this->settings?->llmTruncationRetryBoost() ?? false)) {
                                $attemptMaxTokens = min($maxTokensAnthropic * 2, self::MAX_BOOSTED_TOKENS);
                            }
                        }
                        $attemptMessages = array_merge($attemptMessages, [['role' => 'user', 'content' => $repairInstruction]]);
                    }

                    $out = $this->gateway->chat(
                        $model,
                        $attemptMessages,
                        $temperature,
                        $attemptMaxTokens,
                        null,
                        $role,
                        $runId,
                        $runStepId,
                        $callMetadata,
                        $structuredOutput,
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

                    // Quality gate: a textually/structurally valid but near-empty response
                    // usually means the model bailed (refusal, quota wall, stub). For roles
                    // where a hollow result is itself the bug — e.g. an auditor returning an
                    // empty verdict — fall through to a complementary model instead of
                    // reporting the hollow result as success. Mirrors Fugu's reward=0 for
                    // unparseable/empty worker output.
                    $degradedFloor = $this->degradedFloorForRole($role);
                    if ($degradedFloor > 0 && $idx < $modelCount - 1) {
                        $effectiveLen = $isValidJson !== null
                            ? strlen((string) json_encode($parsedData))
                            : mb_strlen($text);
                        if ($effectiveLen < $degradedFloor) {
                            throw new \RuntimeException('degraded_response');
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
                    if ($structuredOutput && in_array($lastError, ['empty_response', 'degraded_response'], true) && $idx < $modelCount - 1) {
                        break;
                    }
                    if (! $structuredOutput && $lastError === 'degraded_response' && $idx < $modelCount - 1) {
                        break;
                    }
                }
            }
        }

        throw new \RuntimeException('All models failed for role '.$role.': '.($lastError ?? 'unknown'));
    }

    /**
     * Minimum acceptable response size (in chars) for a role before the result is treated
     * as degraded and a complementary model is tried. 0 = gate disabled. Defaults target the
     * review roles, where an empty verdict is a silent failure; overridable via config.
     */
    protected function degradedFloorForRole(string $role): int
    {
        $defaults = [
            'auditor' => 200,
            'security_auditor' => 200,
            'final_reviewer' => 160,
        ];

        /** @var array<string, int> $configured */
        $configured = (array) config('bossku.degraded_response_floor', []);

        return max(0, (int) ($configured[$role] ?? $defaults[$role] ?? 0));
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
            'content' => 'Structured machine-output mode is active. Reply with exactly one JSON object that satisfies the requested schema and NOTHING else: the first character of your reply must be "{" and the last must be "}". Do not emit markdown fences, prose, commentary, or a [BOSSKUAI] indicator header. Any [BOSSKUAI] header shown in the conversation history is for user-facing replies only — never reproduce it here.',
        ];

        return $messages;
    }

    protected static function structuredRepairInstruction(string $error, string $responseText): string
    {
        $preview = $responseText !== '' ? self::sanitizeResponsePreview($responseText) : '(empty)';

        return 'Repair the previous response. It failed structured validation with error: '.$error.'. Return exactly one JSON object only: no markdown fences, no prose, no commentary, and no [BOSSKUAI] header. Previous response preview: '.$preview;
    }

    /**
     * Trim oversized non-system message contents before a structured call so the model has
     * room to emit a complete JSON object. System messages (which carry the schema/rules)
     * are left intact; head and tail of large user payloads are kept so the task and output
     * contract both survive.
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
