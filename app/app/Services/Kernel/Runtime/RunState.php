<?php

namespace App\Services\Kernel\Runtime;

use App\Services\Kernel\Channels\ChannelInterface;
use App\Services\Kernel\Graph\StateSchema;

/**
 * The shared-state blackboard for a single graph run. Each key is a channel
 * with reducer semantics; nodes read via get() and write by returning update
 * arrays which the runner applies via update().
 */
final class RunState
{
    /** @param array<string, ChannelInterface> $channels */
    private function __construct(
        private array $channels,
        private readonly StateSchema $schema,
    ) {}

    public static function fromSchema(StateSchema $schema): self
    {
        return new self($schema->instantiate(), $schema);
    }

    /**
     * Apply a batch of channel writes. Unknown keys auto-create a default
     * (LastValue) channel so nodes can introduce ad-hoc state safely.
     *
     * @param array<string, mixed> $writes
     */
    public function update(array $writes): void
    {
        foreach ($writes as $key => $value) {
            if (! isset($this->channels[$key])) {
                $this->channels[$key] = $this->schema->defaultChannel();
            }
            $this->channels[$key]->update($value);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->channels[$key]) && $this->channels[$key]->isSet()) {
            return $this->channels[$key]->get();
        }

        return $default;
    }

    public function has(string $key): bool
    {
        return isset($this->channels[$key]) && $this->channels[$key]->isSet();
    }

    /**
     * Current values of all set channels (for passing to nodes / debugging).
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $out = [];
        foreach ($this->channels as $key => $channel) {
            if ($channel->isSet()) {
                $out[$key] = $channel->get();
            }
        }

        return $out;
    }

    /**
     * Serializable snapshot of every channel for checkpointing.
     *
     * @return array<string, mixed>
     */
    public function checkpoint(): array
    {
        $out = [];
        foreach ($this->channels as $key => $channel) {
            $out[$key] = $channel->checkpoint();
        }

        return $out;
    }

    /** Restore channel state from a checkpoint() snapshot. */
    public function restore(array $snapshot): void
    {
        foreach ($snapshot as $key => $value) {
            if (! isset($this->channels[$key])) {
                $this->channels[$key] = $this->schema->defaultChannel();
            }
            $this->channels[$key]->restore($value);
        }
    }

    /** Reset ephemeral/barrier channels at the end of a superstep. */
    public function consumeTransient(): void
    {
        foreach ($this->channels as $channel) {
            $channel->consume();
        }
    }
}
