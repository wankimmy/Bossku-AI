<?php

namespace App\Services\Kernel\Types;

use App\Services\Kernel\Runtime\RunState;

/**
 * Declarative per-node result cache. When set, the runner computes a cache key
 * from the node name and current state, and skips re-invoking the node on a hit.
 * Only plain array outputs are cached (not Command).
 */
final class CachePolicy
{
    /** @var (callable(RunState): string)|null */
    private $keyFn;

    /**
     * @param  int|null  $ttlSeconds  null = no expiry
     * @param  (callable(RunState): string)|null  $keyFn  custom key derivation
     */
    public function __construct(
        public readonly ?int $ttlSeconds = null,
        ?callable $keyFn = null,
    ) {
        $this->keyFn = $keyFn;
    }

    public function key(string $node, RunState $state): string
    {
        if ($this->keyFn !== null) {
            return $node.':'.($this->keyFn)($state);
        }

        return $node.':'.md5((string) json_encode($state->values()));
    }
}
