<?php

namespace App\Services\Agents;

use App\Services\BosskuAi\ModelFallbackService;
use App\Services\BosskuAi\ModelRoutingConfig;
use App\Services\Tools\ToolRegistry;
use App\Support\StringCoercion;

/**
 * ReACT-style agentic tool-use loop — the iterative counterpart to the
 * single-shot executor.
 *
 * opencode's strength is that the model can read → edit → run a check → read the
 * real error → fix, all within one task, observing the result of each tool call
 * before deciding the next. Bossku-AI's pipeline executor is single-shot (it
 * emits one JSON plan). This driver adds the missing capability: it lets a model
 * iterate against the real {@see ToolRegistry} until it declares the task done,
 * with a hard iteration cap and stuck detection so a confused model cannot spin.
 *
 * The protocol is plain JSON (not provider-native function calling) so it works
 * across every Ollama / OpenAI-compatible model the gateway routes to. Each turn
 * the model returns:
 *   {"thought": "...", "tool_calls": [{"tool": "...", "payload": {...}}], "done": false}
 * and when finished:
 *   {"thought": "...", "done": true, "final": {"summary": "...", ...}}
 *
 * All file mutations still flow through ToolRegistry's approval / auto-apply
 * governance — this loop only changes *who decides the next step*, not the
 * safety rails.
 */
class AgenticToolLoop
{
    /** Identical consecutive tool calls before the loop declares itself stuck. */
    private const STUCK_THRESHOLD = 3;

    /** Per-tool-result characters fed back to the model (token control). */
    private const MAX_OBSERVATION_CHARS = 4000;

    public function __construct(
        protected ModelFallbackService $fallback,
        protected ToolRegistry $tools,
        protected AgentToolPermissionService $permissions,
        protected ModelRoutingConfig $modelConfig,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     *                                          role, models (list<string>), max_iterations (int), run_id, temperature,
     *                                          emit (callable), system_extra (string)
     * @return array{status: string, final: mixed, iterations: int, tool_calls: list<array<string, mixed>>, model_used: string, messages: list<array{role: string, content: string}>}
     */
    public function run(string $task, array $options = []): array
    {
        $role = StringCoercion::toString($options['role'] ?? null, 'executor');
        $maxIterations = (int) ($options['max_iterations'] ?? config('bossku.agentic_max_iterations', 12));
        $maxIterations = max(1, min($maxIterations, 50));
        $temperature = (float) ($options['temperature'] ?? 0.2);
        $runId = isset($options['run_id']) ? (string) $options['run_id'] : null;
        $emit = is_callable($options['emit'] ?? null) ? $options['emit'] : null;
        $models = $this->resolveModels($options);

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($role, StringCoercion::toString($options['system_extra'] ?? null, ''))],
            ['role' => 'user', 'content' => 'Task:'."\n".$task],
        ];

        $toolCalls = [];
        $modelUsed = $models[0] ?? 'unknown';
        $recentSignatures = [];

        for ($iteration = 1; $iteration <= $maxIterations; $iteration++) {
            $out = $this->fallback->chatWithFallbacks(
                $models,
                $messages,
                $temperature,
                1,
                $role,
                static fn (mixed $j): bool => is_array($j) && (isset($j['tool_calls']) || isset($j['done']) || isset($j['final'])),
                (int) ($options['max_tokens'] ?? 4096),
                $runId,
            );
            $modelUsed = StringCoercion::toString($out['model_used'] ?? null, $modelUsed);
            $parsed = is_array($out['parsed'] ?? null) ? $out['parsed'] : [];

            $messages[] = ['role' => 'assistant', 'content' => StringCoercion::toString($out['text'] ?? null, json_encode($parsed) ?: '{}')];

            $requested = is_array($parsed['tool_calls'] ?? null) ? $parsed['tool_calls'] : [];
            $done = (bool) ($parsed['done'] ?? false);

            if ($done || $requested === []) {
                return $this->result('completed', $parsed['final'] ?? ($parsed['summary'] ?? $parsed), $iteration, $toolCalls, $modelUsed, $messages);
            }

            $observations = [];
            foreach ($requested as $call) {
                if (! is_array($call) || ! isset($call['tool'])) {
                    continue;
                }
                $signature = md5(json_encode([$call['tool'] ?? '', $call['payload'] ?? []]) ?: '');
                $recentSignatures[] = $signature;
                $recentSignatures = array_slice($recentSignatures, -self::STUCK_THRESHOLD);

                $invocation = $this->tools->invoke(
                    $runId,
                    null,
                    ['tool' => $call['tool'], 'payload' => $call['payload'] ?? [], 'agent_role' => $role],
                    $emit,
                    $role,
                );
                $toolCalls[] = ['tool' => $call['tool'], 'status' => $invocation['status'], 'iteration' => $iteration];
                $observations[] = [
                    'tool' => $call['tool'],
                    'status' => $invocation['status'],
                    'result' => $this->truncate($invocation['result'] ?? null),
                ];
            }

            if ($this->isStuck($recentSignatures)) {
                return $this->result('stuck', $parsed['final'] ?? null, $iteration, $toolCalls, $modelUsed, $messages);
            }

            $messages[] = [
                'role' => 'user',
                'content' => 'Tool results (observe, then decide the next step or set done=true):'."\n"
                    .(json_encode($observations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]'),
            ];
        }

        return $this->result('max_iterations', null, $maxIterations, $toolCalls, $modelUsed, $messages);
    }

