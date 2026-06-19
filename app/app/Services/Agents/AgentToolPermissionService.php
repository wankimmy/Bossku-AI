<?php

namespace App\Services\Agents;

use App\Support\AgentTools;

class AgentToolPermissionService
{
    /** @var list<string> */
    protected array $runtimeTools = [
        'log',
        'db_query',
        'file_read_safe',
        'file_search',
        'file_glob',
        'file_write_proposed',
    ];

    /** @var array<string, list<string>> */
    protected array $deniedByRole = [
        'direct_answer' => ['file_write_proposed', 'db_query'],
        'writer' => ['file_write_proposed', 'db_query'],
        'planner' => ['file_write_proposed', 'db_query'],
        'auditor' => ['file_write_proposed', 'db_query'],
        'security_auditor' => ['file_write_proposed', 'db_query'],
        'final_reviewer' => ['file_write_proposed', 'db_query'],
        'seo-writer' => ['file_write_proposed', 'db_query'],
        'marketing-manager' => ['file_write_proposed', 'db_query'],
        'sales-manager' => ['file_write_proposed', 'db_query'],
        'ui-ux-designer' => ['file_write_proposed', 'db_query'],
    ];

    /** @return list<string> */
    public function allowedTools(string $role, ?string $agentsMdRaw = null): array
    {
        $fromYaml = $agentsMdRaw !== null ? AgentTools::parseFrontmatterTools($agentsMdRaw) : [];
        $tools = $fromYaml !== [] ? $fromYaml : AgentTools::forRole($role);
        $denied = $this->deniedByRole[$role] ?? [];

        return array_values(array_filter(
            $tools,
            fn (string $tool) => in_array($tool, $this->runtimeTools, true) && ! in_array($tool, $denied, true),
        ));
    }

    public function isAllowed(string $role, string $tool, ?string $agentsMdRaw = null): bool
    {
        return in_array($tool, $this->allowedTools($role, $agentsMdRaw), true);
    }

    /** @return list<string> */
    public function filterTools(string $role, array $requestedTools, ?string $agentsMdRaw = null): array
    {
        $allowed = $this->allowedTools($role, $agentsMdRaw);

        return array_values(array_intersect($requestedTools, $allowed));
    }

    public function formatToolsBlock(string $role, ?string $agentsMdRaw = null): string
    {
        $tools = $this->allowedTools($role, $agentsMdRaw);
        $listed = implode(', ', $tools);

        return "## Allowed tools ({$role})\n{$listed}\n\nUse only these capabilities. Do not invent tools outside this list.";
    }
}
