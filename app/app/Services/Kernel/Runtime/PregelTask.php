<?php

namespace App\Services\Kernel\Runtime;

use InvalidArgumentException;

/**
 * A scheduled unit of work in the frontier: a node to run, optionally carrying a
 * per-instance Send payload for map-reduce fan-out. Plain tasks serialize to a
 * bare node name (so checkpoints stay backward-compatible with Phase 1/2); Send
 * tasks serialize to ['node' => ..., 'input' => [...]].
 */
final class PregelTask
{
    /** @param array<string, mixed>|null $input Send payload, or null for a plain node */
    public function __construct(
        public readonly string $node,
        public readonly ?array $input = null,
    ) {}

    public function isSend(): bool
    {
        return $this->input !== null;
    }

    /** Dedup signature: plain tasks collapse by node; Send tasks stay distinct. */
    public function signature(): string
    {
        return $this->input === null ? $this->node : $this->node.':'.md5((string) json_encode($this->input));
    }

    /** @return string|array<string, mixed> */
    public function serialize(): string|array
    {
        return $this->input === null ? $this->node : ['node' => $this->node, 'input' => $this->input];
    }

    public static function fromMixed(mixed $value): self
    {
        if (is_string($value)) {
            return new self($value);
        }
        if (is_array($value) && isset($value['node'])) {
            $input = isset($value['input']) && is_array($value['input']) ? $value['input'] : null;

            return new self((string) $value['node'], $input);
        }

        throw new InvalidArgumentException('Cannot decode PregelTask from value.');
    }
}
