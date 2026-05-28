<?php

namespace Tests\Unit;

use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\LlmGateway;
use App\Services\BosskuAi\ModelFallbackService;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ModelFallbackServiceTest extends TestCase
{
    #[Test]
    public function fallback_continues_after_first_model_fails_without_needing_logging(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => 'hi'],
        ];

        $gateway = $this->createMock(LlmGateway::class);

        $gateway->expects($this->exactly(2))
            ->method('chat')
            ->willReturnCallback(function (string $model): array {
                if ($model === 'first-fail') {
                    throw new RuntimeException('simulated upstream failure');
                }

                return [
                    'text' => 'done',
                    'provider' => 'ollama',
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'model_logical' => $model,
                    'model_resolved' => 'fallback-winner:cloud',
                ];
            });

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $out = $svc->chatWithFallbacks(
            ['first-fail', 'second-ok'],
            $messages,
            0.1,
            0,
            'test_role',
            null
        );

        $this->assertSame('done', $out['text']);
        $this->assertSame('second-ok', $out['model_used']);
        $this->assertSame('fallback-winner:cloud', $out['model_resolved']);
        $this->assertTrue($out['fallback_used']);
    }

    #[Test]
    public function chat_with_fallbacks_forwards_role_run_id_to_gateway(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => 'hi'],
        ];

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->once())
            ->method('chat')
            ->with(
                'primary-model',
                $messages,
                0.2,
                null,
                null,
                'orchestrator',
                'run-abc',
                null,
                $this->anything(),
            )
            ->willReturn([
                'text' => 'ok',
                'provider' => 'ollama',
                'input_tokens' => 1,
                'output_tokens' => 1,
                'model_logical' => 'primary-model',
                'model_resolved' => 'primary-model:cloud',
            ]);

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $out = $svc->chatWithFallbacks(
            ['primary-model'],
            $messages,
            0.2,
            0,
            'orchestrator',
            null,
            null,
            'run-abc',
        );

        $this->assertSame('ok', $out['text']);
        $this->assertSame('ollama', $out['provider_used']);
    }
}
