<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\RunWorkspace;
use App\Services\Project\ProjectPathResolver;
use App\Services\Project\RunExecutionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunExecutionContextTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_resolves_worktree_root_when_workspace_ready(): void
    {
        $repo = sys_get_temp_dir().'/bkctx_'.uniqid();
        File::ensureDirectoryExists($repo.'/wt');
        File::put($repo.'/wt/README.md', 'ok');

        Project::query()->create([
            'name' => 'Ctx',
            'host_path' => $repo,
            'container_path' => $repo,
            'is_active' => true,
        ]);
        config(['bossku.repo_root' => $repo]);

        $run = Run::query()->create(['prompt' => 't', 'status' => 'running']);
        RunWorkspace::query()->create([
            'run_id' => $run->getKey(),
            'branch_name' => 'bossku/test',
            'worktree_path' => $repo.'/wt',
            'status' => 'ready',
        ]);

        $ctx = app(RunExecutionContext::class);
        $ctx->bind((string) $run->getKey());
        $exec = $ctx->executionContext(app(ProjectPathResolver::class));

        $this->assertSame(realpath($repo.'/wt'), $exec->repoRoot);
        $ctx->clear();
    }

    #[Test]
    public function cli_session_resolution_uses_bound_run_worktree(): void
    {
        $repo = sys_get_temp_dir().'/bkcli_'.uniqid();
        File::ensureDirectoryExists($repo.'/wt');
        File::put($repo.'/wt/README.md', 'ok');

        Project::query()->create([
            'name' => 'Cli',
            'host_path' => $repo,
            'container_path' => $repo,
            'is_active' => true,
        ]);
        config(['bossku.repo_root' => $repo]);

        $run = Run::query()->create(['prompt' => 't', 'status' => 'running']);
        RunWorkspace::query()->create([
            'run_id' => $run->getKey(),
            'branch_name' => 'bossku/test',
            'worktree_path' => $repo.'/wt',
            'status' => 'ready',
        ]);

        $ctx = app(RunExecutionContext::class);
        $ctx->bind((string) $run->getKey());
        try {
            $cwd = $ctx->executionContext(app(ProjectPathResolver::class))->repoRoot;
            $this->assertSame(realpath($repo.'/wt'), $cwd);
        } finally {
            $ctx->clear();
        }

        $ctx->bind((string) $run->getKey());
        $fallback = $ctx->executionContext(app(ProjectPathResolver::class))->repoRoot;
        $this->assertSame(realpath($repo.'/wt'), $fallback);
        $ctx->clear();
    }
}
