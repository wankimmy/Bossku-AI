<?php

namespace App\Services\Agents;

use App\Models\BosskuAi\SpecialistAgent;
use App\Support\StringCoercion;
use Illuminate\Support\Facades\Http;

/**
 * Bring-your-own-agent over HTTP — Paperclip's "if it can receive a heartbeat,
 * it's hired".
 *
 * A specialist agent with `runtime_mode = 'http'` and an `endpoint` in its
 * metadata is an external worker (an OpenClaw/Claude Code/Codex bot, an n8n
 * flow, any webhook). Instead of running an internal LLM, Bossku POSTs the task
 * to that endpoint and maps the response back into the specialist handoff shape,
 * so external workers participate in the same pipeline as native agents.
 */
class HttpAgentAdapter
{
    public function supports(SpecialistAgent $agent): bool
    {
        return strtolower((string) $agent->runtime_mode) === 'http' && $this->endpoint($agent) !== '';
    }

    public function endpoint(SpecialistAgent $agent): string
    {
        $md = is_array($agent->metadata) ? $agent->metadata : [];

        return StringCoercion::toString($md['endpoint'] ?? $md['webhook_url'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>  specialist-handoff-shaped result
     */
    public function dispatch(SpecialistAgent $agent, array $task): array
    {
        $endpoint = $this->endpoint($agent);
        if ($endpoint === '') {
            return $this->fail($agent, 'No HTTP endpoint configured for this agent.');
        }

        $md = is_array($agent->metadata) ? $agent->metadata : [];
        $headers = ['Accept' => 'application/json'];
        $authHeader = StringCoercion::toString($md['auth_header'] ?? null);
        $authValue = StringCoercion::toString($md['auth_value'] ?? null);
        if ($authHeader !== '' && $authValue !== '') {
            $headers[$authHeader] = $authValue;
        }

        $timeout = (int) config('bossku.http_agent_timeout_seconds', 60);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->asJson()
                ->post($endpoint, [
                    'agent' => $agent->role_slug,
                    'display_name' => $agent->display_name,
                    'task' => $task,
                ]);
        } catch (\Throwable $e) {
            return $this->fail($agent, 'HTTP dispatch failed: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return $this->fail($agent, 'External agent returned HTTP '.$response->status());
        }

        $json = $response->json();

        return $this->normalize(is_array($json) ? $json : ['summary' => $response->body()], $agent);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, SpecialistAgent $agent): array
    {
        $summary = StringCoercion::toString(
            $data['summary'] ?? $data['output'] ?? $data['result'] ?? $data['message'] ?? null,
        );
        $handoff = StringCoercion::toString(
            $data['handoff_to_executor'] ?? $data['handoff'] ?? null,
            $summary !== '' ? $summary : 'External agent completed its task.',
        );

        return [
            'summary' => $summary !== '' ? $summary : $handoff,
            'task_strategy' => $this->stringList($data['task_strategy'] ?? []),
            'pitfalls' => $this->stringList($data['pitfalls'] ?? []),
            'files_or_areas_to_focus' => $this->stringList($data['files_or_areas_to_focus'] ?? []),
            'handoff_to_executor' => $handoff,
            '_specialist_model' => 'http:'.$agent->role_slug,
            '_specialist_runtime' => 'http',
        ];
    }

    private function fail(SpecialistAgent $agent, string $error): array
    {
        return [
            'summary' => $agent->display_name.' (external HTTP agent) could not be reached.',
            'task_strategy' => ['Proceed with planner output; the external agent did not respond.'],
            'pitfalls' => [$error],
            'files_or_areas_to_focus' => [],
            'handoff_to_executor' => 'External agent unavailable; continue with the existing plan.',
            '_specialist_model' => 'http:'.$agent->role_slug,
            '_specialist_runtime' => 'http',
            '_specialist_error' => $error,
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => StringCoercion::toString($v),
            $value,
        ), static fn (string $v): bool => $v !== ''));
    }
}
