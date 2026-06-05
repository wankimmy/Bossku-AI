<?php

namespace Tests\Unit;

use App\Services\Llm\OllamaClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OllamaClientTest extends TestCase
{
    private function fakeChatOk(): void
    {
        Http::fake([
            '*/api/chat' => Http::response([
                'message' => ['content' => '{"status":"success"}'],
                'prompt_eval_count' => 5,
                'eval_count' => 3,
            ], 200),
        ]);
    }

    #[Test]
    public function it_forwards_max_tokens_as_num_predict(): void
    {
        $this->fakeChatOk();

        $client = new OllamaClient('http://127.0.0.1:11434');
        $client->chatWithUsage('kimi-k2.6:cloud', [
            ['role' => 'user', 'content' => 'hi'],
        ], 0.2, 4096);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['options']['num_predict'] ?? null) === 4096
                && ($body['options']['temperature'] ?? null) === 0.2;
        });
    }

    #[Test]
    public function it_omits_num_predict_when_max_tokens_is_null(): void
    {
        $this->fakeChatOk();

        $client = new OllamaClient('http://127.0.0.1:11434');
        $client->chatWithUsage('kimi-k2.6:cloud', [
            ['role' => 'user', 'content' => 'hi'],
        ], 0.2);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ! array_key_exists('num_predict', $body['options'] ?? []);
        });
    }

    #[Test]
    public function it_sends_keep_alive_to_keep_the_model_warm(): void
    {
        config(['bossku.ollama_keep_alive' => '30m']);
        $this->fakeChatOk();

        $client = new OllamaClient('http://127.0.0.1:11434');
        $client->chatWithUsage('kimi-k2.6:cloud', [['role' => 'user', 'content' => 'hi']], 0.2);

        Http::assertSent(fn ($request) => ($request->data()['keep_alive'] ?? null) === '30m');
    }

    #[Test]
    public function it_omits_keep_alive_when_configured_empty(): void
    {
        config(['bossku.ollama_keep_alive' => '']);
        $this->fakeChatOk();

        $client = new OllamaClient('http://127.0.0.1:11434');
        $client->chatWithUsage('kimi-k2.6:cloud', [['role' => 'user', 'content' => 'hi']], 0.2);

        Http::assertSent(fn ($request) => ! array_key_exists('keep_alive', $request->data()));
    }

    #[Test]
    public function it_sends_num_ctx_only_when_configured(): void
    {
        config(['bossku.ollama_num_ctx' => 16384]);
        $this->fakeChatOk();

        $client = new OllamaClient('http://127.0.0.1:11434');
        $client->chatWithUsage('llama3.2', [['role' => 'user', 'content' => 'hi']], 0.2);

        Http::assertSent(fn ($request) => ($request->data()['options']['num_ctx'] ?? null) === 16384);
    }

    #[Test]
    public function it_sanitizes_invalid_utf8_in_message_content(): void
    {
        $this->fakeChatOk();

        // A lone 0xFF byte is not valid UTF-8 and would otherwise make json_encode emit
        // a malformed body that Ollama Cloud rejects with an invalid_json_parse error.
        $dirty = "valid text \xFF more text";
        $this->assertFalse(mb_check_encoding($dirty, 'UTF-8'));

        $client = new OllamaClient('http://127.0.0.1:11434');
        $client->chatWithUsage('kimi-k2.6:cloud', [
            ['role' => 'user', 'content' => $dirty],
        ], 0.2);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $sent = $body['messages'][0]['content'] ?? null;

            return is_string($sent)
                && mb_check_encoding($sent, 'UTF-8')
                && str_contains($sent, 'valid text')
                && str_contains($sent, 'more text');
        });
    }
}
