<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * One typed source of system-prompt context. Ported from opencode's Source<A>.
 *
 * Each source has:
 * - a stable namespaced `key` (so duplicates are detectable),
 * - a `load()` that produces the current value (typed by the caller),
 * - a `baseline()` renderer (the full text used on first admission),
 * - an `update()` renderer (the delta text used on mid-conversation updates),
 * - a `removed()` renderer (the text used when the source is withdrawn).
 *
 * Sources compose into a SystemContext; reconcile() compares the current
 * snapshot to the admitted baseline and returns Unchanged | Updated |
 * ReplacementReady | ReplacementBlocked. This replaces ad-hoc prompt
 * fragment injection with a principled algebra: context becomes versioned,
 * refreshable, and compaction-correct.
 *
 * @template A
 */
abstract class ContextSource
{
    public function __construct(public readonly ContextKey $key) {}

    /**
     * Load the current value. Called at admission and on reconcile. Must be
     * pure (no side effects) so reconcile can call it safely.
     *
     * @return A
     */
    abstract public function load(): mixed;

    /**
     * Render the baseline text — the full content admitted on first load.
     *
     * @param  A  $value
     */
    abstract public function baseline(mixed $value): string;

    /**
     * Render an update text — the delta admitted when the value changes
     * mid-conversation. Default: re-emit the baseline (simplest correct behavior).
     *
     * @param  A  $newValue
     * @param  A  $oldValue
     */
    public function update(mixed $newValue, mixed $oldValue): string
    {
        return $this->baseline($newValue);
    }

    /**
     * Render a removal text — admitted when the source is withdrawn (e.g.
     * a memory was forgotten, a skill was deactivated). Default: a one-line note.
     */
    public function removed(): string
    {
        return "[removed: {$this->key}]";
    }
}