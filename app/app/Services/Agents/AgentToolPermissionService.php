<?php

namespace App\Services\Agents;

use App\Support\AgentTools;

class AgentToolPermissionService
{
    /** @var array<string, string> Editor / agents/*.md aliases → BosskuAI runtime tool names. */
    protected array $editorAliases = [
        'read' => 'file_read_safe',
        'grep' => 'file_search',
        'glob' => 'file_glob',
        'write' => 'file_write_proposed',
        'edit' => 'file_edit',
        'bash' => 'run_command',
        'shell' => 'run_command',
    ];

    /** @var list<string> */
    protected array $runtimeTools = [
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

    /** @var array<string, list<string>> */
    protected array $deniedByRole = [
        'direct_answer' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'writer' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'planner' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'auditor' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'security_auditor' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'final_reviewer' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'seo-writer' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'marketing-manager' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'sales-manager' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
        'ui-ux-designer' => ['file_write_proposed', 'file_edit', 'run_command', 'mcp_call', 'db_query'],
    ];

    /** @return list<string> */
    public function allowedTools(string $role, ?string $agentsMdRaw = null): array
    {
        $fromYaml = $agentsMdRaw !== null ? AgentTools::parseFrontmatterTools($agentsMdRaw) : [];
        $tools = $fromYaml !== [] ? $fromYaml : AgentTools::forRole($role);
        $tools = $this->normalizeTools($tools);
        $denied = $this->deniedByRole[$role] ?? [];

        return array_values(array_filter(
            $tools,
            fn (string $tool) => in_array($tool, $this->runtimeTools, true) && ! in_array($tool, $denied, true),
        ));
    }

    /**
     * Map editor-style tool names from agents/*.md to Bossku runtime tools.
     *
     * @param  list<string>  $tools
     * @return list<string>
     */
    public function normalizeTools(array $tools): array
    {
        $normalized = [];

        foreach ($tools as $tool) {
            if (! is_string($tool) || trim($tool) === '') {
                continue;
            }

            $trimmed = trim($tool);
            $alias = $this->editorAliases[strtolower($trimmed)] ?? $trimmed;
            if (! in_array($alias, $this->runtimeTools, true)) {
                continue;
            }
            $normalized[] = $alias;
        }

        return array_values(array_unique($normalized));
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

        return "## Allowed tools ({$role})\n{$listed}\n\n"
            .'These are BosskuAI runtime capabilities for the active project root, not external editor tools. '
            .'Do not ask the user to enable file_read/file_write or shell access. '
            .'Use only these capabilities. Do not invent tools outside this list.';
    }

    /**
     * Build a structured PermissionRuleset for a role, porting the static
     * deniedByRole array into the allow/deny/ask model. A denied tool becomes
     * an explicit DENY rule; an allowed tool gets an ALLOW rule. This is the
     * opencode-style ruleset that supports path-pattern overrides (e.g.
     * file_write_proposed is ALLOW for executor but ASK for *.env).
     *
     * The default for a tool not mentioned in the role's map is DENY (the
     * runtime tool whitelist already enforces this, but the ruleset makes it
     * explicit for the ask-flow).
     *
     * @return PermissionRuleset
     */
    public function rulesetForRole(string $role, ?string $agentsMdRaw = null): PermissionRuleset
    {
        $allowed = $this->allowedTools($role, $agentsMdRaw);
        $ruleset = new PermissionRuleset;

        foreach ($this->runtimeTools as $tool) {
            $action = in_array($tool, $allowed, true)
                ? PermissionRule::ACTION_ALLOW
                : PermissionRule::ACTION_DENY;
            $ruleset->add(new PermissionRule($tool, $action));
        }

        return $ruleset;
    }

    /**
     * Decide allow / deny / ask for a tool+path using the structured ruleset.
     * This is the entry point for the ask-flow: callers that want a three-way
     * decision (to surface an approval card) use this instead of isAllowed().
     *
     * @return PermissionRule::ACTION_*
     */
    public function decide(string $role, string $tool, ?string $path = null, ?string $agentsMdRaw = null): string
    {
        return $this->rulesetForRole($role, $agentsMdRaw)
            ->evaluate($tool, $path, PermissionRule::ACTION_DENY);
    }
}
