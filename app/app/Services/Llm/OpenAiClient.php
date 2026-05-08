<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAiClient
{
    public function __construct(
        protected ?string $apiKey = null
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, input_tokens: int|null, output_tokens: int|null}
     */
    public function chatWithUsage(array $messages, string $model, ?float $temperature = 0.2): array
    {
        $key = $this->apiKey ?? config('services.openai.key');
        if (! $key) {
            throw new \RuntimeException('OPENAI_API_KEY not set.');
        }

        $res = Http::withToken($key)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
            ]);

        try {
            $res->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI API error: '.$res->body(), previous: $e);
        }

        $json = $res->json();

        return [
            'text' => (string) data_get($json, 'choices.0.message.content', ''),
            'input_tokens' => data_get($json, 'usage.prompt_tokens'),
            'output_tokens' => data_get($json, 'usage.completion_tokens'),
        ];
    }

    public function chat(array $messages, string $model, ?float $temperature = 0.2): string
    {
        return $this->chatWithUsage($messages, $model, $temperature)['text'];
    }

    /** @return list<float> */
    public function embed(string $text, string $model = 'text-embedding-3-small'): array
    {
        $key = $this->apiKey ?? config('services.openai.key');
        if (! $key) {
            throw new \RuntimeException('OPENAI_API_KEY not set.');
        }

        $res = Http::withToken($key)
            ->timeout(60)
            ->post('https://api.openai.com/v1/embeddings', [
                'model' => $model,
                'input' => $text,
            ]);

        try {
            $res->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenAI embedding error: '.$res->body(), previous: $e);
        }

        $vec = data_get($res->json(), 'data.0.embedding');
        if (! is_array($vec)) {
            return [];
        }

        return array_map('floatval', $vec);
    }
}
