<?php

namespace App\Services\Agents;

use App\Models\BosskuAi\SpecialistAgent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MarkdownAgentPackLoader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function loadRepositoryAgents(): array
    {
        $root = base_path('../agents');
        if (! is_dir($root)) {
            $root = base_path('agents');
        }

        return $this->loadFromDirectory($root, source: 'repo_agents');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadProjectAgents(?string $projectRoot): array
    {
        if ($projectRoot === null || $projectRoot === '') {
            return [];
        }

        $dir = rtrim($projectRoot, '/\\').'/.bossku/agents';
        if (! is_dir($dir)) {
            return [];
        }

        return $this->loadFromDirectory($dir, source: 'project_agents');
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function loadFromDirectory(string $dir, string $source): array
    {
        $agents = [];
        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }
            $parsed = $this->parseAgentFile($file->getPathname());
            if ($parsed !== null) {
                $parsed['source'] = $source;
                $agents[] = $parsed;
            }
        }

        return $agents;
    }

    /** @return array<string, mixed>|null */
    public function parseAgentFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = (string) file_get_contents($path);
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m)) {
            return null;
        }

        $front = $this->parseFrontmatter($m[1]);
        $slug = (string) ($front['slug'] ?? Str::slug(pathinfo($path, PATHINFO_FILENAME)));
        $mode = (string) ($front['mode'] ?? 'subagent');

        return [
            'role_slug' => $slug,
            'display_name' => (string) ($front['name'] ?? $slug),
            'description' => (string) ($front['description'] ?? ''),
            'persona_content' => trim($m[2]),
            'trigger_keywords' => is_array($front['keywords'] ?? null) ? $front['keywords'] : [],
            'runtime_mode' => (string) ($front['runtime_mode'] ?? 'advisory'),
            'agent_mode' => $mode,
            'tools' => is_array($front['tools'] ?? null) ? $front['tools'] : [],
            'path' => $path,
        ];
    }

    /** @return array<string, mixed> */
    protected function parseFrontmatter(string $yaml): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\n|\r/', $yaml) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            if ($value === '') {
                continue;
            }
            if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                $inner = trim($value, '[]');
                $out[$key] = array_values(array_filter(array_map(
                    static fn (string $part) => trim($part, " \t\"'"),
                    explode(',', $inner),
                )));
            } else {
                $out[$key] = trim($value, " \t\"'");
            }
        }

        return $out;
    }

    public function syncToProject(array $agentDef, string $projectId): SpecialistAgent
    {
        return SpecialistAgent::query()->updateOrCreate(
            [
                'project_id' => $projectId,
                'role_slug' => (string) $agentDef['role_slug'],
            ],
            [
                'display_name' => (string) $agentDef['display_name'],
                'description' => (string) ($agentDef['description'] ?? ''),
                'trigger_keywords' => $agentDef['trigger_keywords'] ?? [],
                'persona_content' => (string) ($agentDef['persona_content'] ?? ''),
                'approval_status' => 'approved',
                'is_company_staff' => false,
                'staff_active' => true,
                'council_enabled' => true,
                'runtime_mode' => (string) ($agentDef['runtime_mode'] ?? 'advisory'),
                'department' => $agentDef['department'] ?? null,
                'can_create_agents' => (bool) ($agentDef['can_create_agents'] ?? false),
                'budget_policy' => $agentDef['budget_policy'] ?? 'standard',
                'metadata' => [
                    'agent_mode' => $agentDef['agent_mode'] ?? 'subagent',
                    'tools' => $agentDef['tools'] ?? [],
                    'source' => $agentDef['source'] ?? 'markdown_pack',
                    'path' => $agentDef['path'] ?? null,
                ],
            ],
        );
    }
}
