<?php

namespace App\Support;

/**
 * Standard keys for agent step results after ModelFallbackService calls.
 */
final class LlmTelemetry
{
    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $fallbackOut
     * @return array<string, mixed>
     */
    public static function mergeAgentResult(array $target, array $fallbackOut): array
    {
        return array_merge($target, [
            '_provider_used' => (string) ($fallbackOut['provider_used'] ?? 'ollama'),
            '_model_resolved' => (string) ($fallbackOut['model_resolved'] ?? ''),
            '_input_tokens' => $fallbackOut['input_tokens'] ?? null,
            '_output_tokens' => $fallbackOut['output_tokens'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $agentResult
     */
    public static function resolveStepProvider(array $agentResult): string
    {
        return (string) ($agentResult['_provider_used'] ?? 'ollama');
    }
}
