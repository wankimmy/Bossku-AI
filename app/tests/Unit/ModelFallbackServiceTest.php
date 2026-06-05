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
    public function structured_calls_inject_machine_output_guard(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'Base system.'],
            ['role' => 'user', 'content' => 'Return status.'],
        ];
        $capturedMessages = [];

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (string $model, array $messages) use (&$capturedMessages): array {
                $capturedMessages = $messages;

                return [
                    'text' => '{"status":"success"}',
                    'provider' => 'ollama',
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'model_logical' => $model,
                    'model_resolved' => $model.':cloud',
                ];
            });

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $svc->chatWithFallbacks(
            ['structured-model'],
            $messages,
            0.1,
            0,
            'test_role',
            fn (mixed $j): bool => is_array($j) && isset($j['status'])
        );

        $guard = $capturedMessages[array_key_last($capturedMessages)] ?? [];
        $content = (string) ($guard['content'] ?? '');
        $this->assertSame('system', $guard['role'] ?? null);
        $this->assertStringContainsString('exactly one JSON object', $content);
        $this->assertStringContainsString('markdown fences', $content);
        $this->assertStringContainsString('[BOSSKUAI]', $content);
    }

    #[Test]
    public function structured_calls_request_json_mode_from_the_gateway(): void
    {
        $jsonMode = null;

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (
                string $model,
                array $messages,
                ?float $temperature = 0.2,
                ?int $maxTokensAnthropic = null,
                ?string $forceProvider = null,
                string $role = 'coder',
                ?string $runId = null,
                ?string $runStepId = null,
                array $metadata = [],
                bool $jm = false,
            ) use (&$jsonMode): array {
                $jsonMode = $jm;

                return [
                    'text' => '{"status":"success"}',
                    'provider' => 'ollama',
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'model_logical' => $model,
                    'model_resolved' => $model.':cloud',
                ];
            });

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $svc->chatWithFallbacks(
            ['structured-model'],
            [['role' => 'user', 'content' => 'Return status.']],
            0.1,
            0,
            'test_role',
            fn (mixed $j): bool => is_array($j) && isset($j['status'])
        );

        $this->assertTrue($jsonMode, 'Structured-output calls must request json mode (Ollama format:json).');
    }

    #[Test]
    public function unstructured_calls_do_not_request_json_mode(): void
    {
        $jsonMode = null;

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (
                string $model,
                array $messages,
                ?float $temperature = 0.2,
                ?int $maxTokensAnthropic = null,
                ?string $forceProvider = null,
                string $role = 'coder',
                ?string $runId = null,
                ?string $runStepId = null,
                array $metadata = [],
                bool $jm = false,
            ) use (&$jsonMode): array {
                $jsonMode = $jm;

                return [
                    'text' => 'Free-form prose reply.',
                    'provider' => 'ollama',
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'model_logical' => $model,
                    'model_resolved' => $model.':cloud',
                ];
            });

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $svc->chatWithFallbacks(
            ['prose-model'],
            [['role' => 'user', 'content' => 'Say hi.']],
            0.1,
            0,
            'test_role',
        );

        $this->assertFalse($jsonMode, 'Non-structured calls must not force json mode.');
    }

    #[Test]
    public function structured_invalid_json_retry_uses_repair_instruction(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'JSON only.'],
            ['role' => 'user', 'content' => 'Do the task.'],
        ];
        $calls = [];

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->exactly(2))
            ->method('chat')
            ->willReturnCallback(function (string $model, array $messages) use (&$calls): array {
                $calls[] = $messages;
                $text = count($calls) === 1
                    ? "```text\n[BOSSKUAI]\nSkill: bosskuai-project-understanding\nAgent: executor\nModel Role: researcher\nMemory Used: no\n```\n\nProject summary prose without JSON."
                    : '{"status":"success","patch_summary":"repaired"}';

                return [
                    'text' => $text,
                    'provider' => 'ollama',
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'model_logical' => $model,
                    'model_resolved' => $model.':cloud',
                ];
            });

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $out = $svc->chatWithFallbacks(
            ['qwen3-coder-next'],
            $messages,
            0.1,
            1,
            'test_role',
            fn (mixed $j): bool => is_array($j) && isset($j['status'])
        );

        $this->assertSame('qwen3-coder-next', $out['model_used']);
        $this->assertNotSame($calls[0], $calls[1]);
        $repair = $calls[1][array_key_last($calls[1])] ?? [];
        $this->assertSame('user', $repair['role'] ?? null);
        $this->assertStringContainsString('Repair the previous response', (string) ($repair['content'] ?? ''));
        $this->assertStringContainsString('invalid_json_parse', (string) ($repair['content'] ?? ''));
    }

    #[Test]
    public function invalid_json_retry_compacts_oversized_context(): void
    {
        $bigUserContent = str_repeat('A', 30000);
        $messages = [
            ['role' => 'system', 'content' => 'JSON only.'],
            ['role' => 'user', 'content' => $bigUserContent],
        ];
        $calls = [];

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->exactly(2))
            ->method('chat')
            ->willReturnCallback(function (string $model, array $messages) use (&$calls): array {
                $calls[] = $messages;

                return [
                    'text' => count($calls) === 1
                        ? 'Prose with no JSON object at all.'
                        : '{"status":"success"}',
                    'provider' => 'ollama',
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'model_logical' => $model,
                    'model_resolved' => $model.':cloud',
                ];
            });

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $svc->chatWithFallbacks(
            ['qwen3-coder-next'],
            $messages,
            0.1,
            1,
            'test_role',
            fn (mixed $j): bool => is_array($j) && isset($j['status'])
        );

        // First attempt sends the full oversized context.
        $firstUser = collect($calls[0])->firstWhere('role', 'user')['content'] ?? '';
        $this->assertSame(30000, mb_strlen($firstUser));

        // Retry after invalid_json_parse trims the oversized user content so the model
        // has room to finish a complete JSON object.
        $retryContents = array_column($calls[1], 'content');
        $compacted = collect($retryContents)->first(fn ($c) => str_contains((string) $c, 'trimmed for retry'));
        $this->assertNotNull($compacted, 'Expected the oversized context to be trimmed on retry.');
        $this->assertLessThan(30000, mb_strlen((string) $compacted));
    }

    #[Test]
    public function empty_structured_response_skips_same_model_retry_when_fallback_exists(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'JSON only.'],
            ['role' => 'user', 'content' => 'Do the task.'],
        ];
        $modelsCalled = [];

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->exactly(2))
            ->method('chat')
            ->willReturnCallback(function (string $model) use (&$modelsCalled): array {
                $modelsCalled[] = $model;

                return [
                    'text' => $model === 'empty-primary' ? '' : '{"status":"success"}',
                    'provider' => 'ollama',
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'model_logical' => $model,
                    'model_resolved' => $model.':cloud',
                ];
            });

        /** @var LlmGateway $gateway */
        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $out = $svc->chatWithFallbacks(
            ['empty-primary', 'fallback-ok'],
            $messages,
            0.1,
            1,
            'test_role',
            fn (mixed $j): bool => is_array($j) && isset($j['status'])
        );

        $this->assertSame(['empty-primary', 'fallback-ok'], $modelsCalled);
        $this->assertSame('fallback-ok', $out['model_used']);
    }

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
        $capturedMessages = [];

        $gateway = $this->createMock(LlmGateway::class);
        $gateway->expects($this->once())
            ->method('chat')
            ->with(
                'primary-model',
                $this->callback(function (array $messages) use (&$capturedMessages): bool {
                    $capturedMessages = $messages;

                    return true;
                }),
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
        $this->assertStringNotContainsString('Structured machine-output mode', json_encode($capturedMessages) ?: '');
    }
}
