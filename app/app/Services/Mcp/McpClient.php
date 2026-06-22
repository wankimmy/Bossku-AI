<?php

namespace App\Services\Mcp;

/**
 * Minimal MCP client: drives the initialize handshake and tools/list,
 * tools/call over a {@see McpTransport}. Protocol logic only — transport I/O is
 * pluggable so it can be unit-tested with a fake transport.
 */
class McpClient
{
    private const PROTOCOL_VERSION = '2024-11-05';

    private int $id = 0;

    private bool $initialized = false;

    public function __construct(private readonly McpTransport $transport) {}

    /**
     * @return array<string, mixed>  server capabilities / info
     */
    public function initialize(): array
    {
        if ($this->initialized) {
            return [];
        }

        $response = $this->transport->request($this->request('initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => new \stdClass],
            'clientInfo' => ['name' => 'bossku-ai', 'version' => '1.0'],
        ]));

        $this->transport->notify(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
        $this->initialized = true;

        return is_array($response['result'] ?? null) ? $response['result'] : [];
    }

    /**
     * @return list<array<string, mixed>>  tool descriptors {name, description, inputSchema}
     */
    public function listTools(): array
    {
        $this->initialize();
        $response = $this->transport->request($this->request('tools/list', []));
        $tools = $response['result']['tools'] ?? [];

        return is_array($tools) ? array_values($tools) : [];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>  {content?, isError?, error?}
     */
    public function callTool(string $name, array $arguments): array
    {
        $this->initialize();
        $response = $this->transport->request($this->request('tools/call', [
            'name' => $name,
            'arguments' => (object) $arguments,
        ]));

        if (isset($response['error'])) {
            return ['isError' => true, 'error' => $response['error']];
        }

        return is_array($response['result'] ?? null) ? $response['result'] : [];
    }

    public function close(): void
    {
        $this->transport->close();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $method, array $params): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => ++$this->id,
            'method' => $method,
            'params' => (object) $params,
        ];
    }
}
