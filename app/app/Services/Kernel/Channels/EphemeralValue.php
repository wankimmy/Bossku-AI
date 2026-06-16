<?php

namespace App\Services\Kernel\Channels;

/**
 * Holds a value for exactly one superstep, then clears on consume(). Useful for
 * per-step scratch passed between nodes that must not persist into checkpoints
 * of later steps.
 */
final class EphemeralValue implements ChannelInterface
{
    private mixed $value = null;

    private bool $set = false;

    public function update(mixed $value): bool
    {
        $this->value = $value;
        $this->set = true;

        return true;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isSet(): bool
    {
        return $this->set;
    }

    public function checkpoint(): mixed
    {
        return $this->set ? ['v' => $this->value] : null;
    }

    public function restore(mixed $snapshot): void
    {
        if (is_array($snapshot) && array_key_exists('v', $snapshot)) {
            $this->value = $snapshot['v'];
            $this->set = true;

            return;
        }

        $this->value = null;
        $this->set = false;
    }

    public function consume(): bool
    {
        if (! $this->set) {
            return false;
        }

        $this->value = null;
        $this->set = false;

        return true;
    }
}
