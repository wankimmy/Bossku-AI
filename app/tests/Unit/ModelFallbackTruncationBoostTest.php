<?php

namespace Tests\Unit;

use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\LlmGateway;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\RuntimeSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelFallbackTruncationBoostTest extends TestCase
{
    /**
     * @param  list<?int>  $captured
     */
    private function gatewayTruncatingFirstCall(array &$captured): LlmGateway
    {
        $gateway = $this->createMock(LlmGateway::class);
        $gateway->method('chat')
            ->willReturnCallback(function (
                string $model,
                array $messages,
                ?float $temperature = 0.2,
                ?int $maxTokensAnthropic = null,
            ) use (&$captured): array {
                $captured[] = $maxTokensAnthropic;
                // First call: brace-less prose so LlmJsonParser (which repairs
                // truncated objects) genuinely fails with invalid_json_parse.
                $text = count($captured) === 1
                    ? 'Reply was cut off before any JSON object could be emitted'
                    : '{"status":"success"}';

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
        return $gateway;
    }

    private function settings(bool $boost): RuntimeSettings
    {
        $settings = $this->createMock(RuntimeSettings::class);
        $settings->method('llmTruncationRetryBoost')->willReturn($boost);

        /** @var RuntimeSettings $settings */
        return $settings;
    }

    #[Test]
    public function truncation_retry_doubles_max_tokens_when_boost_enabled(): void
    {
        $captured = [];
        $svc = new ModelFallbackService(
            $this->gatewayTruncatingFirstCall($captured),
            app(AgentPersonaService::class),
            $this->settings(true),
        );

        $out = $svc->chatWithFallbacks(
            ['strong-model'],
            [['role' => 'user', 'content' => 'Return status.']],
            0.1,
            1,
            'executor',
            fn (mixed $j): bool => is_array($j) && isset($j['status']),
            8000,
        );

        $this->assertSame([8000, 16000], $captured);
        $this->assertSame('strong-model', $out['model_used']);
        $this->assertFalse($out['fallback_used']);
    }

    #[Test]
    public function boost_is_capped_at_the_ceiling(): void
    {
        $captured = [];
        $svc = new ModelFallbackService(
            $this->gatewayTruncatingFirstCall($captured),
            app(AgentPersonaService::class),
            $this->settings(true),
        );

        $svc->chatWithFallbacks(
            ['strong-model'],
            [['role' => 'user', 'content' => 'Return status.']],
            0.1,
            1,
            'executor',
            fn (mixed $j): bool => is_array($j) && isset($j['status']),
            30000,
        );

        $this->assertSame([30000, 32000], $captured);
    }

    #[Test]
    public function no_boost_without_the_flag_or_without_a_numeric_cap(): void
    {
        $captured = [];
        $svc = new ModelFallbackService(
            $this->gatewayTruncatingFirstCall($captured),
            app(AgentPersonaService::class),
            $this->settings(false),
        );
        $svc->chatWithFallbacks(
            ['strong-model'],
            [['role' => 'user', 'content' => 'Return status.']],
            0.1,
            1,
            'executor',
            fn (mixed $j): bool => is_array($j) && isset($j['status']),
            8000,
        );
        $this->assertSame([8000, 8000], $captured);

        $captured = [];
        $svc = new ModelFallbackService(
            $this->gatewayTruncatingFirstCall($captured),
            app(AgentPersonaService::class),
            $this->settings(true),
        );
        $svc->chatWithFallbacks(
            ['strong-model'],
            [['role' => 'user', 'content' => 'Return status.']],
            0.1,
            1,
            'executor',
            fn (mixed $j): bool => is_array($j) && isset($j['status']),
            null,
        );
        $this->assertSame([null, null], $captured);
    }
}
