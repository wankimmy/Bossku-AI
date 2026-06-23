<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * A Generation is the immutable set of admitted sources at one Context Epoch.
 * The baseline is the concatenated text of all sources at admission time;
 * the snapshot holds the per-source values for later reconcile() comparison.
 *
 * Ported from opencode's Generation { baseline, snapshot }. Context Epochs
 * make compaction and model switches correct: the baseline is immutable within
 * an epoch and replaced atomically when a new epoch begins.
 */
final readonly class Generation
{
    /**
     * @param  array<string, AdmittedSource>  $sources  keyed by ContextKey value
     * @param  string  $baseline  the concatenated baseline text
     * @param  int  $epoch  the epoch number this generation belongs to
     */
    public function __construct(
        public array $sources,
        public string $baseline,
        public int $epoch,
    ) {}

    public function sourceText(ContextKey $key): ?string
    {
        return $this->sources[$key->value]?->text;
    }

    public function sourceValue(ContextKey $key): mixed
    {
        return $this->sources[$key->value]?->value;
    }
}