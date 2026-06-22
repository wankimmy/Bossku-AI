<?php

namespace App\Services\Mcp;

use App\Models\BosskuAi\Goal;
use App\Models\BosskuAi\Project;
use App\Services\Tools\ToolRegistry;
use App\Support\StringCoercion;

/**
 * MCP server: exposes Bossku-AI's capabilities as MCP tools so editors and other
 * MCP clients (Claude Code, Cursor, …) can drive Bossku over the protocol.
 *
 * Pure request handler — given a decoded JSON-RPC message it returns the
 * response message (or null for notifications). Transport (stdio loop) lives in
 * the `bosskuai:mcp-serve` command, keeping this unit-testable.
 */
class McpServer
{
    private const PROTOCOL_VERSION = '2024-11-05';

    public function __construct(private readonly ToolRegistry $tools) {}

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null  response message, or null for notifications
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');

        if (str_starts_with($method, 'notifications/')) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->ok($id, [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'serverInfo' => ['name' => 'bossku-ai', 'version' => '1.0'],
                'capabilities' => ['tools' => new \stdClass],
            ]),
            'ping' => $this->ok($id, new \stdClass),
            'tools/list' => $this->ok($id, ['tools' => $this->toolDefinitions()]),
            'tools/call' => $this->callTool($id, is_array($message['params'] ?? null) ? $message['params'] : []),
            default => $this->error($id, -32601, 'Method not found: '.$method),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toolDefinitions(): array
    {
        return [
            [
                'name' => 'bossku_search',
                'description' => 'Search the active project codebase for text (ripgrep-backed).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'glob' => ['type' => 'string'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'bossku_read',
                'description' => 'Read a file from the active project (line-numbered, paginated).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'offset' => ['type' => 'integer'],
                        'limit' => ['type' => 'integer'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'bossku_goals',
                'description' => 'List business goals and progress for the active project.',
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass],
            ],
            [
                'name' => 'bossku_run',
                'description' => 'Run a task through the BosskuAI multi-agent pipeline and return the result.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['prompt' => ['type' => 'string']],
                    'required' => ['prompt'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function callTool(mixed $id, array $params): array
    {
        $name = (string) ($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        try {
            $text = match ($name) {
                'bossku_search' => $this->runSearch($args),
                'bossku_read' => $this->runRead($args),
                'bossku_goals' => $this->runGoals(),
                'bossku_run' => $this->runTask($args),
                default => throw new \InvalidArgumentException('Unknown tool: '.$name),
            };
        } catch (\Throwable $e) {
            return $this->ok($id, ['content' => [['type' => 'text', 'text' => 'Error: '.$e->getMessage()]], 'isError' => true]);
        }

        return $this->ok($id, ['content' => [['type' => 'text', 'text' => $text]]]);
    }

    /** @param array<string, mixed> $args */
    private function runSearch(array $args): string
    {
        $out = $this->tools->invoke(null, null, ['tool' => 'file_search', 'payload' => [
            'q' => (string) ($args['query'] ?? ''),
            'glob' => (string) ($args['glob'] ?? '*'),
        ]], null, 'executor');

        return json_encode($out['result'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /** @param array<string, mixed> $args */
    private function runRead(array $args): string
    {
        $out = $this->tools->invoke(null, null, ['tool' => 'file_read_safe', 'payload' => [
            'path' => (string) ($args['path'] ?? ''),
            'offset' => (int) ($args['offset'] ?? 1),
            'limit' => (int) ($args['limit'] ?? 0),
        ]], null, 'executor');
        $result = $out['result'] ?? [];

        return StringCoercion::toString($result['preview'] ?? null, json_encode($result) ?: '{}');
    }

    private function runGoals(): string
    {
        $project = Project::query()->where('is_active', true)->first();
        if ($project === null) {
            return 'No active project.';
        }
        $goals = Goal::query()->where('project_id', $project->id)->orderBy('created_at')->get()
            ->map(fn (Goal $g): string => '- ['.$g->progress.'%] '.$g->title.' ('.$g->status.')'
                .($g->target_metric ? ' — '.$g->target_metric : ''))
            ->all();

        return $goals === [] ? 'No goals for "'.$project->name.'".' : implode("\n", $goals);
    }

    /** @param array<string, mixed> $args */
    private function runTask(array $args): string
    {
        $prompt = (string) ($args['prompt'] ?? '');
        if (trim($prompt) === '') {
            throw new \InvalidArgumentException('prompt is required.');
        }
        $result = app(\App\Services\Orchestrator\OrchestratorService::class)->run($prompt);

        return StringCoercion::toString(
            $result['final_output'] ?? $result['output'] ?? null,
            json_encode($result, JSON_UNESCAPED_SLASHES) ?: '{}',
        );
    }

    /**
     * @param  mixed  $result
     * @return array<string, mixed>
     */
    private function ok(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
