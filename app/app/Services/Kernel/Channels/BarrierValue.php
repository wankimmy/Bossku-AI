<?php

namespace App\Services\Kernel\Channels;

/**
 * Named-barrier / fan-in channel. Collects keyed writes and only reports ready
 * once every expected writer has reported. Forward-looking primitive for the
 * Send map-reduce join (Phase 3); in Phase 1 it behaves as a keyed collector.
 */
final class BarrierValue implements ChannelInterface
{
    /** @var array<string, mixed> */
    private array $arrived = [];

    /** @param list<string> $expected expected writer keys */
    public function __construct(private array $expected = []) {}

    /**
     * Write must be ['key' => string, 'value' => mixed] to register an arrival;
     * any other value is stored under an auto-incrementing key.
     */
    public function update(mixed $value): bool
    {
        if (is_array($value) && array_key_exists('key', $value)) {
            $this->arrived[(string) $value['key']] = $value['value'] ?? null;

            return true;
        }

        $this->arrived[(string) count($this->arrived)] = $value;

        return true;
    }

    /** @return list<mixed> collected values once ready, else [] */
    public function get(): mixed
    {
        return $this->isSet() ? array_values($this->arrived) : [];
    }

    public function isSet(): bool
    {
        if ($this->expected === []) {
            return $this->arrived !== [];
        }

        foreach ($this->expected as $key) {
            if (! array_key_exists($key, $this->arrived)) {
                return false;
            }
        }

        return true;
    }

    public function checkpoint(): mixed
    {
        return ['arrived' => $this->arrived, 'expected' => $this->expected];
    }

    public function restore(mixed $snapshot): void
    {
        $this->arrived = is_array($snapshot['arrived'] ?? null) ? $snapshot['arrived'] : [];
        $this->expected = is_array($snapshot['expected'] ?? null) ? array_values($snapshot['expected']) : [];
    }

    public function consume(): bool
    {
        if ($this->arrived === []) {
            return false;
        }

        $this->arrived = [];

        return true;
    }
}
