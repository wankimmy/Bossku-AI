<?php

namespace App\Services\Kernel\Runtime;

use App\Services\Kernel\Store\StoreInterface;
use App\Services\Kernel\Types\GraphInterrupt;

/**
 * Per-run execution context handed to every node. Carries the thread id (the
 * run id), the current superstep, a stream emitter, the long-term store, and a
 * scratchpad used to inject human resume values on interrupt resume (Phase 2).
 */
final class GraphContext
{
    /** @var (callable(array<string, mixed>): void)|null */
    private $emit;

    /** @var array<string, mixed> per-task Send payload for the node currently executing */
    private array $taskInput = [];

    /** @param array<string, mixed> $scratch */
    public function __construct(
        public readonly string $threadId,
        public int $step = 0,
        ?callable $emit = null,
        public readonly ?StoreInterface $store = null,
        private array $scratch = [],
    ) {
        $this->emit = $emit;
    }

    /**
     * The Send payload for the node currently executing (map-reduce fan-out).
     * Empty for normally-scheduled nodes.
     *
     * @param array<string, mixed> $input
     */
    public function setTaskInput(array $input): void
    {
        $this->taskInput = $input;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->taskInput[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function taskInput(): array
    {
        return $this->taskInput;
    }

    /** @param array<string, mixed> $payload */
    public function emit(array $payload): void
    {
        if ($this->emit !== null) {
            ($this->emit)($payload);
        }
    }

    /**
     * Durable interrupt (LangGraph's interrupt()). On the first pass this throws
     * GraphInterrupt and the run suspends; on resume — once a human value has
     * been injected under $key — the same call returns that value instead of
     * throwing, so the node continues from where it paused.
     *
     * @param  mixed  $request  payload surfaced to the human (e.g. approval request)
     * @return mixed the injected resume value
     */
    public function interrupt(string $key, mixed $request = null): mixed
    {
        if ($this->hasResume($key)) {
            return $this->scratch[$key];
        }

        throw new GraphInterrupt(['key' => $key, 'request' => $request]);
    }

    /** Human input injected on resume for the interrupted node (Phase 2). */
    public function resumeValue(string $key, mixed $default = null): mixed
    {
        return $this->scratch[$key] ?? $default;
    }

    public function setResume(string $key, mixed $value): void
    {
        $this->scratch[$key] = $value;
    }

    public function hasResume(string $key): bool
    {
        return array_key_exists($key, $this->scratch);
    }
}
