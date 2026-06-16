<?php

namespace App\Services\Kernel\Types;

use Throwable;

/**
 * Declarative per-node retry policy. The runner enforces the mechanics so
 * personas keep the judgment ("loop until green") while the kernel owns the
 * attempt counting and backoff. Enforcement is wired in Phase 3; the type is
 * defined now so node registration is forward-compatible.
 */
final class RetryPolicy
{
    /** @var (callable(Throwable): bool)|null */
    private $retryOn;

    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly int $backoffMs = 0,
        ?callable $retryOn = null,
    ) {
        $this->retryOn = $retryOn;
    }

    public function shouldRetry(Throwable $e): bool
    {
        if ($this->retryOn === null) {
            return true;
        }

        return ($this->retryOn)($e);
    }
}
