<?php

namespace App\Services\BosskuAi\Checkout;

/**
 * Thrown when an atomic checkout fails because another agent already owns the
 * task. Ported from paperclip's 409 Conflict. The agent contract is: "never
 * retry a 409" — the agent must not re-attempt the checkout, it should pick
 * different work.
 */
final class CheckoutConflictException extends \RuntimeException
{
    /** @param array{type: string, id: string} $taskRef */
    public function __construct(
        public readonly array $taskRef,
        public readonly string $assignee,
    ) {
        parent::__construct(
            "Checkout conflict: task {$taskRef['type']}:{$taskRef['id']} is already owned by another agent (attempted by {$assignee}).",
        );
    }
}