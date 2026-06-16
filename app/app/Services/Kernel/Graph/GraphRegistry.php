<?php

namespace App\Services\Kernel\Graph;

/**
 * Resolves a graph name to its topology description. The Studio renders these;
 * assistants reference a graph by name. Currently the built-in default pipeline;
 * user-defined graphs (stored as data) plug in here later.
 */
final class GraphRegistry
{
    /** @return list<string> */
    public function names(): array
    {
        return [DefaultPipelineGraph::NAME];
    }

    public function has(string $name): bool
    {
        return in_array($name, $this->names(), true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function topology(string $name): ?array
    {
        return match ($name) {
            DefaultPipelineGraph::NAME => DefaultPipelineGraph::topology(),
            default => null,
        };
    }
}
