<?php

namespace App\Services\Kernel;

/**
 * Sentinel node names for the graph kernel. Mirrors LangGraph's START/END.
 */
final class Constants
{
    /** Virtual source node: edges from START define entry points. */
    public const START = '__start__';

    /** Virtual sink node: edges to END terminate a branch. */
    public const END = '__end__';

    /** Checkpoint sources. */
    public const SOURCE_INPUT = 'input';
    public const SOURCE_LOOP = 'loop';
    public const SOURCE_INTERRUPT = 'interrupt';
    public const SOURCE_FORK = 'fork';

    /** Run statuses produced by the runner. */
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_INTERRUPTED = 'interrupted';
    public const STATUS_MAX_STEPS = 'max_steps';
}
