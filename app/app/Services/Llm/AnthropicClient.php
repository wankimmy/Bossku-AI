<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class AnthropicClient
{
    public function __construct(
        protected ?string $apiKey = null
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, input_tokens: int|null, output_tokens: int|null}
     */
    public function chatWithUsage(array $messages, string $model, int $maxTokens = 8192): array
    {
        $key = $this->apiKey ?? config('services.anthropic.key');
        if (! $key) {
            throw new \RuntimeException('ANTHROPIC_API_KEY not set.');
        }

        /** @phpstan-ignore-next-line */
        $res = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ]);

        try {
            $res->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException('Anthropic API error: '.$res->body(), previous: $e);
        }

        $data = $res->json();
        $parts = $data['content'] ?? [];
        $text = '';
        foreach ($parts as $p) {
            if (($p['type'] ?? '') === 'text') {
                $text .= $p['text'] ?? '';
            }
        }

        return [
            'text' => $text,
            'input_tokens' => data_get($data, 'usage.input_tokens'),
            'output_tokens' => data_get($data, 'usage.output_tokens'),
        ];
    }

    public function chat(array $messages, string $model, int $maxTokens = 8192): string
    {
        return $this->chatWithUsage($messages, $model, $maxTokens)['text'];
    }
}
