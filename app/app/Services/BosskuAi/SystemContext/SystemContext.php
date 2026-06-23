<?php

namespace App\Services\BosskuAi\SystemContext;

/**
 * The System Context algebra: combines typed ContextSources into a versioned,
 * reconcileable, epoch-aware system prompt. Ported from opencode's
 * SystemContext.make / combine / initialize / reconcile / replace.
 *
 * Why this matters:
 * - Today Bossku-AI injects memory, AGENTS.md, skills, personas as ad-hoc
 *   fragments in OrchestratorService::run(). Order and overlap are implicit.
 * - SystemContext makes each input a typed Source with a stable key. combine()
 *   rejects duplicate keys so composition is deterministic.
 * - reconcile() returns Unchanged | Updated | ReplacementReady |
 *   ReplacementBlocked, so memory updates mid-run become Mid-Conversation
 *   System Messages instead of a full prompt rebuild.
 * - Context Epochs make compaction and model switches correct: the baseline is
 *   immutable within an epoch and replaced atomically on the next.
 *
 * Usage:
 *   $ctx = SystemContext::make()
 *       ->add(new InstructionsSource(new ContextKey('bossku/instructions/global'), $agentsMd))
 *       ->add(new MemorySource(new ContextKey('bossku/memory/durable'), $memories));
 *   $gen = $ctx->initialize(); // Generation{baseline, snapshot, epoch:0}
 *   // ... run the agent with $gen->baseline as the system prompt ...
 *   $result = $ctx->reconcile($gen); // detect drift since admission
 */
final class SystemContext
{
    /** @var array<string, ContextSource> keyed by ContextKey value */
    private array $sources = [];

    private int $epoch = 0;

    /** @param  list<ContextSource>  $sources */
    public static function make(array $sources = []): self
    {
        $ctx = new self;
        foreach ($sources as $source) {
            $ctx->add($source);
        }

        return $ctx;
    }

    public function add(ContextSource $source): self
    {
        $key = $source->key->value;
        if (isset($this->sources[$key])) {
            throw new \InvalidArgumentException("Duplicate SystemContext source key: {$key}");
        }
        $this->sources[$key] = $source;

        return $this;
    }

    /** @return array<string, ContextSource> */
    public function sources(): array
    {
        return $this->sources;
    }

    /**
     * Load all sources and produce the initial Generation (epoch 0). The
     * baseline text is the concatenation of each source's baseline() render,
     * in insertion order.
     */
    public function initialize(): Generation
    {
        return $this->buildGeneration($this->epoch);
    }

    /**
     * Compare each source's current value to its admitted baseline. Returns
     * a per-source result map and a boolean indicating whether any source
     * changed. Callers use this to decide: admit an update message, begin a
     * new epoch (full replacement), or keep the last-admitted state.
     *
     * @return array{results: array<string, ReconcileResult>, changed: bool}
     */
    public function reconcile(Generation $generation): array
    {
        $results = [];
        $changed = false;

        foreach ($this->sources as $key => $source) {
            $admitted = $generation->sources[$key] ?? null;
            if ($admitted === null) {
                // Source added after initialization — treat as updated.
                $results[$key] = ReconcileResult::Updated;
                $changed = true;
                continue;
            }

            $current = $source->load();
            if ($this->valuesEqual($current, $admitted->value)) {
                $results[$key] = ReconcileResult::Unchanged;
                continue;
            }

            $results[$key] = ReconcileResult::Updated;
            $changed = true;
        }

        // Sources that were admitted but are no longer registered.
        foreach ($generation->sources as $key => $admitted) {
            if (! isset($this->sources[$key])) {
                $results[$key] = ReconcileResult::Updated;
                $changed = true;
            }
        }

        return ['results' => $results, 'changed' => $changed];
    }

    /**
     * Begin a new epoch: re-load all sources and produce a fresh Generation.
     * Use after compaction or a model switch, when the baseline must be
     * replaced atomically.
     */
    public function replace(): Generation
    {
        $this->epoch++;

        return $this->buildGeneration($this->epoch);
    }

    public function currentEpoch(): int
    {
        return $this->epoch;
    }

    private function buildGeneration(int $epoch): Generation
    {
        $admitted = [];
        $parts = [];

        foreach ($this->sources as $key => $source) {
            $value = $source->load();
            $text = $source->baseline($value);
            $admitted[$key] = new AdmittedSource($source->key, $value, $text, $epoch);
            $parts[] = $text;
        }

        return new Generation($admitted, implode("\n\n", $parts), $epoch);
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_string($a) && is_string($b)) {
            return $a === $b;
        }

        return serialize($a) === serialize($b);
    }
}