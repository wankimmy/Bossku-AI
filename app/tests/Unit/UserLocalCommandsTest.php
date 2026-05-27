<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class UserLocalCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detects_docker_blocked_commands(): void
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'commandsRequiringUserRun');
        $method->setAccessible(true);

        $needs = $method->invoke($service, [
            [
                'command' => 'docker compose ps',
                'ok' => false,
                'skipped' => true,
                'reason' => 'Command blocked: docker compose requires /var/run/docker.sock on the Bossku backend (local dev).',
            ],
            [
                'command' => 'git status --porcelain',
                'ok' => true,
                'stdout' => '',
            ],
        ]);

        $this->assertCount(1, $needs);
        $this->assertSame('docker compose ps', $needs[0]['command']);
    }

    #[Test]
    public function builds_clarification_questions_for_blocked_commands(): void
    {
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'buildUserLocalCommandsClarification');
        $method->setAccessible(true);

        $clarification = $method->invoke($service, ['npm run build:pixel-office', 'docker compose logs backend']);

        $this->assertCount(2, $clarification['questions']);
        $this->assertStringContainsString('npm run build:pixel-office', $clarification['questions'][0]['prompt']);
        $this->assertTrue($clarification['questions'][0]['allow_free_text']);
    }

    #[Test]
    public function should_pause_when_blocked_commands_exist(): void
    {
        $run = Run::factory()->create(['prompt' => 'test', 'status' => 'running']);
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'shouldPauseForUserLocalCommands');
        $method->setAccessible(true);

        $execResult = [
            '_commands_executed' => [[
                'command' => 'docker compose exec backend php artisan test',
                'ok' => false,
                'reason' => 'Command blocked: docker compose requires /var/run/docker.sock',
            ]],
        ];

        $this->assertTrue($method->invoke($service, $run, $execResult));
    }
}
