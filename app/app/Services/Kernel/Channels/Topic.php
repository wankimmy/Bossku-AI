<?php

namespace App\Services\Kernel\Channels;

/**
 * Append/accumulate channel (pub-sub topic). Used for growing lists such as
 * audit_findings, tool_calls, or messages. A write may be a single value or a
 * list of values. With $accumulate=false the channel holds only the current
 * superstep's writes (transient fan-in).
 */
final class Topic implements ChannelInterface
{
    /** @var list<mixed> */
    private array $values = [];

    public function __construct(
        private readonly bool $unique = false,
        private readonly bool $accumulate = true,
    ) {}

    public function update(mixed $value): bool
    {
        $incoming = is_array($value) && array_is_list($value) ? $value : [$value];
        if ($incoming === []) {
            return false;
        }

        foreach ($incoming as $v) {
            if ($this->unique && in_array($v, $this->values, true)) {
                continue;
            }
            $this->values[] = $v;
        }

        return true;
    }

    public function get(): mixed
    {
        return $this->values;
    }

    public function isSet(): bool
    {
        return $this->values !== [];
    }

    public function checkpoint(): mixed
    {
        return $this->values;
    }

    public function restore(mixed $snapshot): void
    {
        $this->values = is_array($snapshot) ? array_values($snapshot) : [];
    }

    public function consume(): bool
    {
        if (! $this->accumulate && $this->values !== []) {
            $this->values = [];

            return true;
        }

        return false;
    }
}
