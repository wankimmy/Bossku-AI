<?php

namespace App\Services\Kernel\Runtime;

/**
 * Streaming-mode taxonomy (LangGraph parity). Every kernel-emitted event carries
 * a `mode` so SSE consumers can subscribe selectively. Existing BosskuAI SSE
 * payloads map to `updates`/`custom`; the kernel adds `tasks`, `checkpoints`,
 * and `values`.
 */
final class StreamMode
{
    /** Full state snapshot after a superstep. */
    public const VALUES = 'values';

    /** Per-node state deltas. */
    public const UPDATES = 'updates';

    /** LLM/message token stream. */
    public const MESSAGES = 'messages';

    /** Arbitrary node-emitted payloads. */
    public const CUSTOM = 'custom';

    /** Verbose execution trace (interrupts, errors). */
    public const DEBUG = 'debug';

    /** Checkpoint written. */
    public const CHECKPOINTS = 'checkpoints';

    /** Node scheduled / started / finished. */
    public const TASKS = 'tasks';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::VALUES, self::UPDATES, self::MESSAGES, self::CUSTOM,
            self::DEBUG, self::CHECKPOINTS, self::TASKS,
        ];
    }
}
