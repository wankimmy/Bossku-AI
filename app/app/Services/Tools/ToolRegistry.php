<?php

namespace App\Services\Tools;

use App\Models\BosskuAi\ToolCall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ToolRegistry
{
    /** @var list<string> */
    protected array $allow = ['log', 'db_query', 'file_read_safe'];

    /** @param callable(string, array): void|null $emit optional event hook */
    public function invoke(?string $runId, ?string $stepId, mixed $toolRequest, ?callable $emit = null): array
    {
        if (! is_array($toolRequest) || empty($toolRequest['tool'])) {
            return ['status' => 'noop', 'result' => null];
        }

        $tool = (string) $toolRequest['tool'];
        if (! in_array($tool, $this->allow, true)) {
            Log::warning('Blocked non-allowlisted tool', ['tool' => $tool]);

            return ['status' => 'blocked', 'result' => ['error' => 'Tool not allowed: '.$tool]];
        }

        $payload = $toolRequest['payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $t0 = microtime(true);

        try {
            $result = match ($tool) {
                'log' => $this->log($payload),
                'db_query' => $this->dbQuerySafe($payload),
                'file_read_safe' => $this->fileReadSafe($payload),
                default => ['error' => 'Unknown tool'],
            };
            $status = 'ok';
        } catch (\Throwable $e) {
            $result = ['error' => $e->getMessage()];
            $status = 'error';
        }

        $latency = (int) round((microtime(true) - $t0) * 1000);

        ToolCall::query()->create([
            'run_id' => $runId,
            'run_step_id' => $stepId,
            'tool' => $tool,
            'payload' => $payload,
            'result' => $result,
            'status' => $status,
            'error' => ($status !== 'ok') ? (string) ($result['error'] ?? '') : null,
            'latency_ms' => $latency,
        ]);

        if ($emit !== null) {
            $emit('tool_call', [
                'tool' => $tool,
                'latency_ms' => $latency,
                'status' => $status,
            ]);
        }

        return ['status' => $status, 'result' => $result];
    }

    /** @param array<string,mixed> $payload */
    protected function log(array $payload): array
    {
        $msg = (string) ($payload['message'] ?? json_encode($payload));
        Log::info('[bosskuai_tool_log] '.$msg);

        return ['ok' => true];
    }

    /** @param array<string,mixed> $payload */
    protected function dbQuerySafe(array $payload): array
    {
        $sqlRaw = (string) ($payload['sql'] ?? '');
        $sql = Str::squish(strtolower($sqlRaw));
        foreach (['insert', 'update', 'delete', 'drop', 'alter', 'grant', 'truncate', ';--'] as $forbidden) {
            if (Str::contains(Str::lower($sqlRaw), $forbidden)) {
                throw new \InvalidArgumentException('Only read-only SELECT queries are allowed.');
            }
        }

        if ($sql === '' || ! str_starts_with($sql, 'select ')) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed.');
        }

        return ['rows' => DB::select($sqlRaw)];
    }

    /** @param array<string,mixed> $payload */
    protected function fileReadSafe(array $payload): array
    {
        $path = (string) ($payload['path'] ?? '');
        $baseRepo = config('bossku.repo_root');
        $bases = [$baseRepo, base_path()];
        foreach ($bases as $base) {
            if ($base !== '' && is_dir($base)) {
                $baseReal = realpath($base);
                $combined = ($baseReal ? $baseReal : $base).DIRECTORY_SEPARATOR.ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
                $combinedReal = realpath($combined);
                if ($combinedReal !== false && $baseReal !== false && Str::startsWith($combinedReal, $baseReal)) {
                    if (! is_file($combinedReal)) {
                        return ['found' => false];
                    }

                    return [
                        'found' => true,
                        'preview' => Str::limit((string) file_get_contents($combinedReal), 8000),
                    ];
                }
            }
        }

        throw new \InvalidArgumentException('Path denied or not readable.');
    }
}
