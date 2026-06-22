<?php

namespace App\Services\Mcp;

/**
 * Reads the configured external MCP servers (config/mcp.php). A server is
 * "connectable" only when enabled; GitHub/Figma ship disabled until the user
 * provides a token and flips the env flag.
 */
class McpServerRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $servers = config('mcp.servers', []);

        return is_array($servers) ? $servers : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $name): ?array
    {
        $config = $this->all()[$name] ?? null;

        return is_array($config) ? $config : null;
    }

    public function isEnabled(string $name): bool
    {
        return (bool) ($this->get($name)['enabled'] ?? false);
    }

    /**
     * @return list<string>  names of enabled servers
     */
    public function enabledNames(): array
    {
        return array_values(array_keys(array_filter(
            $this->all(),
            static fn ($c): bool => is_array($c) && (bool) ($c['enabled'] ?? false),
        )));
    }
}
