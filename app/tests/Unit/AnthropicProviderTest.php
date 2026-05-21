<?php

namespace Tests\Unit;

use App\Services\Llm\DTO\LlmRequest;
use App\Services\Llm\Providers\AnthropicProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnthropicProviderTest extends TestCase
{
    #[Test]
    public function complete_maps_system_messages_and_returns_text(): void
    {
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'model' => 'claude-sonnet-4-5',
                'content' => [['type' => 'text', 'text' => 'Hello from Claude']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
            ], 200),
        ]);

        $provider = new AnthropicProvider('sk-ant-test');
        $response = $provider->complete(new LlmRequest(
            model: 'claude-sonnet-4-5',
            messages: [
                ['role' => 'system', 'content' => 'You are helpful.'],
                ['role' => 'user', 'content' => 'Hi'],
            ],
            maxTokens: 64,
        ));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['system'] ?? '') === 'You are helpful.'
                && count($body['messages'] ?? []) === 1
                && ($body['messages'][0]['role'] ?? '') === 'user';
        });

        $this->assertSame('Hello from Claude', $response->text);
        $this->assertSame('anthropic', $response->provider);
        $this->assertSame(10, $response->inputTokens);
        $this->assertSame(5, $response->outputTokens);
    }
}
