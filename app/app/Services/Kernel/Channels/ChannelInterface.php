<?php

namespace App\Services\Kernel\Channels;

/**
 * A channel is a typed slot in the run-state blackboard with reducer semantics.
 *
 * Multiple nodes may write the same channel within one superstep; the channel
 * decides how those writes combine (last-wins, append, fold, barrier, ...).
 * This keeps parallel writes deterministic — the foundation for fan-out (P3).
 */
interface ChannelInterface
{
    /**
     * Apply a single write through the channel's reducer.
     *
     * @return bool true if the channel value changed
     */
    public function update(mixed $value): bool;

    /** Current value (or null when unset). */
    public function get(): mixed;

    /** Whether a value has ever been written. */
    public function isSet(): bool;

    /** Serializable snapshot for checkpointing. */
    public function checkpoint(): mixed;

    /** Restore from a snapshot produced by checkpoint(). */
    public function restore(mixed $snapshot): void;

    /**
     * Reset transient state at the end of a superstep (ephemeral/barrier
     * channels). Returns true if the value changed.
     */
    public function consume(): bool;
}
