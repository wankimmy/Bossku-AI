<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OllamaClient
{
    public function __construct(
        protected string $baseUrl = 'http://127.0.0.1:11434'
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, input_tokens: int|null, output_tokens: int|null}
     */
    public function chatWithUsage(string $model, array $messages, ?float $temperature = 0.2): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/chat';
        $res = Http::timeout(300)->post($url, [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => ['temperature' => $temperature],
        ]);

        try {
            $res->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException(
                'Ollama is not reachable at '.$this->baseUrl.'. Check docker compose service and OLLAMA_BASE_URL. Response: '.$res->body(),
                previous: $e
            );
        }

        // Ollama may omit usage in older versions
        $j = $res->json();

        return [
            'text' => (string) data_get($j, 'message.content', ''),
            'input_tokens' => data_get($j, 'prompt_eval_count'),
            'output_tokens' => data_get($j, 'eval_count'),
        ];
    }

    public function chat(string $model, array $messages, ?float $temperature = 0.2): string
    {
        return $this->chatWithUsage($model, $messages, $temperature)['text'];
    }
}
