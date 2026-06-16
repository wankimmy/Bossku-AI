<?php

namespace App\Services\Kernel\Graph;

use App\Services\Kernel\Channels\ChannelInterface;
use App\Services\Kernel\Channels\LastValue;

/**
 * Declares the channel set (the typed shape) of a graph's run-state blackboard.
 * Stores prototype channel instances and hands out fresh clones per run.
 */
final class StateSchema
{
    /** @param array<string, ChannelInterface> $channels prototypes keyed by name */
    public function __construct(private array $channels = []) {}

    /** @param array<string, ChannelInterface> $channels */
    public static function make(array $channels): self
    {
        return new self($channels);
    }

    public function withChannel(string $name, ChannelInterface $channel): self
    {
        $clone = new self($this->channels);
        $clone->channels[$name] = $channel;

        return $clone;
    }

    public function has(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    /**
     * Fresh channel instances for a new run (prototypes cloned).
     *
     * @return array<string, ChannelInterface>
     */
    public function instantiate(): array
    {
        $out = [];
        foreach ($this->channels as $name => $channel) {
            $out[$name] = clone $channel;
        }

        return $out;
    }

    /** Default channel used for writes to undeclared keys. */
    public function defaultChannel(): ChannelInterface
    {
        return new LastValue;
    }
}
