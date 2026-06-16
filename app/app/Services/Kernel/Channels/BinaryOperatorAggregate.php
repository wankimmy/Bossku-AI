<?php

namespace App\Services\Kernel\Channels;

/**
 * Folds every write into a running accumulator via a binary operator, starting
 * from an identity value. Used for token totals, latency sums, score merges.
 *
 * Example: new BinaryOperatorAggregate(fn ($a, $b) => $a + $b, 0)
 */
final class BinaryOperatorAggregate implements ChannelInterface
{
    private mixed $value;

    private bool $set = false;

    /** @var callable(mixed, mixed): mixed */
    private $operator;

    public function __construct(callable $operator, private readonly mixed $initial = null)
    {
        $this->operator = $operator;
        $this->value = $initial;
    }

    public function update(mixed $value): bool
    {
        $this->value = ($this->operator)($this->value, $value);
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
        return ['v' => $this->value, 'set' => $this->set];
    }

    public function restore(mixed $snapshot): void
    {
        if (is_array($snapshot) && array_key_exists('v', $snapshot)) {
            $this->value = $snapshot['v'];
            $this->set = (bool) ($snapshot['set'] ?? true);

            return;
        }

        $this->value = $this->initial;
        $this->set = false;
    }

    public function consume(): bool
    {
        return false;
    }
}
