<?php

namespace App\Services\Kernel\Types;

/**
 * A node may return a Command instead of a plain state-update array to control
 * routing and state in one move (LangGraph's Command).
 *
 *   return new Command(update: ['plan' => $plan], goto: 'executor');
 *
 * - update: channel writes to apply.
 * - goto:   override outgoing edges (node name, END, or a list of either).
 * - send:   dynamic fan-out targets (Phase 3).
 */
final class Command
{
    /**
     * @param  array<string, mixed>  $update
     * @param  string|list<string>|null  $goto
     * @param  list<Send>  $send
     */
    public function __construct(
        public readonly array $update = [],
        public readonly string|array|null $goto = null,
        public readonly array $send = [],
    ) {}

    public function hasGoto(): bool
    {
        return $this->goto !== null;
    }

    /** @return list<string> */
    public function gotoList(): array
    {
        if ($this->goto === null) {
            return [];
        }

        return is_array($this->goto) ? array_values($this->goto) : [$this->goto];
    }
}
