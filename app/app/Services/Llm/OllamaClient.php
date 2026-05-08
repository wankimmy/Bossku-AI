<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OllamaClient
{
    public function __construct(
        protected string $baseUrl = 'http://127.0.0.1:11434',
        protected ?string $apiKey = null,
    ) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{text: string, input_tokens: int|null, output_tokens: int|null}
     */
    public function chatWithUsage(string $model, array $messages, ?float $temperature = 0.2): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/chat';

        $http = Http::timeout(300)->acceptJson();
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $http = $http->withToken($this->apiKey);
        }

        $res = $http->post($url, [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => ['temperature' => $temperature],
        ]);

        try {
            $res->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException(
                'Ollama is not reachable at '.$this->baseUrl.'. For Ollama Cloud set OLLAMA_BASE_URL (e.g. https://ollama.com) and OLLAMA_API_KEY. Response: '.$res->body(),
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

    /**
     * @return list<float>
     */
    public function embed(string $text, string $model, ?int $dimensions = 1536): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/embed';

        $http = Http::timeout(120)->acceptJson();
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $http = $http->withToken($this->apiKey);
        }

        $payload = [
            'model' => $model,
            'input' => $text,
        ];

        if ($dimensions !== null) {
            $payload['dimensions'] = $dimensions;
        }

        $res = $http->post($url, $payload);

        try {
            $res->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException(
                'Ollama embeddings failed at '.$this->baseUrl.'. For Ollama Cloud set OLLAMA_BASE_URL and OLLAMA_API_KEY. Response: '.$res->body(),
                previous: $e
            );
        }

        $j = $res->json();
        /** @var list<float>|mixed $embedding */
        $embedding = data_get($j, 'embeddings.0', data_get($j, 'embedding'));
        if (! is_array($embedding)) {
            throw new \RuntimeException('Ollama /api/embed returned no embedding array');
        }

        return array_values(array_map(static fn ($v) => (float) $v, $embedding));
    }
}
