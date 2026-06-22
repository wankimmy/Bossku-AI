<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Project;
use App\Models\BosskuAi\SpecialistAgent;
use App\Services\Agents\HttpAgentAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HttpAgentAdapterTest extends TestCase
{
    use RefreshDatabase;

    private HttpAgentAdapter $adapter;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = app(HttpAgentAdapter::class);
        $this->project = Project::query()->create([
            'name' => 'BYO Co',
            'host_path' => '/tmp/byo',
            'container_path' => '/tmp/byo',
            'is_active' => true,
        ]);
    }

    private function httpAgent(array $metadata): SpecialistAgent
    {
        return SpecialistAgent::query()->create([
            'project_id' => $this->project->id,
            'role_slug' => 'external-bot',
            'display_name' => 'External Bot',
            'approval_status' => 'approved',
            'runtime_mode' => 'http',
            'metadata' => $metadata,
        ]);
    }

    #[Test]
    public function supports_only_http_agents_with_endpoint(): void
    {
        $this->assertTrue($this->adapter->supports($this->httpAgent(['endpoint' => 'https://bot.example/run'])));

        $noEndpoint = SpecialistAgent::query()->create([
            'project_id' => $this->project->id, 'role_slug' => 'llm-bot', 'display_name' => 'LLM Bot',
            'approval_status' => 'approved', 'runtime_mode' => 'http', 'metadata' => [],
        ]);
        $this->assertFalse($this->adapter->supports($noEndpoint));
    }

    #[Test]
    public function dispatch_posts_task_and_maps_response(): void
    {
        Http::fake([
            'https://bot.example/run' => Http::response([
                'summary' => 'Did the thing',
                'task_strategy' => ['step one', 'step two'],
                'handoff_to_executor' => 'Apply the patch in src/x.php',
            ], 200),
        ]);

        $agent = $this->httpAgent([
            'endpoint' => 'https://bot.example/run',
            'auth_header' => 'Authorization',
            'auth_value' => 'Bearer xyz',
        ]);

        $result = $this->adapter->dispatch($agent, ['user_prompt' => 'do it']);

        $this->assertSame('Did the thing', $result['summary']);
        $this->assertSame(['step one', 'step two'], $result['task_strategy']);
        $this->assertSame('Apply the patch in src/x.php', $result['handoff_to_executor']);
        $this->assertSame('http', $result['_specialist_runtime']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://bot.example/run'
                && $request['agent'] === 'external-bot'
                && $request->hasHeader('Authorization', 'Bearer xyz');
        });
    }

    #[Test]
    public function dispatch_degrades_gracefully_on_http_error(): void
    {
        Http::fake(['https://bot.example/run' => Http::response('nope', 500)]);
        $agent = $this->httpAgent(['endpoint' => 'https://bot.example/run']);

        $result = $this->adapter->dispatch($agent, ['user_prompt' => 'x']);

        $this->assertArrayHasKey('_specialist_error', $result);
        $this->assertStringContainsString('500', $result['_specialist_error']);
        $this->assertNotSame('', $result['handoff_to_executor']);
    }
}
