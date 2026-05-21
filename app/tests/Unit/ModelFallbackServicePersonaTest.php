<?php

namespace Tests\Unit;

use App\Models\BosskuAi\AgentPersona;
use App\Services\BosskuAi\AgentPersonaService;
use App\Services\BosskuAi\LlmGateway;
use App\Services\BosskuAi\ModelFallbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelFallbackServicePersonaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chat_with_fallbacks_applies_persona_to_system_message(): void
    {
        AgentPersona::query()->create([
            'role' => 'executor',
            'display_name' => 'Executor',
            'content' => 'CUSTOM_PERSONA_MARKER',
            'enabled' => true,
        ]);

        $captured = null;
        $gateway = $this->createMock(LlmGateway::class);
        $gateway->method('chat')->willReturnCallback(function (string $model, array $messages) use (&$captured): array {
            $captured = $messages;

            return [
                'text' => '{"status":"success","patch_summary":"ok"}',
                'provider' => 'ollama',
                'input_tokens' => 1,
                'output_tokens' => 1,
                'model_logical' => $model,
                'model_resolved' => $model,
            ];
        });

        $svc = new ModelFallbackService($gateway, app(AgentPersonaService::class));
        $svc->chatWithFallbacks(
            ['test-model'],
            [
                ['role' => 'system', 'content' => 'Builtin executor.'],
                ['role' => 'user', 'content' => '{}'],
            ],
            0.0,
            0,
            'executor',
            function (mixed $j): bool {
                return is_array($j);
            }
        );

        $this->assertNotNull($captured);
        $this->assertStringContainsString('CUSTOM_PERSONA_MARKER', $captured[0]['content']);
        $this->assertStringContainsString('Builtin executor.', $captured[0]['content']);
    }
}
