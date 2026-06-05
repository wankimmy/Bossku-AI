<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Project\ProjectCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ExecutorCommandsApplyTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function apply_executor_commands_populates_executed_and_git_status(): void
    {
        $mock = $this->createMock(ProjectCommandRunner::class);
        $mock->method('runAllowedProjectCommands')->willReturn([
            'executed' => [[
                'command' => 'git restore app/Foo.php',
                'exit_code' => 0,
                'stdout' => '',
                'stderr' => '',
                'ok' => true,
            ]],
            'post_git_status' => '',
            'ran_restore' => true,
        ]);

        $this->app->instance(ProjectCommandRunner::class, $mock);

        $run = Run::factory()->create(['prompt' => 'restore', 'status' => 'running']);
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'applyExecutorCommands');
        $method->setAccessible(true);

        $result = $method->invoke($service, $run, [
            'commands_run' => [['command' => 'git restore app/Foo.php']],
            'status' => 'success',
        ], null);

        $this->assertSame('git restore app/Foo.php', $result['_commands_executed'][0]['command']);
        $this->assertSame('', $result['git_status_after']);
    }
}
