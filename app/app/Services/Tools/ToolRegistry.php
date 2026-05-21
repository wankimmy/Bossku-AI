<?php

namespace App\Services\Tools;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\ToolCall;
use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\RiskClassifier;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectPathResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class ToolRegistry
{
    /** @var list<string> */
    protected array $allow = [
        'log',
        'db_query',
        'file_read_safe',
        'file_search',
        'file_glob',
        'file_write_proposed',
    ];

    public function __construct(
        protected ProjectPathResolver $paths,
        protected ProjectFileDiscovery $discovery,
        protected ApprovalGateService $approvals,
        protected RiskClassifier $riskClassifier,
    ) {}

    /** @param callable(array<string, mixed>): void|null $emit optional SSE event hook */
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
                'file_search' => $this->fileSearch($payload),
                'file_glob' => $this->fileGlob($payload),
                'file_write_proposed' => $this->fileWriteProposed($runId, $payload),
                default => ['error' => 'Unknown tool'],
            };

            $status = 'ok';

            if (
                $tool === 'file_write_proposed'
                && is_array($result)
                && ! empty($result['approval_id'])
                && $this->approvals->autoApplyFileWritesEnabled()
            ) {
                $this->approvals->autoApproveAndApply((string) $result['approval_id']);
                $result['status'] = 'auto_applied';
            }
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
            $emit([
                'type' => 'tool_call',
                'run_id' => $runId,
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
        $resolved = $this->paths->resolve($path);
        if (! is_file($resolved['absolute'])) {
            return ['found' => false, 'path' => $resolved['relative']];
        }

        return [
            'found' => true,
            'path' => $resolved['relative'],
            'preview' => Str::limit((string) file_get_contents($resolved['absolute']), 8000),
        ];
    }

    /** @param array<string,mixed> $payload */
    protected function fileSearch(array $payload): array
    {
        $query = (string) ($payload['q'] ?? $payload['query'] ?? '');
        if ($query === '') {
            throw new \InvalidArgumentException('Search query is required.');
        }

        $root = $this->paths->repoRoot();
        $glob = (string) ($payload['glob'] ?? '*');
        $finder = Finder::create()
            ->files()
            ->in($root)
            ->name($glob)
            ->ignoreUnreadableDirs()
            ->exclude($this->discovery->skipDirs());

        $matches = [];
        $pattern = '/'.preg_quote($query, '/').'/i';
        $limit = $this->discovery->maxSearchMatches();

        foreach ($finder as $file) {
            if (count($matches) >= $limit) {
                break;
            }

            $absolute = $file->getRealPath();
            if ($absolute === false) {
                continue;
            }

            if ($file->getSize() > 1_048_576) {
                continue;
            }

            $contents = @file_get_contents($absolute);
            if ($contents === false || ! mb_check_encoding($contents, 'UTF-8')) {
                continue;
            }

            if (! preg_match($pattern, $contents)) {
                continue;
            }

            $relative = ltrim(str_replace($root, '', $absolute), DIRECTORY_SEPARATOR);
            $matches[] = [
                'path' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
            ];
        }

        return ['matches' => $matches, 'count' => count($matches)];
    }

    /** @param array<string,mixed> $payload */
    protected function fileGlob(array $payload): array
    {
        $pattern = (string) ($payload['pattern'] ?? $payload['glob'] ?? '');
        if ($pattern === '') {
            throw new \InvalidArgumentException('pattern is required.');
        }

        $paths = $this->discovery->globPaths($pattern);
        $matches = array_map(static fn (string $path) => ['path' => $path], $paths);

        return ['matches' => $matches, 'count' => count($matches)];
    }

    /** @param array<string,mixed> $payload */
    protected function fileWriteProposed(?string $runId, array $payload): array
    {
        $path = (string) ($payload['path'] ?? '');
        $after = (string) ($payload['new_contents'] ?? $payload['contents'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('path is required.');
        }

        $resolved = $this->paths->resolve($path);
        $before = is_file($resolved['absolute']) ? (string) file_get_contents($resolved['absolute']) : '';
        $diff = $this->paths->unifiedDiff($resolved['relative'], $before, $after);

        if ($runId === null) {
            $run = Run::query()->create([
                'prompt' => 'Agent file change: '.$resolved['relative'],
                'status' => 'running',
                'metadata' => ['source' => 'agent_tool'],
            ]);
            $runId = $run->id;
        }

        $risk = $this->riskClassifier->classify($resolved['relative'].' '.$after);

        $approval = $this->approvals->createApproval(
            $runId,
            null,
            'file_write',
            'Write file: '.$resolved['relative'],
            $risk,
            [
                'path' => $resolved['relative'],
                'before' => $before,
                'after' => $after,
                'diff' => $diff,
            ],
        );

        return [
            'approval_id' => $approval->id,
            'path' => $resolved['relative'],
            'status' => 'pending',
            'diff' => $diff,
        ];
    }
}
