<?php

namespace App\Services\Kernel\Channels;

/**
 * Last-write-wins channel. The default for scalar-ish state (plan, route,
 * final_output). When several writers hit it in one superstep, the last applied
 * write wins (writers are applied in deterministic node order).
 */
final class LastValue implements ChannelInterface
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
        return false;
    }
}
