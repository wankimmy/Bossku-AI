<?php

namespace App\Services\Mcp;

/**
 * Transport for a single MCP session. Implementations carry JSON-RPC messages
 * to/from an MCP server (stdio subprocess, HTTP, or a fake for tests).
 */
interface McpTransport
{
    /**
     * Send a JSON-RPC request and return the decoded response message.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function request(array $message): array;

    /**
     * Send a JSON-RPC notification (no response expected).
     *
     * @param  array<string, mixed>  $message
     */
    public function notify(array $message): void;

    public function close(): void;
}
