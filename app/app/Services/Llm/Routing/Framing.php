<?php

namespace App\Services\Llm\Routing;

/**
 * The framing axis: how the request/response is shaped. Ported from opencode's
 * Framing. The framing knows how to build the HTTP body from messages and how
 * to parse the response. The two common framings are OpenAI chat completions
 * (used by Ollama, OpenAI, DeepSeek, Moonshot, etc.) and Anthropic messages.
 *
 * Keeping framing separate from protocol means a new provider that speaks
 * "OpenAI chat" reuses the OpenAI framing without copy-pasting the request
 * builder.
 */
abstract class Framing
{
    /**
     * Build the HTTP request body from the common message + params shape.
     *
     * @param  string  $model
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{max_tokens?: int, temperature?: float}  $params
     * @return array<string, mixed>
     */
    abstract public function requestBody(string $model, array $messages, array $params): array;

    /**
     * Extract the text response from the HTTP body.
     *
     * @param  array<string, mixed>  $body
     */
    abstract public function extractText(array $body): string;

    /**
     * Extract the token usage from the HTTP body.
     *
     * @param  array<string, mixed>  $body
     * @return array{input: int, output: int}
     */
    abstract public function extractUsage(array $body): array;

    /**
     * Extract the resolved model name from the HTTP body (providers may
     * return a different model id than requested).
     *
     * @param  array<string, mixed>  $body
     */
    abstract public function extractModel(array $body): string;

    abstract public function label(): string;
}