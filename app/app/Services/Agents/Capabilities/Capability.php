<?php

namespace App\Services\Agents\Capabilities;

/**
 * A single capability an agent may exercise. Ported from paperclip's plugin
 * capability model. Capabilities are namespaced strings: "file.read",
 * "file.write", "file.edit", "command.run", "command.run.sudo", "db.query",
 * "mcp.call", "memory.write", "checkout.acquire". The gate validates every
 * host-service call against the agent's declared capability set.
 *
 * Capabilities support a simple wildcard: "command.run" covers
 * "command.run.sudo" (a more specific capability implies the broader one is
 * allowed too, but not vice versa). This mirrors paperclip's least-privilege
 * model: an agent declares only what it needs.
 */
final readonly class Capability
{
    public function __construct(public string $name)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('Capability must be a non-empty string.');
        }
    }

    /**
     * Does this capability satisfy the required capability? A declared
     * capability satisfies a required one if they are equal, or if the
     * declared is a prefix of the required (broader covers narrower).
     *
     * @example
     *   declared 'file.read' satisfies required 'file.read'
     *   declared 'command.run' satisfies required 'command.run.sudo'
     *   declared 'command.run.sudo' does NOT satisfy required 'command.run'
     */
    public function satisfies(self $required): bool
    {
        if ($this->name === $required->name) {
            return true;
        }

        return str_starts_with($required->name, $this->name.'.');
    }

    public function __toString(): string
    {
        return $this->name;
    }
}