<?php

namespace App\Services\BosskuAi\Ralphinho;

/**
 * A single work unit decomposed from an RFC. Ported from ECC's Ralphinho
 * RFC-driven DAG pipeline. Each WorkUnit has a complexity tier that determines
 * the pipeline depth (how many stages run): trivial (1 stage), small (3),
 * medium (5), large (7+). Separate context windows per stage eliminate
 * author-bias; dependencies form a DAG; the merge queue handles eviction.
 *
 * @see WorkUnitTier for the pipeline-depth table.
 */
final class WorkUnit
{
    /**
     * @param  string  $id
     * @param  string  $name
     * @param  list<string>  $rfcSections  which RFC sections this unit addresses
     * @param  string  $description
     * @param  list<string>  $deps  dependency WorkUnit ids (DAG edges)
     * @param  list<string>  $acceptance  acceptance criteria (must all pass)
     * @param  WorkUnitTier  $tier  complexity tier → pipeline depth
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $rfcSections,
        public readonly string $description,
        public readonly array $deps = [],
        public readonly array $acceptance = [],
        public readonly WorkUnitTier $tier = WorkUnitTier::Small,
    ) {}

    /**
     * Is this unit blocked by any of the given incomplete dependency ids?
     *
     * @param  list<string>  $incompleteDepIds  ids of dependencies not yet done
     */
    public function isBlockedBy(array $incompleteDepIds): bool
    {
        return count(array_intersect($this->deps, $incompleteDepIds)) > 0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rfc_sections' => $this->rfcSections,
            'description' => $this->description,
            'deps' => $this->deps,
            'acceptance' => $this->acceptance,
            'tier' => $this->tier->value,
            'pipeline_stages' => $this->tier->pipelineStages(),
        ];
    }
}