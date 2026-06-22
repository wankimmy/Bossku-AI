<?php

namespace App\Support;

/**
 * Maps pipeline agent roles to BosskuAI runtime tools and editor-equivalent capabilities.
 */
class AgentTools
{
    /** @var array<string, list<string>> */
    protected static array $roleTools = [
        'router' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
        'orchestrator' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
        'planner' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
        'designer' => ['file_read_safe', 'file_search', 'file_glob', 'file_edit', 'file_write_proposed', 'log'],
        'executor' => ['file_read_safe', 'file_search', 'file_glob', 'file_edit', 'file_write_proposed', 'run_command', 'log', 'db_query'],
        'auditor' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
        'security_auditor' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
        'final_reviewer' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
        'writer' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
        'direct_answer' => ['file_read_safe', 'file_search', 'log'],
        'clarification' => ['file_read_safe', 'file_search', 'file_glob', 'log'],
    ];

    /**
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        $role = strtolower(trim($role));

        return self::$roleTools[$role] ?? ['file_read_safe', 'log'];
    }

    /**
     * Parse YAML frontmatter `tools:` from an agents/*.md file.
     *
     * @return list<string>
     */
    public static function parseFrontmatterTools(string $raw): array
    {
        if (! preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $raw, $m)) {
            return [];
        }

        $yaml = $m[1];
        if (! preg_match('/^tools:\s*\[(.*?)\]\s*$/m', $yaml, $toolsMatch)
            && ! preg_match('/^tools:\s*\n((?:\s+-\s+.+\n?)+)/m', $yaml, $toolsMatch)) {
            return [];
        }

        if (str_contains($toolsMatch[0], '[')) {
            $inner = $toolsMatch[1];
            preg_match_all('/["\']?([^"\',\]]+)["\']?/', $inner, $parts);

            return array_values(array_filter(array_map('trim', $parts[1] ?? [])));
        }

        preg_match_all('/^\s*-\s+(.+)$/m', $toolsMatch[1], $lines);

        return array_values(array_filter(array_map(static fn ($t) => trim($t, " \t\"'"), $lines[1] ?? [])));
    }

    public static function formatToolsBlock(string $role, ?string $agentsMdRaw = null): string
    {
        $fromYaml = $agentsMdRaw !== null ? self::parseFrontmatterTools($agentsMdRaw) : [];
        $tools = $fromYaml !== [] ? $fromYaml : self::forRole($role);
        $listed = implode(', ', $tools);

        return "## Allowed tools ({$role})\n{$listed}\n\nUse only these capabilities. Do not invent tools outside this list.";
    }
}
