<?php

namespace Tests\Feature;

use App\Services\Agents\AgenticToolLoop;
use App\Services\Agents\AgentToolPermissionService;
use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgenticToolLoopTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a loop whose model is a scripted fake: $responder(int $callIndex, array $messages)
     * returns the parsed JSON the model would emit on that turn.
     */
    private function loopWithModel(callable $responder): AgenticToolLoop
    {
        $fake = new class extends ModelFallbackService
        {
            /** @var callable */
            public $responder;

            /** @var list<array<int, array{role: string, content: string}>> */
            public array $calls = [];

            public function __construct() {}

            public function chatWithFallbacks(array $models, array $messages, float $temperature, int $retryCount, string $role, ?callable $isValidJson = null, ?int $maxTokensAnthropic = null, ?string $runId = null, ?string $runStepId = null, array $metadata = []): array
            {
                $index = count($this->calls);
                $this->calls[] = $messages;
                $parsed = ($this->responder)($index, $messages);

                return [
                    'text' => json_encode($parsed) ?: '{}',
                    'parsed' => $parsed,
                    'model_used' => 'fake-model',
                    'model_resolved' => 'fake',
                    'provider_used' => 'fake',
                    'fallback_used' => false,
                    'fallback_reason' => null,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                ];
            }
        };
        $fake->responder = $responder;

        $loop = new AgenticToolLoop(
            $fake,
            app(ToolRegistry::class),
            app(AgentToolPermissionService::class),
            app(ModelRoutingConfig::class),
        );

        // Expose the fake for assertions on what was fed back to the model.
        $this->lastFake = $fake;

        return $loop;
    }

    private object $lastFake;

    #[Test]
    public function completes_when_model_sets_done(): void
    {
        $loop = $this->loopWithModel(fn (int $i) => $i === 0
            ? ['thought' => 'log it', 'tool_calls' => [['tool' => 'log', 'payload' => ['message' => 'hi']]], 'done' => false]
            : ['thought' => 'finished', 'done' => true, 'final' => ['summary' => 'all done']]);

        $result = $loop->run('do a thing', ['models' => ['fake-model']]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, $result['iterations']);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertSame('log', $result['tool_calls'][0]['tool']);
        $this->assertSame('all done', $result['final']['summary']);
    }

    #[Test]
    public function stops_at_max_iterations_when_never_done(): void
    {
        // Vary payload each turn so stuck detection does not fire first.
        $loop = $this->loopWithModel(fn (int $i) => [
            'tool_calls' => [['tool' => 'log', 'payload' => ['message' => 'step '.$i]]],
            'done' => false,
        ]);

        $result = $loop->run('endless', ['models' => ['fake-model'], 'max_iterations' => 3]);

        $this->assertSame('max_iterations', $result['status']);
        $this->assertSame(3, $result['iterations']);
        $this->assertCount(3, $result['tool_calls']);
    }

    #[Test]
    public function detects_stuck_on_repeated_identical_calls(): void
    {
        $loop = $this->loopWithModel(fn (int $i) => [
            'tool_calls' => [['tool' => 'log', 'payload' => ['message' => 'same']]],
            'done' => false,
        ]);

        $result = $loop->run('spin', ['models' => ['fake-model'], 'max_iterations' => 10]);

        $this->assertSame('stuck', $result['status']);
        $this->assertSame(3, $result['iterations']);
    }

    #[Test]
    public function feeds_tool_results_back_to_the_model(): void
    {
        $loop = $this->loopWithModel(fn (int $i) => $i === 0
            ? ['tool_calls' => [['tool' => 'log', 'payload' => ['message' => 'observe me']]], 'done' => false]
            : ['done' => true, 'final' => ['summary' => 'ok']]);

        $loop->run('observe', ['models' => ['fake-model']]);

        // The second model call must include the observation from the first tool call.
        $secondCallMessages = $this->lastFake->calls[1];
        $joined = implode("\n", array_column($secondCallMessages, 'content'));
        $this->assertStringContainsString('Tool results', $joined);
        $this->assertStringContainsString('"status"', $joined);
    }
}
