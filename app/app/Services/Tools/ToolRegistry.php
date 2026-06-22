<?php

namespace App\Services\Tools;

use App\Models\BosskuAi\Run;
use App\Models\BosskuAi\ToolCall;
use App\Services\Agents\AgentToolPermissionService;
use App\Services\Governance\ApprovalGateService;
use App\Services\Governance\RiskClassifier;
use App\Services\Project\FileEditEngine;
use App\Services\Project\ProjectCommandRunner;
use App\Services\Project\ProjectFileDiscovery;
use App\Services\Project\ProjectPathResolver;
use App\Support\ToolCallFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class ToolRegistry
{
    /** Max lines returned by a single file_read_safe call (paged via offset). */
    private const MAX_READ_LINES = 2000;

    /** Per-line character cap; long minified lines are truncated, not dropped. */
    private const MAX_LINE_CHARS = 2000;

    /** @var list<string> */
    protected array $allow = [
        'log',
        'db_query',
        'file_read_safe',
        'file_search',
        'file_glob',
        'file_write_proposed',
        'file_edit',
        'run_command',
        'mcp_list_tools',
        'mcp_call',
    ];

    public function __construct(
        protected ProjectPathResolver $paths,
        protected ProjectFileDiscovery $discovery,
        protected ApprovalGateService $approvals,
        protected RiskClassifier $riskClassifier,
        protected AgentToolPermissionService $toolPermissions,
        protected ProjectCommandRunner $commands,
        protected FileEditEngine $editEngine = new FileEditEngine,
    ) {}

    /** @param callable(array<string, mixed>): void|null $emit optional SSE event hook */
    public function invoke(?string $runId, ?string $stepId, mixed $toolRequest, ?callable $emit = null, string $agentRole = 'executor'): array
    {
        if (! is_array($toolRequest) || empty($toolRequest['tool'])) {
            return ['status' => 'noop', 'result' => null];
        }

        $tool = (string) $toolRequest['tool'];
        $role = (string) ($toolRequest['agent_role'] ?? $agentRole);
        if (! $this->toolPermissions->isAllowed($role, $tool)) {
            Log::warning('Blocked tool by agent permission policy', ['tool' => $tool, 'role' => $role]);

            return ['status' => 'blocked', 'result' => ['error' => "Tool not allowed for role {$role}: {$tool}"]];
        }

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
                'file_edit' => $this->fileEdit($runId, $payload),
                'run_command' => $this->runCommand($payload),
                'mcp_list_tools' => $this->mcpListTools($payload),
                'mcp_call' => $this->mcpCall($payload),
                default => ['error' => 'Unknown tool'],
            };

            $status = 'ok';

            if (
                in_array($tool, ['file_write_proposed', 'file_edit'], true)
                && is_array($result)
                && ! empty($result['approval_id'])
                && $this->approvals->autoApplyFileWritesEnabled()
                && ! (bool) config('bossku.require_user_approval_before_apply', true)
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
                'agent' => 'tools',
                'tool' => $tool,
                'payload' => ToolCallFormatter::payloadPreview($tool, $payload),
                'summary' => ToolCallFormatter::summary($tool, $payload, $status),
                'message' => ToolCallFormatter::actionDetail($tool, $payload),
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

        $raw = (string) file_get_contents($resolved['absolute']);
        $lines = $raw === '' ? [] : explode("\n", str_replace(["\r\n", "\r"], "\n", $raw));
        $totalLines = count($lines);

        // 1-indexed offset + line limit, mirroring opencode's read tool so the
        // model can page through large files instead of getting a blind 8KB cut.
        $offset = max(1, (int) ($payload['offset'] ?? 1));
        $limit = (int) ($payload['limit'] ?? self::MAX_READ_LINES);
        $limit = $limit > 0 ? min($limit, self::MAX_READ_LINES) : self::MAX_READ_LINES;

        $window = array_slice($lines, $offset - 1, $limit);
        $rendered = [];
        foreach ($window as $i => $line) {
            if (mb_strlen($line) > self::MAX_LINE_CHARS) {
                $line = mb_substr($line, 0, self::MAX_LINE_CHARS).'… (line truncated)';
            }
            $rendered[] = ($offset + $i).': '.$line;
        }

        $returned = count($rendered);
        $lastLine = $offset + $returned - 1;
        $truncated = $lastLine < $totalLines;

        return [
            'found' => true,
            'path' => $resolved['relative'],
            'total_lines' => $totalLines,
            'offset' => $offset,
            'returned_lines' => $returned,
            'truncated' => $truncated,
            'preview' => implode("\n", $rendered)
                .($truncated ? "\n… (".($totalLines - $lastLine).' more line(s); call file_read_safe again with offset '.($lastLine + 1).')' : ''),
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
        $limit = $this->discovery->maxSearchMatches();

        // Prefer ripgrep: fast, gitignore-aware, and returns the matching line +
        // line number so the model can quote exact text for surgical edits. Falls
        // back to the in-PHP scan when rg is not installed in the container.
        if ($this->ripgrepAvailable()) {
            $rg = $this->ripgrepSearch($root, $query, $glob, $limit);
            if ($rg !== null) {
                return $rg;
            }
        }

        return $this->phpSearch($root, $query, $glob, $limit);
    }

    private static ?bool $rgAvailable = null;

    protected function ripgrepBinary(): string
    {
        $custom = (string) config('bossku.ripgrep_path', '');

        return $custom !== '' ? $custom : 'rg';
    }

    protected function ripgrepAvailable(): bool
    {
        if (self::$rgAvailable !== null) {
            return self::$rgAvailable;
        }
        if (! (bool) config('bossku.allow_ripgrep_search', true)) {
            return self::$rgAvailable = false;
        }

        try {
            return self::$rgAvailable = Process::timeout(5)->run([$this->ripgrepBinary(), '--version'])->successful();
        } catch (\Throwable) {
            return self::$rgAvailable = false;
        }
    }

    /**
     * @return array{matches: list<array{path: string, line_number?: int, line?: string}>, count: int, engine?: string}|null
     */
    protected function ripgrepSearch(string $root, string $query, string $glob, int $limit): ?array
    {
        $args = [
            $this->ripgrepBinary(),
            '--line-number', '--no-heading', '--color', 'never',
            '--fixed-strings', '--ignore-case',
            '--max-columns', '500', '--max-count', '50',
        ];
        foreach ($this->discovery->skipDirs() as $dir) {
            $args[] = '--glob';
            $args[] = '!'.$dir.'/**';
        }
        if ($glob !== '' && $glob !== '*') {
            $args[] = '--glob';
            $args[] = $glob;
        }
        $args[] = '-e';
        $args[] = $query;
        $args[] = $root;

        try {
            $result = Process::timeout(30)->path($root)->run($args);
        } catch (\Throwable) {
            return null;
        }

        // rg exit codes: 0 = matches, 1 = no matches (both valid), >=2 = error.
        if (($result->exitCode() ?? 2) >= 2) {
            return null;
        }

        $matches = [];
        foreach (preg_split("/\r\n|\n|\r/", $result->output()) ?: [] as $row) {
            if ($row === '' || count($matches) >= $limit) {
                continue;
            }
            if (preg_match('/^(.+?):(\d+):(.*)$/', $row, $m) !== 1) {
                continue;
            }
            $relative = ltrim(str_replace($root, '', $m[1]), DIRECTORY_SEPARATOR);
            $matches[] = [
                'path' => str_replace(DIRECTORY_SEPARATOR, '/', $relative),
                'line_number' => (int) $m[2],
                'line' => Str::limit(trim($m[3]), 300),
            ];
        }

        return ['matches' => $matches, 'count' => count($matches), 'engine' => 'ripgrep'];
    }

    /**
     * @return array{matches: list<array{path: string}>, count: int, engine: string}
     */
    protected function phpSearch(string $root, string $query, string $glob, int $limit): array
    {
        $finder = Finder::create()
            ->files()
            ->in($root)
            ->name($glob)
            ->ignoreUnreadableDirs()
            ->exclude($this->discovery->skipDirs());

        $matches = [];
        $pattern = '/'.preg_quote($query, '/').'/i';

        foreach ($finder as $file) {
            if (count($matches) >= $limit) {
                break;
            }

            $absolute = $file->getRealPath();
            if ($absolute === false || $file->getSize() > 1_048_576) {
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
            $matches[] = ['path' => str_replace(DIRECTORY_SEPARATOR, '/', $relative)];
        }

        return ['matches' => $matches, 'count' => count($matches), 'engine' => 'php'];
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

    /**
     * Surgical edit: locate `old_string` in the file (tolerating whitespace /
     * indentation drift) and replace it with `new_string`. Accepts either a
     * single {old_string, new_string, replace_all?} or an `edits` array of
     * them. Produces the resulting file and routes it through the same proposed
     * file-write approval path as file_write_proposed.
     *
     * @param array<string,mixed> $payload
     */
    protected function fileEdit(?string $runId, array $payload): array
    {
        $path = (string) ($payload['path'] ?? '');
        if ($path === '') {
            throw new \InvalidArgumentException('path is required.');
        }

        $edits = $payload['edits'] ?? null;
        if (! is_array($edits) || $edits === []) {
            // Single-edit convenience form.
            $old = (string) ($payload['old_string'] ?? $payload['oldString'] ?? '');
            if ($old === '') {
                throw new \InvalidArgumentException('Provide edits[] or old_string/new_string.');
            }
            $edits = [[
                'old_string' => $old,
                'new_string' => (string) ($payload['new_string'] ?? $payload['newString'] ?? ''),
                'replace_all' => (bool) ($payload['replace_all'] ?? $payload['replaceAll'] ?? false),
            ]];
        }

        $resolved = $this->paths->resolve($path);
        if (! is_file($resolved['absolute'])) {
            throw new \InvalidArgumentException('Cannot edit a file that does not exist: '.$resolved['relative']);
        }

        $before = (string) file_get_contents($resolved['absolute']);
        $after = $this->editEngine->applyEdits($before, array_values($edits));

        return $this->fileWriteProposed($runId, [
            'path' => $path,
            'new_contents' => $after,
        ]);
    }

    /**
     * Run an allowlisted project command (tests, build, lint, git status…) so an
     * agentic loop can verify its own edits. Execution is delegated to
     * ProjectCommandRunner, which enforces the command allowlist, forbidden
     * tokens, working-directory/path bounds, timeout, and output truncation —
     * this tool adds no new execution surface beyond that hardened runner.
     *
     * @param array<string,mixed> $payload
     */
    protected function runCommand(array $payload): array
    {
        $command = (string) ($payload['command'] ?? '');
        if (trim($command) === '') {
            throw new \InvalidArgumentException('command is required.');
        }

        $cwd = (string) ($payload['cwd'] ?? $payload['working_directory'] ?? '');
        $entry = ['command' => $command];
        if ($cwd !== '') {
            $entry['cwd'] = $cwd;
        }

        $outcome = $this->commands->runAllowedProjectCommands([$entry]);
        $row = $outcome['executed'][0] ?? [
            'command' => $command,
            'exit_code' => -1,
            'stdout' => '',
            'stderr' => 'Command produced no result.',
            'ok' => false,
        ];

        if ($outcome['post_git_status'] !== null) {
            $row['git_status_after'] = $outcome['post_git_status'];
        }

        return $row;
    }

    /**
     * List the tools exposed by a connected external MCP server (e.g. github,
     * figma). @param array<string,mixed> $payload
     */
    protected function mcpListTools(array $payload): array
    {
        $server = (string) ($payload['server'] ?? '');
        if ($server === '') {
            throw new \InvalidArgumentException('server is required.');
        }

        $tools = app(\App\Services\Mcp\McpToolBridge::class)->listTools($server);

        return ['server' => $server, 'tools' => $tools, 'count' => count($tools)];
    }

    /**
     * Call a tool on a connected external MCP server.
     * Payload: {server, tool, arguments?}. @param array<string,mixed> $payload
     */
    protected function mcpCall(array $payload): array
    {
        $server = (string) ($payload['server'] ?? '');
        $tool = (string) ($payload['tool'] ?? '');
        if ($server === '' || $tool === '') {
            throw new \InvalidArgumentException('server and tool are required.');
        }
        $arguments = is_array($payload['arguments'] ?? null) ? $payload['arguments'] : [];

        return app(\App\Services\Mcp\McpToolBridge::class)->callTool($server, $tool, $arguments);
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
