<?php

namespace App\Services\Mcp;

/**
 * Stdio MCP transport: spawns the server process once and exchanges
 * newline-delimited JSON-RPC messages over its stdin/stdout (the standard MCP
 * stdio framing). One instance == one live session.
 *
 * The protocol logic lives in {@see McpClient}; this class only does process and
 * pipe I/O, so it is exercised by integration rather than unit tests.
 */
class StdioMcpTransport implements McpTransport
{
    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     */
    public function __construct(
        private readonly string $command,
        private readonly array $args = [],
        private readonly array $env = [],
        private readonly int $timeoutSeconds = 60,
    ) {}

    public function request(array $message): array
    {
        $this->ensureStarted();
        $this->write($message);

        $deadline = microtime(true) + $this->timeoutSeconds;
        while (microtime(true) < $deadline) {
            $line = $this->readLine($deadline);
            if ($line === null) {
                break;
            }
            $decoded = json_decode($line, true);
            if (! is_array($decoded)) {
                continue;
            }
            // Skip server-initiated notifications/requests; we want the response
            // to the id we just sent.
            if (isset($decoded['id']) && ($decoded['id'] ?? null) == ($message['id'] ?? null)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('MCP request timed out for method '.($message['method'] ?? 'unknown'));
    }

    public function notify(array $message): void
    {
        $this->ensureStarted();
        $this->write($message);
    }

    public function close(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
        $this->pipes = [];
        if (is_resource($this->process)) {
            @proc_terminate($this->process);
            @proc_close($this->process);
        }
        $this->process = null;
    }

    private function ensureStarted(): void
    {
        if (is_resource($this->process)) {
            return;
        }

        $cmd = array_merge([$this->command], $this->args);
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            $cmd,
            $descriptors,
            $this->pipes,
            null,
            array_merge(getenv() ?: [], $this->env),
        );
        if (! is_resource($process)) {
            throw new \RuntimeException('Failed to start MCP server: '.$this->command);
        }
        $this->process = $process;
        stream_set_blocking($this->pipes[1], false);
    }

    /** @param array<string, mixed> $message */
    private function write(array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode MCP message.');
        }
        fwrite($this->pipes[0], $json."\n");
        fflush($this->pipes[0]);
    }

    private function readLine(float $deadline): ?string
    {
        $buffer = '';
        while (microtime(true) < $deadline) {
            $chunk = fgets($this->pipes[1]);
            if ($chunk === false) {
                usleep(10000);

                continue;
            }
            $buffer .= $chunk;
            if (str_ends_with($buffer, "\n")) {
                return trim($buffer);
            }
        }

        return $buffer !== '' ? trim($buffer) : null;
    }
}
