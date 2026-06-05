<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Services\Project\ProjectRuntimeHints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectRuntimeHintsTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/bk_hints_'.uniqid();
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_detects_laravel_compose_and_suggests_app_service(): void
    {
        File::put($this->root.'/artisan', '');
        File::put($this->root.'/composer.json', '{"require":{"laravel/framework":"^11"}}');
        File::put($this->root.'/docker-compose.yml', <<<'YAML'
services:
  app:
    build: .
  mysql:
    image: mysql:8
YAML);

        $hints = app(ProjectRuntimeHints::class)->summarize($this->root);

        $this->assertSame('laravel', $hints['framework']);
        $this->assertSame('docker-compose.yml', $hints['compose_file']);
        $this->assertSame(['app', 'mysql'], $hints['compose_services']);
        $this->assertSame('app', $hints['suggested_compose_service']);
        $this->assertContains('docker compose exec app php artisan test', $hints['suggested_commands']);
    }

    #[Test]
    public function it_prefers_non_infra_service_when_app_is_not_first(): void
    {
        File::put($this->root.'/docker-compose.yml', <<<'YAML'
services:
  redis:
    image: redis:7
  web:
    build: .
YAML);

        $hints = app(ProjectRuntimeHints::class)->summarize($this->root);

        $this->assertSame('web', $hints['suggested_compose_service']);
    }

    #[Test]
    public function for_prompt_mentions_compose_service_from_this_repo(): void
    {
        File::put($this->root.'/docker-compose.yml', "services:\n  api:\n    build: .\n");
        File::put($this->root.'/artisan', '');

        $prompt = app(ProjectRuntimeHints::class)->forPrompt($this->root);

        $this->assertStringContainsString('service "api"', $prompt);
        $this->assertStringContainsString('not a hardcoded name', $prompt);
    }

    #[Test]
    public function agent_workspace_context_includes_bossku_toolkit_mode_when_repo_is_toolkit(): void
    {
        $root = sys_get_temp_dir().'/bk_ctx_'.uniqid();
        File::ensureDirectoryExists($root.'/app/app/Services/Orchestrator');
        File::ensureDirectoryExists($root.'/web');
        File::put($root.'/docker-compose.yml', 'services: {}');
        File::put(
            $root.'/app/app/Services/Orchestrator/OrchestratorService.php',
            '<?php namespace App\Services\Orchestrator; class OrchestratorService {}',
        );

        Project::query()->create([
            'name' => 'Bossku-AI',
            'host_path' => $root,
            'container_path' => $root,
            'is_active' => true,
        ]);

        $context = app(\App\Services\Project\ProjectService::class)->agentWorkspaceContext();

        $this->assertStringContainsString('SELF-IMPROVEMENT MODE', $context);
        $this->assertStringContainsString('Bossku-AI orchestrator', $context);

        File::deleteDirectory($root);
    }

    #[Test]
    public function agent_workspace_context_includes_runtime_hints_for_active_project(): void
    {
        Project::query()->create([
            'name' => 'My App',
            'host_path' => $this->root,
            'container_path' => $this->root,
            'is_active' => true,
        ]);

        File::put($this->root.'/docker-compose.yml', "services:\n  worker:\n    build: .\n");
        File::put($this->root.'/artisan', '');

        $context = app(\App\Services\Project\ProjectService::class)->agentWorkspaceContext();

        $this->assertStringContainsString('Active project: "My App"', $context);
        $this->assertStringContainsString('worker', $context);
        $this->assertStringContainsString('never hardcode another repo', $context);
    }
}
