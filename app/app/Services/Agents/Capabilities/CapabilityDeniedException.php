<?php

namespace App\Services\Agents\Capabilities;

/**
 * Thrown when an agent attempts a host-service call it has not declared.
 * Ported from paperclip's capability violation error. The agent contract is:
 * custom instructions cannot expand authority — only the manifest can.
 */
final class CapabilityDeniedException extends \RuntimeException
{
    /**
     * @param  string  $requiredCapability
     * @param  string  $agentRole
     * @param  list<string>  $declared  the capabilities the agent DID declare
     */
    public function __construct(
        public readonly string $requiredCapability,
        public readonly string $agentRole,
        public readonly array $declared,
    ) {
        parent::__construct(
            "Capability denied: agent '{$agentRole}' attempted '{$requiredCapability}' but only declared: [".implode(', ', $declared).']. Custom instructions cannot expand authority.',
        );
    }
}