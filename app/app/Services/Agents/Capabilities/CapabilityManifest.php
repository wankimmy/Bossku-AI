<?php

namespace App\Services\Agents\Capabilities;

/**
 * An agent's declared capability set — the allowlist of host-service calls it
 * may make. Ported from paperclip's capability-gated host services. Every
 * tool invocation is checked against this manifest before execution;
 * undeclared capabilities are denied (least-privilege by construction).
 *
 * Example manifests per role:
 *   executor:    file.read, file.write, file.edit, command.run, db.query, log
 *   planner:     file.read, file.search, file.glob, log
 *   auditor:     file.read, file.search, file.glob, log
 *   specialist:  declared per specialist definition
 */
final class CapabilityManifest
{
    /** @var array<string, Capability> keyed by capability name */
    private array $capabilities = [];

    /** @param list<Capability|string> $capabilities */
    public function __construct(array $capabilities = [])
    {
        foreach ($capabilities as $cap) {
            $this->add(is_string($cap) ? new Capability($cap) : $cap);
        }
    }

    public function add(Capability $capability): self
    {
        $this->capabilities[$capability->name] = $capability;

        return $this;
    }

    /** @return list<string> */
    public function declared(): array
    {
        return array_keys($this->capabilities);
    }

    /**
     * Does this manifest allow the required capability? A declared broader
     * capability (e.g. 'command.run') satisfies a narrower required one
     * ('command.run.sudo').
     */
    public function allows(string $requiredCapability): bool
    {
        $required = new Capability($requiredCapability);

        foreach ($this->capabilities as $declared) {
            if ($declared->satisfies($required)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Assert the capability is allowed; throw if not. This is the gate that
     * host-service calls pass through before execution.
     *
     * @throws CapabilityDeniedException
     */
    public function assert(string $requiredCapability, string $agentRole): void
    {
        if (! $this->allows($requiredCapability)) {
            throw new CapabilityDeniedException($requiredCapability, $agentRole, $this->declared());
        }
    }
}