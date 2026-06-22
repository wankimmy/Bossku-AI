<?php

namespace App\Services\Mcp;

/**
 * Bridges configured external MCP servers to Bossku's tool surface: lists and
 * calls their tools so agents can use GitHub, Figma, etc. through one interface.
 *
 * Each call opens a fresh session (connect → initialize → call → close), which
 * is simple and stateless; the client factory is overridable for testing.
 */
class McpToolBridge
{
    /** @var (callable(array<string, mixed>): McpClient)|null */
    private $clientFactory = null;

    public function __construct(private readonly McpServerRegistry $registry) {}

    /** Override how clients/transports are built (used in tests). */
    public function setClientFactory(callable $factory): void
    {
        $this->clientFactory = $factory;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTools(string $server): array
    {
        $client = $this->client($server);
        try {
            return $client->listTools();
        } finally {
            $client->close();
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $server, string $tool, array $arguments): array
    {
        $client = $this->client($server);
        try {
            return $client->callTool($tool, $arguments);
        } finally {
            $client->close();
        }
    }

    private function client(string $server): McpClient
    {
        $config = $this->registry->get($server);
        if ($config === null) {
            throw new \InvalidArgumentException('Unknown MCP server: '.$server);
        }
        if (! ($config['enabled'] ?? false)) {
            throw new \RuntimeException('MCP server is not enabled: '.$server.' (set its env flag + credential).');
        }

        if ($this->clientFactory !== null) {
            return ($this->clientFactory)($config);
        }

        return new McpClient($this->makeTransport($config));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function makeTransport(array $config): McpTransport
    {
        $transport = (string) ($config['transport'] ?? 'stdio');
        if ($transport !== 'stdio') {
            throw new \RuntimeException('Unsupported MCP transport: '.$transport);
        }

        return new StdioMcpTransport(
            (string) ($config['command'] ?? ''),
            is_array($config['args'] ?? null) ? array_map('strval', $config['args']) : [],
            $this->stringEnv(is_array($config['env'] ?? null) ? $config['env'] : []),
            (int) config('mcp.timeout_seconds', 60),
        );
    }

    /**
     * @param  array<string, mixed>  $env
     * @return array<string, string>
     */
    private function stringEnv(array $env): array
    {
        $out = [];
        foreach ($env as $key => $value) {
            $out[(string) $key] = (string) $value;
        }

        return $out;
    }
}
