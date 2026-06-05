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
     * @param  string|null  $format  Ollama `format` (e.g. "json") for grammar-constrained
     *                               decoding — forces structured roles to emit valid JSON
     *                               instead of prose / a stray [BOSSKUAI] indicator header.
     * @return array{text: string, input_tokens: int|null, output_tokens: int|null}
     */
    public function chatWithUsage(string $model, array $messages, ?float $temperature = 0.2, ?int $maxTokens = null, ?string $format = null): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/chat';

        $http = Http::timeout(300)->acceptJson();
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $http = $http->withToken($this->apiKey);
        }

        $payload = [
            'model' => $model,
            'messages' => self::sanitizeMessages($messages),
            'stream' => true,
            'options' => self::buildOptions($temperature, $maxTokens),
        ];
        if ($format !== null && $format !== '') {
            $payload['format'] = $format;
        }

        // stream:true → Ollama sends NDJSON chunks; Guzzle stream option buffers at socket
        // level so very large completions don't load into memory as one giant string.
        $res = $http->withOptions(['stream' => true])->post($url, self::withThink(self::withKeepAlive($payload)));

        try {
            $res->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException(
                'Ollama is not reachable at '.$this->baseUrl.'. For Ollama Cloud set OLLAMA_BASE_URL (e.g. https://ollama.com) and OLLAMA_API_KEY. Response: '.$res->body(),
                previous: $e
            );
        }

        return self::parseStreamedNdjson($res->body());
    }

    /**
     * Reassemble an Ollama NDJSON stream into the same shape as a non-streamed response.
     * Each line is a JSON object; content tokens accumulate and the final `done:true` line
     * carries `prompt_eval_count` / `eval_count` (usage stats).
     *
     * Thinking models (kimi, deepseek, glm, qwen reasoning variants) stream their answer in
     * `message.thinking` and may leave `message.content` empty — which the pipeline would
     * otherwise treat as an `empty_response` and waste a fallback call. When content is empty
     * we fall back to the accumulated thinking text; downstream JSON parsing still extracts
     * the embedded object.
     *
     * @return array{text: string, input_tokens: int|null, output_tokens: int|null}
     */
    private static function parseStreamedNdjson(string $rawBody): array
    {
        $content = '';
        $thinking = '';
        $inputTokens = null;
        $outputTokens = null;

        foreach (explode("\n", $rawBody) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            /** @var array<string,mixed>|null $chunk */
            $chunk = json_decode($line, true);
            if (! is_array($chunk)) {
                continue;
            }
            $content .= (string) data_get($chunk, 'message.content', '');
            $thinking .= (string) data_get($chunk, 'message.thinking', data_get($chunk, 'message.reasoning', ''));
            if ($chunk['done'] ?? false) {
                $inputTokens = isset($chunk['prompt_eval_count']) ? (int) $chunk['prompt_eval_count'] : null;
                $outputTokens = isset($chunk['eval_count']) ? (int) $chunk['eval_count'] : null;
            }
        }

        return [
            'text' => trim($content) !== '' ? $content : $thinking,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
        ];
    }

    public function chat(string $model, array $messages, ?float $temperature = 0.2): string
    {
        return $this->chatWithUsage($model, $messages, $temperature)['text'];
    }

    /**
     * Vision-capable chat (Ollama `images` on the last user message).
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  list<string>  $imagesBase64
     */
    public function chatWithImages(
        string $model,
        array $messages,
        array $imagesBase64,
        ?float $temperature = 0.2,
    ): string {
        if ($messages === []) {
            throw new \InvalidArgumentException('messages must not be empty');
        }

        $payloadMessages = $messages;
        $lastIndex = array_key_last($payloadMessages);
        $last = $payloadMessages[$lastIndex];
        if (($last['role'] ?? '') !== 'user') {
            $payloadMessages[] = ['role' => 'user', 'content' => 'Describe the attached image(s).'];
            $lastIndex = array_key_last($payloadMessages);
            $last = $payloadMessages[$lastIndex];
        }

        $payloadMessages[$lastIndex] = array_merge($last, [
            'images' => array_values(array_filter($imagesBase64, fn ($img) => is_string($img) && $img !== '')),
        ]);

        $url = rtrim($this->baseUrl, '/').'/api/chat';

        $http = Http::timeout(300)->acceptJson();
        if ($this->apiKey !== null && $this->apiKey !== '') {
            $http = $http->withToken($this->apiKey);
        }

        $res = $http->post($url, self::withKeepAlive([
            'model' => $model,
            'messages' => self::sanitizeMessages($payloadMessages),
            'stream' => false,
            'options' => self::buildOptions($temperature, null),
        ]));

        try {
            $res->throw();
        }
        catch (RequestException $e) {
            throw new \RuntimeException(
                'Ollama vision chat failed at '.$this->baseUrl.'. Response: '.$res->body(),
                previous: $e
            );
        }

        $j = $res->json();

        return (string) data_get($j, 'message.content', '');
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
            'input' => self::toValidUtf8($text),
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

    /**
     * Build the Ollama `options` block. `num_predict` caps output tokens so long
     * structured JSON replies are not silently truncated (a common cause of
     * downstream `invalid_json_parse`).
     *
     * @return array<string, mixed>
     */
    protected static function buildOptions(?float $temperature, ?int $maxTokens): array
    {
        $options = ['temperature' => $temperature];
        if ($maxTokens !== null && $maxTokens > 0) {
            $options['num_predict'] = $maxTokens;
        }

        $numCtx = config('bossku.ollama_num_ctx');
        if (is_int($numCtx) && $numCtx > 0) {
            $options['num_ctx'] = $numCtx;
        }

        return $options;
    }

    /**
     * Add `keep_alive` to a request payload so local Ollama keeps the model warm between
     * pipeline stages (no cold reloads). Cloud ignores it harmlessly. Skipped when the
     * configured value is an empty string (let Ollama use its own default).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected static function withKeepAlive(array $payload): array
    {
        $keepAlive = config('bossku.ollama_keep_alive', '30m');
        if ($keepAlive === '' || $keepAlive === null) {
            return $payload;
        }
        $payload['keep_alive'] = is_numeric($keepAlive) ? (int) $keepAlive : (string) $keepAlive;

        return $payload;
    }

    /**
     * Optionally set the top-level `think` flag for thinking-capable models. Default (null)
     * omits the param so Ollama keeps its per-model default. Set BOSSKU_OLLAMA_THINK=false to
     * suppress chain-of-thought generation — lower latency and output tokens for roles that
     * only need the final structured answer (e.g. the orchestrator/router).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected static function withThink(array $payload): array
    {
        $think = config('bossku.ollama_think');
        if (is_bool($think)) {
            $payload['think'] = $think;
        }

        return $payload;
    }

    /**
     * Ensure every message `content` is valid UTF-8 before it is JSON-encoded for the
     * request body. Large, mixed context (file dumps, logs, pasted binaries) can carry
     * invalid byte sequences that make `json_encode` emit a malformed body — which
     * Ollama Cloud rejects with an `invalid_json_parse` style error.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    protected static function sanitizeMessages(array $messages): array
    {
        foreach ($messages as $i => $message) {
            if (isset($message['content']) && is_string($message['content'])) {
                $messages[$i]['content'] = self::toValidUtf8($message['content']);
            }
        }

        return $messages;
    }

    protected static function toValidUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        if (function_exists('mb_scrub')) {
            return mb_scrub($value, 'UTF-8');
        }

        $prev = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        mb_substitute_character($prev);

        return $clean;
    }
}
