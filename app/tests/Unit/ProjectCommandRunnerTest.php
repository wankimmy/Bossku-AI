<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Services\Project\ProjectCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectCommandRunnerTest extends TestCase
{
    use RefreshDatabase;

    private string $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = sys_get_temp_dir().'/bkcmd_'.uniqid();
        File::ensureDirectoryExists($this->repo);
        $this->initGitRepo($this->repo.'/tracked.txt', "line one\n");

        config([
            'bossku.repo_root' => $this->repo,
            'bossku.auto_execute_project_commands' => true,
            'bossku.allow_docker_compose_commands' => false,
        ]);

        Project::query()->create([
            'name' => 'Git command test',
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
    public function it_allows_git_restore_and_updates_working_tree(): void
    {
        File::put($this->repo.'/tracked.txt', "changed\n");

        $runner = app(ProjectCommandRunner::class);
        $outcome = $runner->runAllowedGitCommands([
            ['command' => 'git restore tracked.txt'],
        ]);

        $this->assertCount(1, $outcome['executed']);
        $this->assertTrue($outcome['executed'][0]['ok']);
        $this->assertSame(0, $outcome['executed'][0]['exit_code']);
        $this->assertSame("line one\n", File::get($this->repo.'/tracked.txt'));
        $this->assertTrue($outcome['ran_restore']);
    }

    #[Test]
    public function it_blocks_dangerous_and_non_git_commands(): void
    {
        $runner = app(ProjectCommandRunner::class);

        $this->assertNotNull($runner->validateCommand('rm -rf /'));
        $this->assertNotNull($runner->validateCommand('git reset --hard HEAD'));
        $this->assertNotNull($runner->validateCommand('git restore a.php; git push'));
        $this->assertNotNull($runner->validateCommand('git restore a.php && git clean -fd'));

        $outcome = $runner->runAllowedGitCommands([
            'git reset --hard',
            ['command' => 'npm install'],
        ]);

        $this->assertCount(2, $outcome['executed']);
        $this->assertFalse($outcome['executed'][0]['ok']);
        $this->assertTrue($outcome['executed'][0]['skipped'] ?? false);
        $this->assertFalse($outcome['executed'][1]['ok']);
    }

    #[Test]
    public function it_allows_php_artisan_commands(): void
    {
        if (! is_executable('/usr/bin/php') && ! is_executable('php')) {
            $this->markTestSkipped('php binary not available');
        }

        File::put($this->repo.'/artisan', "#!/usr/bin/env php\n<?php echo 'Laravel';");
        $runner = app(ProjectCommandRunner::class);
        $this->assertNull($runner->validateCommand('php artisan --version', $this->repo));
    }

    #[Test]
    public function it_blocks_docker_compose_when_sock_unavailable(): void
    {
        config(['bossku.allow_docker_compose_commands' => true]);
        $runner = app(ProjectCommandRunner::class);
        if (is_readable('/var/run/docker.sock')) {
            $this->assertNull($runner->validateCommand('docker compose ps', $this->repo));
        } else {
            $this->assertStringContainsString('docker.sock', (string) $runner->validateCommand('docker compose ps', $this->repo));
        }
    }

    #[Test]
    public function it_marks_commands_skipped_when_auto_execute_disabled(): void
    {
        config(['bossku.auto_execute_project_commands' => false]);

        $runner = app(ProjectCommandRunner::class);
        $outcome = $runner->runAllowedGitCommands(['git status']);

        $this->assertCount(1, $outcome['executed']);
        $this->assertFalse($outcome['executed'][0]['ok']);
        $this->assertTrue($outcome['executed'][0]['skipped'] ?? false);
        $this->assertNull($outcome['post_git_status']);
    }

    protected function initGitRepo(string $filePath, string $contents): void
    {
        File::put($filePath, $contents);
        $cwd = dirname($filePath);
        $commands = [
            'git init',
            'git config user.email "test@bossku.local"',
            'git config user.name "Bossku Test"',
            'git add .',
            'git commit -m "initial"',
        ];
        foreach ($commands as $command) {
            $code = 0;
            passthru('cd '.escapeshellarg($cwd).' && '.$command.' 2>&1', $code);
            if ($code !== 0) {
                $this->markTestSkipped('git is required for ProjectCommandRunnerTest: '.$command);
            }
        }
    }
}
