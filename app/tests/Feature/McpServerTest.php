<?php

namespace Tests\Feature;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Services\Mcp\McpServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = app(McpServer::class);
    }

    #[Test]
    public function initialize_returns_server_info(): void
    {
        $response = $this->server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]);

        $this->assertSame('bossku-ai', $response['result']['serverInfo']['name']);
        $this->assertSame(1, $response['id']);
    }

    #[Test]
    public function notifications_get_no_response(): void
    {
        $this->assertNull($this->server->handle(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
    }

    #[Test]
    public function tools_list_advertises_bossku_tools(): void
    {
        $response = $this->server->handle(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []]);

        $names = array_column($response['result']['tools'], 'name');
        $this->assertContains('bossku_search', $names);
        $this->assertContains('bossku_read', $names);
        $this->assertContains('bossku_goals', $names);
        $this->assertContains('bossku_run', $names);
    }

    #[Test]
    public function tools_call_goals_returns_progress(): void
    {
        $project = Project::query()->create([
            'name' => 'MCP Co', 'host_path' => '/tmp/mcp', 'container_path' => '/tmp/mcp', 'is_active' => true,
        ]);
        Goal::query()->create(['project_id' => $project->id, 'title' => 'Ship MVP', 'progress' => 40, 'status' => 'active']);

        $response = $this->server->handle([
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'bossku_goals', 'arguments' => []],
        ]);

        $text = $response['result']['content'][0]['text'];
        $this->assertStringContainsString('Ship MVP', $text);
        $this->assertStringContainsString('40%', $text);
    }

    #[Test]
    public function unknown_method_returns_error(): void
    {
        $response = $this->server->handle(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'nope']);

        $this->assertSame(-32601, $response['error']['code']);
    }
}