    /**
     * @param  list<array<string, mixed>>  $toolCalls
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{status: string, final: mixed, iterations: int, tool_calls: list<array<string, mixed>>, model_used: string, messages: list<array{role: string, content: string}>}
     */
    private function result(string $status, mixed $final, int $iterations, array $toolCalls, string $modelUsed, array $messages): array
    {
        return [
            'status' => $status,
            'final' => $final,
            'iterations' => $iterations,
            'tool_calls' => $toolCalls,
            'model_used' => $modelUsed,
            'messages' => $messages,
        ];
    }

    /** @param list<string> $recent */
    private function isStuck(array $recent): bool
    {
        return count($recent) >= self::STUCK_THRESHOLD && count(array_unique($recent)) === 1;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function resolveModels(array $options): array
    {
        $fromOptions = $options['models'] ?? null;
        if (is_array($fromOptions) && $fromOptions !== []) {
            return array_values(array_filter(array_map('strval', $fromOptions)));
        }

        $profile = $this->modelConfig->executorProfile('default');
        $primary = StringCoercion::toString($profile['primary'] ?? null, 'kimi-k2.6');
        $fallbacks = is_array($profile['fallback'] ?? null) ? $profile['fallback'] : [];

        return array_values(array_unique(array_merge([$primary], array_map('strval', $fallbacks))));
    }

    private function truncate(mixed $result): mixed
    {
        $json = json_encode($result, JSON_UNESCAPED_SLASHES);
        if ($json === false || strlen($json) <= self::MAX_OBSERVATION_CHARS) {
            return $result;
        }

        return ['_truncated' => true, 'preview' => substr($json, 0, self::MAX_OBSERVATION_CHARS).'…'];
    }

    private function systemPrompt(string $role, string $extra): string
    {
        $catalog = $this->permissions->formatToolsBlock($role);

        $base = <<<SYS
You are the BosskuAI Agentic Coder. You solve the task by calling tools in a loop and observing each result before deciding the next step. Work in small, verifiable steps: read before you edit, edit surgically, then verify.

{$catalog}

TOOL PAYLOADS:
- file_read_safe: {path, offset?, limit?} — reads are line-numbered "<n>: text"; strip the "<n>: " prefix when quoting code.
- file_search: {q, glob?} — find files containing text (returns path + line).
- file_glob: {pattern} — list files by glob.
- file_edit: {path, edits:[{old_string, new_string, replace_all?}]} — surgical edit; old_string must match the file (whitespace may differ); failures report not-found/ambiguous so you can retry with more context.
- file_write_proposed: {path, new_contents} — whole-file write/create.
- run_command: {command, cwd?} — run an allowlisted command (e.g. "php artisan test", "composer test", "npm test", "git status") to VERIFY your edits. Only git/php artisan/phpunit/composer/npm/yarn/pnpm/docker compose are permitted; read exit_code + stderr and fix real failures.
- db_query: {sql} — read-only SELECT only.
- log: {message}.

After editing code, run the project's tests or a build/lint command via run_command and react to the result before declaring done.

PROTOCOL — respond with JSON only, no prose, no code fences:
- To act: {"thought": "<one line>", "tool_calls": [{"tool": "<name>", "payload": {...}}], "done": false}
- When finished: {"thought": "<one line>", "done": true, "final": {"summary": "<what you did>", "files_changed": ["<path>"]}}

RULES:
- Read a file before editing it; never invent paths.
- Make the smallest change that works; verify with a read or search after editing.
- If a tool returns an error, read it and adapt — do not repeat the identical call.
- Set done=true as soon as the task is complete; do not pad with extra tool calls.
SYS;

        return $extra !== '' ? $base."\n\nAdditional context:\n".$extra : $base;
    }
}
