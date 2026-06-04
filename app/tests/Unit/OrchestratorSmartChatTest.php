<?php

namespace Tests\Unit;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\Setting;
use App\Services\BosskuAi\PromptRouteClassifier;
use App\Services\Learning\UserSelfLearningService;
use App\Services\Orchestrator\DirectAnswerService;
use App\Services\Orchestrator\ObsidianSyncService;
use App\Services\Orchestrator\OrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrchestratorSmartChatTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/bossku_smart_chat_'.uniqid();
        File::ensureDirectoryExists($this->root);
    }

    protected function tearDown(): void
    {
        if (isset($this->root) && is_dir($this->root)) {
            File::deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    #[Test]
    public function smoke_chat_prompt_is_classified_without_active_project_context(): void
    {
        File::put($this->root.'/artisan', '');
        File::put($this->root.'/docker-compose.yml', "services:\n  backend:\n    build: .\n  postgres:\n    image: postgres:16\n");

        Project::query()->create([
            'name' => 'Security API Project',
            'host_path' => 'C:\\Users\\Safwan Hakim\\Documents\\Safwan\\SecurityApi',
            'container_path' => $this->root,
            'is_active' => true,
        ]);

        Setting::setValue('memory_storage_enabled', '0');
        Setting::setValue('learning_auto_promote_enabled', '0');

        $route = [
            'task_type' => 'question',
            'audit_mode' => 'standard',
            'risk_level' => 'low',
            'skill' => 'generic',
            'workflow' => 'direct_answer',
            'needs_repo_context' => false,
            'needs_file_edit' => false,
            'needs_test_run' => false,
            'needs_executor' => false,
            'needs_auditor' => false,
            'needs_security_auditor' => false,
            'needs_final_reviewer' => false,
            'executor_profile' => 'none',
            'memory_mode' => 'none',
            'estimated_token_level' => 'low',
            'reason' => 'Smoke prompt.',
        ];

        $this->mock(PromptRouteClassifier::class, function (MockInterface $mock) use ($route): void {
            $mock->shouldReceive('classify')
                ->once()
                ->with('test')
                ->andReturn([
                    'route' => $route,
                    'models_resolved' => [
                        'router' => 'mock-router',
                        'direct_answer' => 'mock-direct',
                    ],
                    'router_meta' => ['provider' => 'mock'],
                ]);
        });

        $this->mock(DirectAnswerService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('answer')
                ->once()
                ->andReturn('BosskuAI is running. Your prompt "test" was received.');
        });
        $this->mock(UserSelfLearningService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('processAfterRun')->once()->andReturn([]);
        });
        $this->mock(ObsidianSyncService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sync')->once();
        });

        $events = [];
        $result = app(OrchestratorService::class)->run('test', function (array $event) use (&$events): void {
            $events[] = $event;
        });

        $this->assertSame('BosskuAI is running. Your prompt "test" was received.', $result['final_output']);
        $this->assertStringNotContainsString('[BOSSKUAI]', $result['final_output']);
        $this->assertSame('direct_answer', $result['routing']['workflow']);

        $completed = collect($events)->firstWhere('type', 'run_completed');
        $this->assertSame('direct_answer', $completed['agent'] ?? null);
        $this->assertSame('direct_answer', $completed['from_agent'] ?? null);
        $this->assertSame('BosskuAI is running. Your prompt "test" was received.', $completed['output'] ?? null);
    }
}
