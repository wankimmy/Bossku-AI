<?php

namespace App\Services\Kernel\Types;

use RuntimeException;

/**
 * Thrown by the interrupt() helper inside a node to durably suspend the run.
 * The runner catches it, writes an interrupt-source checkpoint (state frozen),
 * and returns control so the process may exit and resume later. Full HIL wiring
 * (Approvals/clarification) is Phase 2; the exception + runner handling exist
 * now so the seam is in place.
 */
final class GraphInterrupt extends RuntimeException
{
    public function __construct(public readonly mixed $value)
    {
        parent::__construct('Graph execution interrupted pending human input.');
    }
}
