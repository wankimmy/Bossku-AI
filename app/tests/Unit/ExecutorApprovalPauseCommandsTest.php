<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Approval;
use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Services\Orchestrator\OrchestratorService;
use App\Services\Project\ProjectCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class ExecutorApprovalPauseCommandsTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bkcmdpause_'.uniqid();
        File::ensureDirectoryExists($this->repo);
        config([
            'bossku.repo_root' => $this->repo,
            'bossku.require_user_approval_before_apply' => true,
            'bossku.require_user_approval_for_commands' => false,
            'bossku.auto_execute_project_commands' => true,
        ]);

        Project::query()->create([
            'name' => 'Command pause test',
            'host_path' => $this->repo,
            'container_path' => $this->repo,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->repo)) {
            File::deleteDirectory($this->repo);
        }

        parent::tearDown();
    }

    #[Test]
    public function apply_or_pause_runs_commands_without_queueing_approval(): void
    {
        File::put($this->repo.'/keep.txt', "original\n");

        $mock = $this->createMock(ProjectCommandRunner::class);
        $mock->method('runAllowedProjectCommands')->willReturn([
            'executed' => [[
                'command' => 'php artisan test',
                'exit_code' => 0,
                'stdout' => 'OK',
                'stderr' => '',
                'ok' => true,
            ]],
            'post_git_status' => '',
            'ran_restore' => false,
        ]);
        $this->app->instance(ProjectCommandRunner::class, $mock);

        $run = Run::factory()->create(['prompt' => 'test', 'status' => 'running']);
        $service = app(OrchestratorService::class);
        $method = new ReflectionMethod(OrchestratorService::class, 'applyOrPauseForExecutorApprovals');
        $method->setAccessible(true);

        $result = $method->invoke($service, $run, [
            'files_changed' => [[
                'path' => 'keep.txt',
                'change_type' => 'modified',
                'after' => "changed\n",
            ]],
            'commands_run' => [['command' => 'php artisan test']],
            'status' => 'success',
        ], [], null);

        $this->assertTrue($result['awaiting_approvals'] ?? false);
        $this->assertSame("original\n", File::get($this->repo.'/keep.txt'));

        $run->refresh();
        /** @var array<string, mixed> $meta */
        $meta = is_array($run->metadata) ? $run->metadata : [];
        /** @var array<string, mixed> $checkpoint */
        $checkpoint = is_array($meta['checkpoint'] ?? null) ? $meta['checkpoint'] : [];
        /** @var array<string, mixed> $pipeline */
        $pipeline = is_array($checkpoint['pipeline'] ?? null) ? $checkpoint['pipeline'] : [];
        /** @var array<string, mixed> $execResult */
        $execResult = is_array($pipeline['exec_result'] ?? null) ? $pipeline['exec_result'] : [];
        $this->assertSame('php artisan test', $execResult['_commands_executed'][0]['command'] ?? null);

        $this->assertDatabaseHas('bossku_ai_approvals', [
            'run_id' => $run->id,
            'operation_type' => 'file_write',
            'status' => 'pending',
        ]);
        $this->assertSame(0, Approval::query()
            ->where('run_id', $run->id)
            ->where('operation_type', 'terminal_command')
            ->count());
    }
}
