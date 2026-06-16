<?php

namespace App\Services\Kernel\Types;

/**
 * The value surfaced to a human when a node suspends the run. Carried on the
 * interrupt checkpoint and returned by the runner so the caller can render an
 * approval/clarification prompt. Resumed via Command(resume: ...) in Phase 2.
 */
final class Interrupt
{
    public function __construct(
        public readonly string $id,
        public readonly string $node,
        public readonly mixed $value,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'node' => $this->node, 'value' => $this->value];
    }
}
