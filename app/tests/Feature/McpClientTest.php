<?php

namespace Tests\Feature;

use App\Services\Mcp\McpClient;
use App\Services\Mcp\McpServerRegistry;
use App\Services\Mcp\McpToolBridge;
use App\Services\Mcp\McpTransport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Scripts JSON-RPC responses by method so the client can be driven offline. */
class FakeMcpTransport implements McpTransport
{
    /** @var list<array<string,mixed>> */
    public array $sent = [];

    public bool $closed = false;

    public function request(array $message): array
    {
        $this->sent[] = $message;
        $id = $message['id'] ?? 0;

        return match ($message['method'] ?? '') {
            'initialize' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['serverInfo' => ['name' => 'fake'], 'capabilities' => []]],
            'tools/list' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['tools' => [
                ['name' => 'create_issue', 'description' => 'Create a GitHub issue'],
                ['name' => 'get_file', 'description' => 'Read a repo file'],
            ]]],
            'tools/call' => ['jsonrpc' => '2.0', 'id' => $id, 'result' => ['content' => [['type' => 'text', 'text' => 'issue #42 created']]]],
            default => ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32601, 'message' => 'method not found']],
        };
    }

    public function notify(array $message): void
    {
        $this->sent[] = $message;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

class McpClientTest extends TestCase
{
    #[Test]
    public function client_initializes_lists_and_calls_tools(): void
    {
        $transport = new FakeMcpTransport;
        $client = new McpClient($transport);

        $tools = $client->listTools();
        $this->assertCount(2, $tools);
        $this->assertSame('create_issue', $tools[0]['name']);

        $result = $client->callTool('create_issue', ['title' => 'Bug']);
        $this->assertSame('issue #42 created', $result['content'][0]['text']);

        // Handshake happened once: an initialize request + initialized notification.
        $methods = array_column($transport->sent, 'method');
        $this->assertSame('initialize', $methods[0]);
        $this->assertContains('notifications/initialized', $methods);
        $this->assertSame(1, count(array_filter($methods, fn ($m) => $m === 'initialize')));
    }

    #[Test]
    public function registry_reads_enabled_servers(): void
    {
        config(['mcp.servers' => [
            'github' => ['enabled' => true, 'transport' => 'stdio', 'command' => 'npx'],
            'figma' => ['enabled' => false, 'transport' => 'stdio', 'command' => 'npx'],
        ]]);
        $registry = app(McpServerRegistry::class);

        $this->assertTrue($registry->isEnabled('github'));
        $this->assertFalse($registry->isEnabled('figma'));
        $this->assertSame(['github'], $registry->enabledNames());
    }

    #[Test]
    public function bridge_routes_to_client_for_enabled_server(): void
    {
        config(['mcp.servers' => ['github' => ['enabled' => true, 'transport' => 'stdio', 'command' => 'npx']]]);
        $bridge = app(McpToolBridge::class);
        $transport = new FakeMcpTransport;
        $bridge->setClientFactory(fn () => new McpClient($transport));

        $tools = $bridge->listTools('github');
        $this->assertCount(2, $tools);

        $result = $bridge->callTool('github', 'create_issue', ['title' => 'x']);
        $this->assertSame('issue #42 created', $result['content'][0]['text']);
        $this->assertTrue($transport->closed); // session closed after use
    }

    #[Test]
    public function bridge_rejects_disabled_or_unknown_server(): void
    {
        config(['mcp.servers' => ['figma' => ['enabled' => false, 'transport' => 'stdio', 'command' => 'npx']]]);
        $bridge = app(McpToolBridge::class);

        $this->expectException(\RuntimeException::class);
        $bridge->listTools('figma');
    }
}
