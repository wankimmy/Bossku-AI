<?php

namespace App\Services\Kernel\Types;

/**
 * Dynamic fan-out: schedule a node to run with a specific state payload, used
 * for map-reduce. A node may return a list of Send objects (or a Command with
 * sends) to spawn N parallel instances that later join at a BarrierValue.
 *
 * Full parallel execution lands in Phase 3; the type is defined now so graphs
 * and checkpoints are forward-compatible.
 */
final class Send
{
    /** @param array<string, mixed> $state per-instance input for the target node */
    public function __construct(
        public readonly string $node,
        public readonly array $state = [],
    ) {}
}
