<?php

namespace App\Console\Commands;

use App\Services\Mcp\McpServer;
use Illuminate\Console\Command;

class McpServeCommand extends Command
{
    protected $signature = 'bosskuai:mcp-serve';

    protected $description = 'Run Bossku-AI as an MCP server over stdio (connect from Claude Code, Cursor, etc.).';

    public function handle(McpServer $server): int
    {
        $stdin = fopen('php://stdin', 'r');
        $stdout = fopen('php://stdout', 'w');
        if ($stdin === false || $stdout === false) {
            return self::FAILURE;
        }

        // Newline-delimited JSON-RPC: read one message per line, write one
        // response per line. EOF (client disconnect) ends the loop.
        while (($line = fgets($stdin)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $message = json_decode($line, true);
            if (! is_array($message)) {
                continue;
            }

            try {
                $response = $server->handle($message);
            } catch (\Throwable $e) {
                $response = ['jsonrpc' => '2.0', 'id' => $message['id'] ?? null, 'error' => ['code' => -32603, 'message' => $e->getMessage()]];
            }

            if ($response !== null) {
                fwrite($stdout, (json_encode($response, JSON_UNESCAPED_SLASHES) ?: '{}')."\n");
                fflush($stdout);
            }
        }

        return self::SUCCESS;
    }
}
