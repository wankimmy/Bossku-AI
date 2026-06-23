<?php

namespace App\Services\Llm\Routing;

/**
 * The OpenAI Chat Completions framing. Used by Ollama, OpenAI, DeepSeek,
 * Moonshot, ZAI, DashScope, OpenRouter, and any /v1/chat/completions endpoint.
 * One framing definition serves all of them — the only thing that differs is
 * the endpoint and auth.
 */
final class OpenAiChatFraming extends Framing
{
    public function requestBody(string $model, array $messages, array $params): array
    {
        $body = ['model' => $model, 'messages' => $messages];
        if (isset($params['max_tokens'])) {
            $body['max_tokens'] = $params['max_tokens'];
        }
        if (isset($params['temperature'])) {
            $body['temperature'] = $params['temperature'];
        }

        return $body;
    }

    public function extractText(array $body): string
    {
        return (string) data_get($body, 'choices.0.message.content', '');
    }

    public function extractUsage(array $body): array
    {
        return [
            'input' => (int) data_get($body, 'usage.prompt_tokens', 0),
            'output' => (int) data_get($body, 'usage.completion_tokens', 0),
        ];
    }

    public function extractModel(array $body): string
    {
        return (string) data_get($body, 'model', '');
    }

    public function label(): string
    {
        return 'openai-chat';
    }
}